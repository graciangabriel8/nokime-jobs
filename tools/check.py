#!/usr/bin/env python3
"""The checks a change must pass before it goes live. .github/workflows/deploy.yml runs them on every push to main
and moves the branch OVH deploys (live) only when they pass; run them by hand with `python3 tools/check.py`.
Prints one line per failure and exits 1 on any.

1. translations: tools/check-i18n.py (every key a page uses exists in English)
2. scripts parse: node --check on js/*.js and every distinct inline <script> of a page (skipped with a note where
   node is missing; never on GitHub); every JSON-LD block (the structured data search engines read) is valid JSON
3. links: every local href, src, poster, data-src-*/data-poster-* of a page and every url() of a stylesheet
   points at a file of the repo
4. cache versions: every page loads its css/ and js/ files with the same ?v=, and a loaded css/ or js/ file that
   differs from the live branch needs a new ?v= (on GitHub the workflow fetches live; by hand, origin/live as last fetched)
5. secrets: no live or test key, token or deploy-hook address in any tracked file (the scanner first proves it
   catches a planted fake of each kind), and no credential file (*.pem, *.key, .env, config.php) tracked
6. offers: tools/build.py runs clean on a copy of the repo (js/offers.js well formed, every offer complete), and
   what it writes matches what is committed: a live offer without its page, or a questionnaire not rebuilt, fails;
   a page or sitemap line left by an offer that expired since the last build is only noted
7. PHP: php -l on api/*.php (required on GitHub; skipped with a note where php is missing)
"""
import hashlib, json, os, pathlib, re, shutil, subprocess, sys, tempfile

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
INLINE = re.compile(r"(?is)<script\b([^>]*)>(.*?)</script>")
inline = {}   # distinct inline code -> (pages using it, module or not)
for page in pages:
    for attrs, body in INLINE.findall(page.read_text(encoding="utf-8")):
        kind = re.search(r'\stype="([^"]*)"', attrs)
        kind = kind.group(1).lower() if kind else ""
        if re.search(r"\ssrc=", attrs) or not body.strip(): continue
        if kind == "application/ld+json":
            try: json.loads(body)
            except ValueError as e: fail("structured data on %s is not valid JSON: %s" % (page.relative_to(root), e))
        elif kind in ("", "text/javascript", "module"):
            inline.setdefault(body, ([], kind == "module"))[0].append(page)
if node:
    with tempfile.TemporaryDirectory() as tmp:
        for body, (used, module) in inline.items():
            f = pathlib.Path(tmp) / (hashlib.sha1(body.encode()).hexdigest() + (".mjs" if module else ".js"))
            f.write_text(body, encoding="utf-8")
            r = subprocess.run([node, "--check", str(f)], capture_output=True, text=True)
            if r.returncode != 0: fail("inline script on %s%s does not parse: %s" % (used[0].relative_to(root), " and %d other page(s)" % (len(used) - 1) if len(used) > 1 else "",
                                                                                   next((l for l in r.stderr.splitlines() if "Error" in l), "?").strip()))

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
VERSIONED = re.compile(r'(?:css|js)/[\w.-]+\.(?:css|js)\?v=(\d+)')
loaded = set()   # the css/ and js/ files pages load with ?v=
for page in pages:
    for url in ATTR.findall(page.read_text(encoding="utf-8")):
        path = url.split("#")[0].split("?")[0]
        if local(url) and VERSIONED.search(url):
            target = pathlib.Path(os.path.normpath((root / path.lstrip("/")) if path.startswith("/") else (page.parent / path)))
            if root in target.parents: loaded.add(str(target.relative_to(root)))
live = subprocess.run(["git", "-C", str(root), "rev-parse", "--verify", "-q", "refs/remotes/origin/live^{commit}"], capture_output=True, text=True).stdout.strip()
if live and len(seen) == 1:
    now = next(iter(seen))
    then = VERSIONED.search(subprocess.run(["git", "-C", str(root), "show", live + ":index.html"], capture_output=True, text=True).stdout)
    moved = subprocess.run(["git", "-C", str(root), "diff", "--name-only", live, "--", "css", "js"], capture_output=True, text=True).stdout.split()
    stale = sorted(set(moved) & loaded)
    if then and then.group(1) == now and stale:
        fail("cache: %s changed since the live version but ?v= is still %s, so visitors keep the old copy: run python3 tools/bump.py" % (", ".join(stale), now))
elif not live and CI: fail("cache: the live branch was not fetched, so a change without a new ?v= cannot be seen")
elif not live: print("note: no origin/live here (git fetch origin), changes without a new ?v= not checked")

# 5. secrets
SECRET = re.compile(r"\b(?:sk|rk)_(?:live|test)_[0-9A-Za-z]{16,}|\bwhsec_[0-9A-Za-z]{16,}|\bgh[opsur]_[0-9A-Za-z]{30,}"
                    r"|\bgithub_pat_[0-9A-Za-z_]{30,}|\bEAA[0-9A-Za-z]{40,}|\bIGAA[0-9A-Za-z_-]{40,}|\bxox[abpr]-[0-9A-Za-z-]{10,}"
                    r"|\bAKIA[0-9A-Z]{16}\b|-----BEGIN [A-Z ]*PRIVATE KEY-----|webhooks-webhosting\.[a-z.]+/1\.0/vcs/github/push/[A-Za-z0-9._-]{40,}")
PLANTED = ["sk_" + "live_" + "Z" * 24, "rk_" + "test_" + "Z" * 24, "whsec_" + "Z" * 32, "gh" + "o_" + "Z" * 36, "github_" + "pat_" + "Z" * 40,
           "E" + "AA" + "Z" * 60, "IG" + "AA" + "Z" * 60, "xo" + "xb-" + "Z" * 20, "AK" + "IA" + "Z" * 16, "-----BEGIN " + "RSA PRIVATE KEY-----",
           "https://webhooks-" + "webhosting.eu.ovhapis.com/1.0/vcs/github/push/" + "Z" * 48]
for planted in PLANTED:
    if not SECRET.search("x = '%s';" % planted): fail("secrets: the scanner missed a planted fake (%s…), so its silence proves nothing" % planted[:6])
for f in tracked():   # every tracked file, read as bytes: no extension or encoding lets one through
    try: text = f.read_bytes().decode("utf-8", errors="replace")
    except OSError: continue
    for m in SECRET.finditer(text):   # the logs are public: say where, never any character of what
        fail("secret: %s line %d holds something shaped like a live key or deploy-hook address" % (f.relative_to(root), text.count("\n", 0, m.start()) + 1))
    rel = f.relative_to(root)
    if rel.suffix in (".pem", ".key", ".p12", ".pfx") or rel.name.startswith(".env") or rel.name == "config.php":
        fail("secret: %s is tracked; keys and credentials never go in the repository (the repository is public: rotate whatever it held)" % rel)

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
        out = (r.stdout + r.stderr).strip().splitlines() or ["?"]   # "Errors parsing" alone says nothing: show the parse error
        if r.returncode != 0: fail("PHP %s: %s" % (f.relative_to(root), next((l for l in out if "error:" in l.lower()), out[0]).strip()))
elif CI: fail("PHP: php is missing on the runner")
else: print("note: php missing here, api/*.php not linted (GitHub does it)")

for f in failures: print("FAIL " + f)
print("%d page(s) checked: %s" % (len(pages), "all good" if not failures else "%d failure(s)" % len(failures)))
sys.exit(1 if failures else 0)
