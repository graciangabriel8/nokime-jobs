#!/usr/bin/env python3
"""The checks a change must pass before it goes live. .github/workflows/deploy.yml runs them on every push to main
and moves the branch OVH deploys (live) only when they pass; run them by hand with `python3 tools/check.py`.
Prints one line per failure and exits 1 on any.

1. translations: tools/check-i18n.py (every key a page uses exists in English)
2. scripts parse: node --check on js/*.js (skipped with a note where node is missing; never on GitHub)
3. links: every local href, src, poster, data-src-*/data-poster-* of a page and every url() of a stylesheet
   points at a file of the repo
4. cache versions: every page loads its css/ and js/ files with the same ?v=
5. secrets: no live key, token or deploy-hook address in a tracked text file (the scanner first proves it
   catches a planted fake key)
6. offers: tools/build.py runs clean on a copy of the repo (js/offers.js well formed, every offer complete), and
   what it writes matches what is committed: a live offer without its page, or a questionnaire not rebuilt, fails;
   a page or sitemap line left by an offer that expired since the last build is only noted
7. PHP: php -l on api/*.php (required on GitHub; skipped with a note where php is missing)
"""
import os, re, shutil, subprocess, sys, pathlib, tempfile

root = pathlib.Path(__file__).resolve().parent.parent
CI = os.environ.get("GITHUB_ACTIONS") == "true"
failures = []
def fail(msg): failures.append(msg)
def tracked(*globs):
    out = subprocess.run(["git", "-C", str(root), "ls-files", "-z", *globs], capture_output=True, text=True, check=True).stdout
    return [root / p for p in out.split("\0") if p]

# Pages: what visitors get. Files and folders starting with "_", tools/ and design/ are working material.
pages = [p for p in tracked("*.html") if not any(part.startswith("_") or part in ("tools", "design") for part in p.relative_to(root).parts)]

# 1. translations
r = subprocess.run([sys.executable, str(root / "tools/check-i18n.py")], capture_output=True, text=True)
if r.returncode != 0: fail("translations: " + (r.stdout + r.stderr).strip().replace("\n", " | "))

# 2. scripts parse
node = shutil.which("node")
if node:
    for js in tracked("js/*.js"):
        r = subprocess.run([node, "--check", str(js)], capture_output=True, text=True)
        if r.returncode != 0: fail("script %s does not parse: %s" % (js.relative_to(root), next((l for l in r.stderr.splitlines() if "Error" in l), "?").strip()))
elif CI: fail("scripts: node is missing on the runner")
else: print("note: node missing here, scripts not parsed")

# 3. links
ATTR = re.compile(r'\s(?:href|src|poster|data-src-fr|data-src-en|data-poster-fr|data-poster-en)="([^"]*)"')
def local(url):
    return url and not re.match(r"(?i)^([a-z][a-z0-9+.-]*:|#|//)", url)   # any scheme (https:, mailto:, sms:, whatsapp:…) is outside the repo
def exists(base, url):
    path = url.split("#")[0].split("?")[0]
    if not path: return True
    target = (root / path.lstrip("/")) if path.startswith("/") else (base / path)
    target = pathlib.Path(os.path.normpath(target))
    if root not in target.parents and target != root: return False   # leaves the site
    return (target / "index.html").is_file() if path.endswith("/") or target.is_dir() else target.is_file()
for page in pages:
    for url in ATTR.findall(page.read_text(encoding="utf-8")):
        if local(url) and not exists(page.parent, url): fail("link: %s points at %s, which is not in the repo" % (page.relative_to(root), url))
for css in tracked("css/*.css"):
    for url in re.findall(r"url\(\s*['\"]?([^'\")]+)['\"]?\s*\)", css.read_text(encoding="utf-8")):
        if local(url) and not exists(css.parent, url): fail("link: %s points at %s, which is not in the repo" % (css.relative_to(root), url))

# 4. cache versions
seen = {}
for page in pages:
    for v in set(re.findall(r'(?:css|js)/[\w.-]+\.(?:css|js)\?v=(\d+)', page.read_text(encoding="utf-8"))):
        seen.setdefault(v, []).append(str(page.relative_to(root)))
if len(seen) > 1: fail("cache versions differ between pages: " + "; ".join("v=%s on %s" % (v, ", ".join(sorted(ps))) for v, ps in sorted(seen.items())))

# 5. secrets
SECRET = re.compile(r"\b(?:sk|rk)_live_[0-9A-Za-z]{16,}|\bsk_test_[0-9A-Za-z]{16,}|\bwhsec_[0-9A-Za-z]{16,}|\bghp_[0-9A-Za-z]{30,}"
                    r"|\bgithub_pat_[0-9A-Za-z_]{30,}|\bxox[abpr]-[0-9A-Za-z-]{10,}|\bAKIA[0-9A-Z]{16}\b|-----BEGIN [A-Z ]*PRIVATE KEY-----"
                    r"|webhooks-webhosting\.[a-z.]+/1\.0/vcs/github/push/[A-Za-z0-9._-]{40,}")
for planted in ("sk_" + "live_" + "Z" * 24, "https://webhooks-" + "webhosting.eu.ovhapis.com/1.0/vcs/github/push/" + "Z" * 48):
    if not SECRET.search("x = '%s';" % planted): fail("secrets: the scanner missed a planted fake key, so its silence proves nothing")
for f in tracked():   # every tracked file, read as bytes: no extension or encoding lets one through
    try: text = f.read_bytes().decode("utf-8", errors="replace")
    except OSError: continue
    for m in SECRET.finditer(text):   # the logs are public: say where, never any character of what
        fail("secret: %s line %d holds something shaped like a live key or deploy-hook address" % (f.relative_to(root), text.count("\n", 0, m.start()) + 1))

# 6. offers: the builder validates js/offers.js and writes the offer pages; run it on a copy, never on the repo
with tempfile.TemporaryDirectory() as tmp:
    copy = pathlib.Path(tmp) / "site"
    shutil.copytree(root, copy, ignore=shutil.ignore_patterns(".git"))
    r = subprocess.run([sys.executable, str(copy / "tools/build.py")], capture_output=True, text=True, cwd=str(copy))
    if r.returncode != 0: fail("offers: tools/build.py stopped: " + (r.stderr or r.stdout).strip().splitlines()[-1])
    else:
        for rel in ("questionnaire/index.html", "js/i18n.js"):
            if (copy / rel).read_bytes() != (root / rel).read_bytes(): fail("build: %s differs from what tools/build.py writes: run it and commit" % rel)
        built = {p.relative_to(copy) for p in (copy / "o").rglob("index.html")} if (copy / "o").is_dir() else set()
        kept = {p.relative_to(root) for p in (root / "o").rglob("index.html")} if (root / "o").is_dir() else set()
        for rel in sorted(built):
            if rel not in kept or (copy / rel).read_bytes() != (root / rel).read_bytes(): fail("build: %s is missing or out of date: run tools/build.py and commit" % rel)
        for rel in sorted(kept - built): print("note: %s belongs to an expired offer; tools/build.py removes it" % rel)
        loc = lambda p: set(re.findall(r"<loc>([^<]+)</loc>", p.read_text(encoding="utf-8"))) if p.is_file() else set()
        new, old = loc(copy / "sitemap.xml"), loc(root / "sitemap.xml")
        for u in sorted(new - old): fail("build: sitemap.xml lacks %s: run tools/build.py and commit" % u)
        for u in sorted(old - new): print("note: sitemap.xml still lists %s, an offer that expired" % u)

# 7. PHP
php = shutil.which("php")
if php:
    for f in tracked("api/*.php"):
        r = subprocess.run([php, "-l", str(f)], capture_output=True, text=True)
        if r.returncode != 0: fail("PHP %s: %s" % (f.relative_to(root), (r.stdout + r.stderr).strip().splitlines()[0]))
elif CI: fail("PHP: php is missing on the runner")
else: print("note: php missing here, api/*.php not linted (GitHub does it)")

for f in failures: print("FAIL " + f)
print("%d page(s) checked: %s" % (len(pages), "all good" if not failures else "%d failure(s)" % len(failures)))
sys.exit(1 if failures else 0)
