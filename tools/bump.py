#!/usr/bin/env python3
"""Raise the ?v=N on every stylesheet and script link in every page (any depth, and the root-absolute
links of 404.html), so a push never serves new HTML with a browser's cached CSS or JS: the OVH
webhook deploys within seconds, but browsers keep the CSS and JS they cached.
Run before every commit that touches css/ or js/:

    python3 tools/bump.py
"""
import re, pathlib
root = pathlib.Path(__file__).resolve().parent.parent
pages = sorted(root.glob("**/index.html")) + [p for p in [root / "404.html"] if p.exists()]
pat = re.compile(r'((?:href|src)="(?:/|(?:\.\./)*)(?:css|js)/[\w-]+\.(?:css|js))(?:\?v=(\d+))?"')
current = max((int(v) for p in pages for _, v in pat.findall(p.read_text(encoding="utf-8")) if v), default=0)
nxt = current + 1
for p in pages:
    s = p.read_text(encoding="utf-8")
    s2, n = pat.subn(lambda m: m.group(1) + "?v=%d\"" % nxt, s)
    p.write_text(s2, encoding="utf-8")
print(f"v{current} → v{nxt} on {len(pages)} pages")
