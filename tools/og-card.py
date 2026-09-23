#!/usr/bin/env python3
"""Render og.png, the 1200×630 card link previews show, and apple-touch-icon.png, in the site's
own identity: white paper, the steel of the pass, one ink, one blue, Bricolage Grotesque (800,
width 85 %) and DM Mono. The rail drawing is read out of index.html (one source for both). Fetches two
static TTFs once from the fonts API (the site itself serves variable woff2 files; QuickLook wants
TTF), writes SVGs with the fonts embedded, and rasterises them with macOS QuickLook:

    python3 tools/og-card.py            # writes ./og.png and ./apple-touch-icon.png
"""
import base64, pathlib, re, subprocess, sys, tempfile, urllib.request
root = pathlib.Path(__file__).resolve().parent.parent
UA_OLD = "Mozilla/4.0"   # a legacy user agent makes the fonts API hand over TTF files
INK, INK2, BLEU, PAPER, STEEL = "#17191E", "#5A606B", "#2038D5", "#FFFFFF", "#E7EAED"

def ttf(family_query):
    css = urllib.request.urlopen(urllib.request.Request("https://fonts.googleapis.com/css2?family=" + family_query, headers={"User-Agent": UA_OLD}), timeout=40).read().decode()
    m = re.search(r"url\(([^)]+\.ttf)\)", css)
    if not m: sys.exit("no TTF for " + family_query)
    return base64.b64encode(urllib.request.urlopen(m.group(1), timeout=40).read()).decode()

sans = ttf("Bricolage+Grotesque:opsz,wdth,wght@96,85,800")
mono = ttf("DM+Mono:wght@500")

home = (root / "index.html").read_text(encoding="utf-8")
drawing = re.search(r'<svg class="pass-drawing" viewBox="0 0 520 330"[^>]*>(.*?)</svg>', home, re.S).group(1).replace("currentColor", INK)
mark = re.search(r'<svg class="mark" viewBox="0 0 32 32" aria-hidden="true">(.*?)</svg>', home, re.S).group(1)
mark = (mark.replace('class="mk-t"', 'fill="%s" stroke="%s" stroke-width="1.8" stroke-linejoin="round"' % (PAPER, INK))
            .replace('class="mk-b"', 'fill="%s"' % BLEU).replace('class="mk-rail"', 'fill="%s"' % INK))

# QuickLook thumbnails a square: the 1200×630 card sits centred on a 1200×1200 canvas, cropped after.
card = f'''<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1200 1200">
<defs><style>
@font-face{{font-family:"Bricolage Grotesque";src:url(data:font/ttf;base64,{sans}) format("truetype")}}
@font-face{{font-family:"DM Mono";src:url(data:font/ttf;base64,{mono}) format("truetype")}}
</style></defs>
<rect width="1200" height="1200" fill="{PAPER}"/>
<g transform="translate(0,285)">
<rect x="780" y="0" width="420" height="630" fill="{STEEL}"/>
<g transform="translate(790,180) scale(.77)">{drawing}</g>
<g transform="translate(66,58) scale(1.5)">{mark}</g>
<text x="122" y="92" font-family="Bricolage Grotesque, Helvetica, sans-serif" font-size="32" fill="{INK}">Nokime Jobs</text>
<text font-family="Bricolage Grotesque, Helvetica, sans-serif" font-size="64" letter-spacing="-1.1" fill="{INK}">
<tspan x="70" y="258">Des stages et des saisons</tspan><tspan x="70" y="320">dans des cuisines</tspan><tspan x="70" y="382" fill="{BLEU}">qui en valent la peine.</tspan></text>
<text x="72" y="530" font-family="DM Mono, Menlo, monospace" font-size="17" letter-spacing="1.7" fill="{INK}">STAGES · ALTERNANCES · SAISONS</text>
<text x="72" y="562" font-family="DM Mono, Menlo, monospace" font-size="17" letter-spacing="1.7" fill="{INK2}">GRATUIT POUR LES CANDIDATS</text>
</g>
</svg>'''
# the icon: the mark, large, on the steel of the pass (drawn at width 1200 so QuickLook fills the frame)
icon = f'''<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 180 180">
<rect width="180" height="180" fill="{STEEL}"/>
<g transform="translate(16.4,17.55) scale(4.6)">{mark}</g>
</svg>'''

tmp = pathlib.Path(tempfile.mkdtemp())
for name, svg, size in (("og", card, 1200), ("icon", icon, 180)):
    src = tmp / (name + ".svg"); src.write_text(svg, encoding="utf-8")
    subprocess.run(["qlmanage", "-t", "-s", str(size), "-o", str(tmp), str(src)], check=True, capture_output=True)
    if not (tmp / (name + ".svg.png")).exists(): sys.exit("QuickLook produced no PNG for " + name)
subprocess.run(["sips", "--cropToHeightWidth", "630", "1200", str(tmp / "og.svg.png"), "--out", str(root / "og.png")], check=True, capture_output=True)
(root / "apple-touch-icon.png").write_bytes((tmp / "icon.svg.png").read_bytes())
print("og.png:", (root / "og.png").stat().st_size // 1024, "KB · apple-touch-icon.png:", (root / "apple-touch-icon.png").stat().st_size // 1024, "KB")
