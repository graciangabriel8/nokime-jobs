#!/usr/bin/env python3
"""Offer pages and the sitemap, from js/offers.js.

Each real offer becomes o/<id>/index.html: a page a student can share and a search
engine can read, with schema.org JobPosting data so job search features can list it.
Demo offers never get a page. Expired offers get none either, and their old page is
removed. The sitemap lists the site's pages and the live offers.

    python3 tools/build.py
"""
import json, re, pathlib, datetime, shutil, html, urllib.parse
root = pathlib.Path(__file__).resolve().parent.parent
BASE = "https://graciangabriel8.github.io/nokime-jobs/"
today = datetime.date.today().isoformat()

src = (root / "js/offers.js").read_text(encoding="utf-8")
m = re.search(r"window\.NOKIME_JOBS\s*=\s*(\[.*?\]);\s*\n", src, re.S)
offers = json.loads(m.group(1)) if m and m.group(1).strip() != "[]" else []
live = [o for o in offers if not o.get("demo") and re.fullmatch(r"\d{4}-\d{2}-\d{2}", str(o.get("expires") or o.get("end") or "")) and (o.get("expires") or o["end"]) >= today]   # as js/jobs.js: undated or past its end, no page

home = (root / "index.html").read_text(encoding="utf-8")
head = home[:home.index("<header")]; header = home[home.index("<header"):home.index("<main>")]
footer = home[home.index("<footer"):home.index('<script src="js/i18n.js')]; scripts = home[home.index('<script src="js/i18n.js'):home.index('<script src="js/offers.js')]
KIND = {"stage": "Stage", "alternance": "Alternance", "saison": "Saison"}
EMP = {"stage": "INTERN", "alternance": "FULL_TIME", "saison": "TEMPORARY"}
REGIONS = {"Auvergne-Rhône-Alpes": "01 03 07 15 26 38 42 43 63 69 73 74", "Bourgogne-Franche-Comté": "21 25 39 58 70 71 89 90", "Bretagne": "22 29 35 56", "Centre-Val de Loire": "18 28 36 37 41 45", "Corse": "2A 2B 20", "Grand Est": "08 10 51 52 54 55 57 67 68 88", "Hauts-de-France": "02 59 60 62 80", "Île-de-France": "75 77 78 91 92 93 94 95", "Normandie": "14 27 50 61 76", "Nouvelle-Aquitaine": "16 17 19 23 24 33 40 47 64 79 86 87", "Occitanie": "09 11 12 30 31 32 34 46 48 65 66 81 82", "Pays de la Loire": "44 49 53 72 85", "Provence-Alpes-Côte d’Azur": "04 05 06 13 83 84", "Outre-mer": "971 972 973 974 976"}
DEPT = {d: r for r, ds in REGIONS.items() for d in ds.split()}
def fr_date(iso):
    d = datetime.date.fromisoformat(iso); return d.strftime("%-d ") + ["janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre"][d.month - 1] + d.strftime(" %Y")
SEAL = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="sl-o" d="M12 1.8A2.15 2.15 0 0 1 15.9 2.58A2.15 2.15 0 0 1 19.21 4.79A2.15 2.15 0 0 1 21.42 8.1A2.15 2.15 0 0 1 22.2 12A2.15 2.15 0 0 1 21.42 15.9A2.15 2.15 0 0 1 19.21 19.21A2.15 2.15 0 0 1 15.9 21.42A2.15 2.15 0 0 1 12 22.2A2.15 2.15 0 0 1 8.1 21.42A2.15 2.15 0 0 1 4.79 19.21A2.15 2.15 0 0 1 2.58 15.9A2.15 2.15 0 0 1 1.8 12A2.15 2.15 0 0 1 2.58 8.1A2.15 2.15 0 0 1 4.79 4.79A2.15 2.15 0 0 1 8.1 2.58A2.15 2.15 0 0 1 12 1.8Z"/><circle class="sl-i" cx="12" cy="12" r="6.6"/></svg>'   # the distinction's rosette, as js/jobs.js draws it
def rel(s): return s.replace('href="css/', 'href="../../css/').replace('src="js/', 'src="../../js/').replace('href="apple-touch-icon.png"', 'href="../../apple-touch-icon.png"').replace('href="./"', 'href="../../"').replace('href="publier/"', 'href="../../publier/"').replace('href="ecoles/"', 'href="../../ecoles/"').replace('href="contact/"', 'href="../../contact/"').replace('href="mentions-legales/"', 'href="../../mentions-legales/"').replace('href="./#offres"', 'href="../../#offres"')

odir = root / "o"
for old in odir.glob("*/"):
    if old.name not in {o["id"] for o in live}: shutil.rmtree(old)
for o in live:
    e = html.escape
    title = "%s — %s, %s · Nokime Jobs" % (o["role"], o["restaurant"], o["city"])
    desc = "%s : %s à %s, du %s au %s. %s%s" % (KIND[o["kind"]], o["role"], o["city"], fr_date(o["start"]), fr_date(o["end"]), o.get("pay", ""), ", logé" if o.get("housing") and "logé" not in o.get("pay", "") else "")
    facts = "".join('<div class="fact"><dt>%s</dt><dd>%s</dd></div>' % (k, html.escape(v)) for k, v in [("Dates", "du %s au %s" % (fr_date(o["start"]), fr_date(o["end"]))), ("Heures", ("%s h par semaine" % o["hours"]) if o.get("hours") else ""), ("Rémunération", o.get("pay", "")), ("Logement", "Logé" if o.get("housing") else "")] if v)
    ld = {"@context": "https://schema.org", "@type": "JobPosting", "title": o["role"], "description": (o.get("text") or desc), "datePosted": o.get("published", today),
          "validThrough": (o.get("expires") or o["end"]) + "T23:59:59", "employmentType": EMP[o["kind"]],
          "hiringOrganization": {"@type": "Organization", "name": o["restaurant"]},
          "jobLocation": {"@type": "Place", "address": {"@type": "PostalAddress", "addressLocality": o["city"], "addressRegion": DEPT.get(str(o["dept"]), ""), "addressCountry": "FR"}},
          "jobStartDate": o["start"], "directApply": True}
    h = head
    h = re.sub(r"<title>[^<]*</title>", "<title>%s</title>" % e(title), h)
    h = re.sub(r'<meta name="description" content="[^"]*">', '<meta name="description" content="%s">' % e(desc), h)
    h = re.sub(r'<meta property="og:title" content="[^"]*">', '<meta property="og:title" content="%s">' % e(title), h)
    h = re.sub(r'<meta property="og:description" content="[^"]*">', '<meta property="og:description" content="%s">' % e(desc), h)
    h = h.replace(BASE + '">', BASE + "o/" + o["id"] + '/">').replace('<meta name="twitter:card"', '<script type="application/ld+json">%s</script>\n<meta name="twitter:card"' % json.dumps(ld, ensure_ascii=False).replace("</", "<\\/"))
    h = re.sub(r'<body[^>]*>', '<body>', h)
    mail = "mailto:%s?subject=%s&body=%s" % (o["contact"]["email"], urllib.parse.quote("Candidature — %s — %s" % (o["role"], o["restaurant"])), urllib.parse.quote("Bonjour,\n\nJe vous écris pour le poste de %s à partir du %s.\n\n" % (o["role"], fr_date(o["start"]))))
    main = '''<main>
  <section class="page-hero offer-page">
    <div class="wrap">
      <p class="eyebrow">%s · %s</p>
      <h1 class="display">%s</h1>
      <p class="lead">%s%s</p>
    </div>
  </section>

  <section class="pass">
    <div class="wrap">
      <div class="offer-card">
        <dl class="job-facts">%s</dl>
        %s
        <div class="cta-row">
          <a class="btn primary" href="%s"><span>Écrire au restaurant</span><span class="arrow" aria-hidden="true">→</span></a>
          %s
          <a class="btn" href="../../#offres"><span>Toutes les offres</span></a>
        </div>
      </div>
    </div>
  </section>
</main>

''' % (e(KIND[o["kind"]]), e(o["city"]) + (" · " + e(DEPT[str(o["dept"])]) if str(o["dept"]) in DEPT else ""), e(o["role"]), e(o["restaurant"]),
       '<span class="distinction" role="img" aria-label="Distinction Nokime" title="Distinction Nokime">%s</span>' % SEAL if o.get("distinction") else '<span class="distinction empty" aria-hidden="true"></span>', facts,
       ('<p class="offer-text">%s</p>' % e(o["text"])) if o.get("text") else "", e(mail),
       ('<a class="btn" href="tel:%s">%s</a>' % (e(re.sub(r"\s", "", o["contact"]["phone"])), e(o["contact"]["phone"]))) if o["contact"].get("phone") else "")
    page = rel(h + header + main + footer + scripts)
    (odir / o["id"]).mkdir(parents=True, exist_ok=True)
    (odir / o["id"] / "index.html").write_text(page, encoding="utf-8")

urls = [BASE, BASE + "publier/", BASE + "ecoles/", BASE + "contact/"] + [BASE + "o/%s/" % o["id"] for o in live]
(root / "sitemap.xml").write_text('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' + "".join("  <url><loc>%s</loc></url>\n" % u for u in urls) + "</urlset>\n", encoding="utf-8")
print("offers: %d live, %d pages written, sitemap %d urls" % (len(live), len(live), len(urls)))
