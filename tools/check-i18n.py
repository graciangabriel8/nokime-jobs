#!/usr/bin/env python3
"""Every data-t key, data-title and data-desc used by a page must exist in the `en`
block of js/i18n.js, or the English view keeps that string in French. Prints what is
missing, and the `en` keys no page uses (informational). Exit 1 on a missing key."""
import re, pathlib, sys, json
root = pathlib.Path(__file__).resolve().parent.parent
pages = sorted(root.glob("**/index.html")) + [p for p in [root / "404.html"] if p.exists()]
used = {}
for p in pages:
    s = p.read_text(encoding="utf-8")
    m = re.search(r"Object\.assign\(NOKIME_I18N\.en,(\{.*?\})\);</script>", s)   # an offer page carries its own English
    own = set(json.loads(m.group(1))) if m else set()
    for k in re.findall(r'data-t="([^"]+)"', s):
        if k not in own: used.setdefault(k, set()).add(p.relative_to(root).as_posix())
    for k in re.findall(r'data-t-attr="[^"]*?:([^",]+)"', s): used.setdefault(k.strip(), set()).add(p.relative_to(root).as_posix())
    for k in re.findall(r'data-(?:title|desc)="([^"]+)"', s): used.setdefault(k, set()).add(p.relative_to(root).as_posix())
js = (root / "js" / "i18n.js").read_text(encoding="utf-8")
en_block = js[js.index("\n  en: {"):]
en = set(re.findall(r'(?m)^\s*(\w+):\s', en_block)) | set(re.findall(r'[,{]\s*(\w+):\s*"', en_block))
missing = {k: v for k, v in used.items() if k not in en}
unused = sorted(k for k in en if k not in used and k not in ("demoTitle","demoHint","demoTotal","demoPrice","demoRatio","demoIn","demoUnder","demoOver","demoSeason","demoAllYear","demoFrom","demoNote","demoQtyLabel","demoPriceLabel","preset1","preset2","months","langSwitch","themeSwitch","menuLabel"))
print(f"{len(pages)} pages, {len(used)} keys used, {len(en)} keys in en")
for k, v in sorted(missing.items()): print("MISSING in en:", k, "←", ", ".join(sorted(v)))
if unused: print("en keys no page uses:", ", ".join(unused))
# French typography: a narrow no-break space before ; : ? ! and no-break spaces inside « » and before €.
# The English legal block, scripts and styles are skipped. A plain ASCII space there is a defect (exit 1).
def french_text(s):
    s = re.sub(r"<script\b.*?</script>|<style\b.*?</style>", " ", s, flags=re.S)
    s = re.sub(r'<div data-lang="en">.*?\n      </div>\n', " ", s, flags=re.S)
    s = re.sub(r'<span data-lang="en">.*?</span>', " ", s, flags=re.S)
    return re.sub(r"<[^>]+>", " ", s)
typo = {}
for p in pages:
    txt = french_text(p.read_text(encoding="utf-8"))
    hits = re.findall(r"\S [;:?!](?!\S)|« |\d €| »", txt)
    if hits: typo[p.relative_to(root).as_posix()] = hits
for k, v in sorted(typo.items()): print("TYPOGRAPHY (ASCII space):", k, "←", ", ".join(repr(h) for h in v[:6]))

sys.exit(1 if missing or typo else 0)
