#!/usr/bin/env python3
"""Render og.png, the 1200×630 card link previews show, and apple-touch-icon.png, in the site's
own identity: white paper, one ink, one blue, Bricolage Grotesque and DM Mono. Fetches two static
TTFs once from the fonts API (the site itself serves variable woff2 files; QuickLook wants TTF),
writes an SVG with the fonts embedded, and rasterises it with macOS QuickLook:

    python3 tools/og-card.py            # writes ./og.png and ./apple-touch-icon.png
"""
import base64, pathlib, re, subprocess, sys, tempfile, urllib.request
root = pathlib.Path(__file__).resolve().parent.parent
UA_OLD = "Mozilla/4.0"   # a legacy user agent makes the fonts API hand over TTF files
INK, BLEU, PAPER = "#141414", "#2038D5", "#FFFFFF"

def ttf(family_query, weight, stretch):
    css = urllib.request.urlopen(urllib.request.Request("https://fonts.googleapis.com/css2?family=" + family_query, headers={"User-Agent": UA_OLD}), timeout=40).read().decode()
    for block in re.findall(r"@font-face \{(.*?)\}", css, re.S):
        if ("font-weight: %s" % weight) in block and ("font-stretch: %s" % stretch in block or stretch is None):
            url = re.search(r"url\(([^)]+\.ttf)\)", block).group(1)
            return base64.b64encode(urllib.request.urlopen(url, timeout=40).read()).decode()
    sys.exit("no TTF for %s %s %s" % (family_query, weight, stretch))

bricolage = ttf("Bricolage+Grotesque:opsz,wdth,wght@12..96,75..100,200..800", 800, "condensed")
mono = ttf("DM+Mono:wght@500", 500, None)

MARK = '<g transform="translate(%d,%d) scale(%s)" fill="none" stroke="%s" stroke-linecap="square" stroke-linejoin="round"><circle cx="120" cy="120" r="91" stroke-width="18"/><path d="M69 171V69l102 102V69" stroke-width="24"/></g>'

# QuickLook thumbnails a square: the 1200×630 card sits centred on a 1200×1200 canvas, cropped after.
card = f'''<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1200 1200">
<defs><style>
@font-face{{font-family:"Bricolage Grotesque";src:url(data:font/ttf;base64,{bricolage}) format("truetype")}}
@font-face{{font-family:"DM Mono";src:url(data:font/ttf;base64,{mono}) format("truetype")}}
</style></defs>
<rect width="1200" height="1200" fill="{PAPER}"/>
<g transform="translate(0,285)">
{MARK % (72, 52, 0.15, INK)}
<text x="120" y="82" font-family="Bricolage Grotesque, Helvetica, sans-serif" font-size="30" fill="{INK}">Nokime Jobs</text>
<rect x="72" y="118" width="1056" height="6" fill="{INK}"/>
<text font-family="Bricolage Grotesque, Helvetica, sans-serif" font-size="96" letter-spacing="-3" fill="{INK}">
<tspan x="72" y="228">Des stages et des saisons</tspan><tspan x="72" y="322">dans des cuisines</tspan><tspan x="72" y="416" fill="{BLEU}">qui en valent la peine.</tspan></text>
<text x="72" y="500" font-family="DM Mono, Menlo, monospace" font-size="19" letter-spacing="3.2" fill="{INK}">STAGES · ALTERNANCES · SAISONS</text>
<text x="1128" y="500" text-anchor="end" font-family="DM Mono, Menlo, monospace" font-size="19" letter-spacing="3.2" fill="#6B6B6B">GRATUIT POUR LES CANDIDATS</text>
<rect x="72" y="560" width="1056" height="1" fill="{INK}"/>
</g>
</svg>'''
icon = f'''<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 180 180">
<rect width="180" height="180" fill="{PAPER}"/>
<g transform="translate(20,20) scale(0.5833)" fill="none" stroke-linecap="square" stroke-linejoin="round"><circle cx="120" cy="120" r="91" stroke="{INK}" stroke-width="18"/><path d="M69 171V69l102 102V69" stroke="{BLEU}" stroke-width="24"/></g>
</svg>'''

tmp = pathlib.Path(tempfile.mkdtemp())
for name, svg, size in (("og", card, 1200), ("icon", icon, 180)):
    src = tmp / (name + ".svg"); src.write_text(svg, encoding="utf-8")
    subprocess.run(["qlmanage", "-t", "-s", str(size), "-o", str(tmp), str(src)], check=True, capture_output=True)
    if not (tmp / (name + ".svg.png")).exists(): sys.exit("QuickLook produced no PNG for " + name)
subprocess.run(["sips", "--cropToHeightWidth", "630", "1200", str(tmp / "og.svg.png"), "--out", str(root / "og.png")], check=True, capture_output=True)
(root / "apple-touch-icon.png").write_bytes((tmp / "icon.svg.png").read_bytes())
print("og.png:", (root / "og.png").stat().st_size // 1024, "KB · apple-touch-icon.png:", (root / "apple-touch-icon.png").stat().st_size // 1024, "KB")
