#!/usr/bin/env python3
"""Every data-t key, data-title and data-desc used by a page must exist in the `en`
block of js/i18n.js, or the English view keeps that string in French. Prints what is
missing, and the `en` keys no page uses (informational). Exit 1 on a missing key."""
import re, pathlib, sys
root = pathlib.Path(__file__).resolve().parent.parent
pages = sorted(root.glob("**/index.html")) + [p for p in [root / "404.html"] if p.exists()]
used = {}
for p in pages:
    s = p.read_text(encoding="utf-8")
    for k in re.findall(r'data-t="([^"]+)"', s): used.setdefault(k, set()).add(p.relative_to(root).as_posix())
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
sys.exit(1 if missing else 0)
