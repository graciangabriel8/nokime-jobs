#!/usr/bin/env python3
"""Offer pages and the sitemap, from js/offers.js.

Each real offer becomes o/<id>/index.html: a page a student can share and a search
engine can read, with schema.org JobPosting data so job search features can list it.
Demo offers never get a page. Expired offers get none either, and their old page is
removed. The sitemap lists the site's pages and the live offers.

It also writes the questionnaire from tools/questionnaire_questions.py: the period selects and
the question fieldsets of questionnaire/index.html, and the English question strings in
js/i18n.js, each between its start/end markers. Nothing about any answer is read or written here.

    python3 tools/build.py
"""
import json, re, sys, pathlib, datetime, shutil, html, urllib.parse
root = pathlib.Path(__file__).resolve().parent.parent
BASE = "https://jobs.nokime.fr/"
today = datetime.date.today().isoformat()

src = (root / "js/offers.js").read_text(encoding="utf-8")
m = re.search(r"window\.NOKIME_JOBS\s*=\s*(\[.*?\]);\s*\n", src, re.S)
try:
    offers = json.loads(m.group(1)) if m and m.group(1).strip() != "[]" else []
except json.JSONDecodeError as err:   # an offer copied from the demo list keeps its unquoted keys
    at = src[:m.start(1)].count("\n") + err.lineno; col = err.colno + (m.start(1) - src.rfind("\n", 0, m.start(1)) - 1 if err.lineno == 1 else 0)
    sys.exit("js/offers.js, ligne %d, colonne %d : NOKIME_JOBS doit être du JSON strict (clés entre guillemets doubles)." % (at, col))
live = [o for o in offers if not o.get("demo") and re.fullmatch(r"\d{4}-\d{2}-\d{2}", str(o.get("expires") or o.get("end") or "")) and (o.get("expires") or o["end"]) >= today]   # as js/jobs.js: undated or past its end, no page

home = (root / "index.html").read_text(encoding="utf-8")
head = home[:home.index("<header")]; header = home[home.index("<header"):home.index("<main>")]
footer = home[home.index("<footer"):home.index('<script src="js/i18n.js')]; scripts = home[home.index('<script src="js/i18n.js'):home.index('<script src="js/offers.js')]
KIND = {"stage": "Stage", "alternance": "Alternance", "saison": "Saison"}
EMP = {"stage": "INTERN", "alternance": "FULL_TIME", "saison": "TEMPORARY"}
METIER = {"cuisine": "Cuisine", "salle": "Salle", "hebergement": "Hébergement", "spa": "Spa"}   # as js/jobs.js; the English is in js/i18n.js (metier_*)
REGIONS = {"Auvergne-Rhône-Alpes": "01 03 07 15 26 38 42 43 63 69 73 74", "Bourgogne-Franche-Comté": "21 25 39 58 70 71 89 90", "Bretagne": "22 29 35 56", "Centre-Val de Loire": "18 28 36 37 41 45", "Corse": "2A 2B 20", "Grand Est": "08 10 51 52 54 55 57 67 68 88", "Hauts-de-France": "02 59 60 62 80", "Île-de-France": "75 77 78 91 92 93 94 95", "Normandie": "14 27 50 61 76", "Nouvelle-Aquitaine": "16 17 19 23 24 33 40 47 64 79 86 87", "Occitanie": "09 11 12 30 31 32 34 46 48 65 66 81 82", "Pays de la Loire": "44 49 53 72 85", "Provence-Alpes-Côte d’Azur": "04 05 06 13 83 84", "Outre-mer": "971 972 973 974 976"}
DEPT = {d: r for r, ds in REGIONS.items() for d in ds.split()}
def fr_date(iso):
    d = datetime.date.fromisoformat(iso); return d.strftime("%-d ") + ["janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre"][d.month - 1] + d.strftime(" %Y")
def en_date(iso):
    d = datetime.date.fromisoformat(iso); return "%d %s %d" % (d.day, ["January","February","March","April","May","June","July","August","September","October","November","December"][d.month - 1], d.year)
SEAL = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="sl-o" d="M12 1.8A2.15 2.15 0 0 1 15.9 2.58A2.15 2.15 0 0 1 19.21 4.79A2.15 2.15 0 0 1 21.42 8.1A2.15 2.15 0 0 1 22.2 12A2.15 2.15 0 0 1 21.42 15.9A2.15 2.15 0 0 1 19.21 19.21A2.15 2.15 0 0 1 15.9 21.42A2.15 2.15 0 0 1 12 22.2A2.15 2.15 0 0 1 8.1 21.42A2.15 2.15 0 0 1 4.79 19.21A2.15 2.15 0 0 1 2.58 15.9A2.15 2.15 0 0 1 1.8 12A2.15 2.15 0 0 1 2.58 8.1A2.15 2.15 0 0 1 4.79 4.79A2.15 2.15 0 0 1 8.1 2.58A2.15 2.15 0 0 1 12 1.8Z"/><circle class="sl-i" cx="12" cy="12" r="6.6"/></svg>'   # the distinction's rosette, as js/jobs.js draws it
I18N_FR = (root / "js/i18n.js").read_text(encoding="utf-8").split("\n  en: {")[0]
def fr_str(k): return re.search(r'\b%s: "([^"]*)"' % k, I18N_FR).group(1)   # the pay wording has one home, the fr block of js/i18n.js
def past_two_months(a, b):   # as js/jobs.js: two calendar months, the day after the same date two months on
    d = datetime.date.fromisoformat(a); y, mo = divmod(d.month - 1 + 2, 12); y += d.year; mo += 1
    last = (datetime.date(y + mo // 12, mo % 12 + 1, 1) - datetime.timedelta(days=1)).day
    return datetime.date.fromisoformat(b) >= datetime.date(y, mo, min(d.day, last))
def allowance_due(o):   # as js/jobs.js: past two months, or 309 hours reachable (hours a week, else the form's maximum 48, per week begun)
    days = (datetime.date.fromisoformat(o["end"]) - datetime.date.fromisoformat(o["start"])).days + 1
    return days > 0 and (past_two_months(o["start"], o["end"]) or (int(o.get("hours") or 0) or 48) * -(-days // 7) > 308)
def pay_of(o): return "" if o.get("payHidden") else str(o.get("pay") or "").strip()
def pay_fact(o):   # the pay is always a fact: the restaurant's words, or "Non communiquée" and the legal floor where there is one
    if pay_of(o): return '<div class="fact"><dt data-t="colPay">Rémunération</dt><dd%s>%s</dd></div>' % (' data-t="oPay"' if (o.get("en") or {}).get("pay") else "", html.escape(pay_of(o)))
    nk = ("payNote_stage" if allowance_due(o) else "") if o["kind"] == "stage" else "payNote_" + o["kind"]
    note = fr_str(nk) if nk else ""
    return '<div class="fact%s"><dt data-t="colPay">Rémunération</dt><dd data-t="payUndisclosed">%s</dd>%s</div>' % (" noted" if note else "", html.escape(fr_str("payUndisclosed")), '<dd class="fact-note" data-t="%s">%s</dd>' % (nk, html.escape(note)) if note else "")
def rel(s): return s.replace('href="css/', 'href="../../css/').replace('src="js/', 'src="../../js/').replace('href="apple-touch-icon.png"', 'href="../../apple-touch-icon.png"').replace('href="./"', 'href="../../"').replace('href="publier/"', 'href="../../publier/"').replace('href="ecoles/"', 'href="../../ecoles/"').replace('href="contact/"', 'href="../../contact/"').replace('href="mentions-legales/"', 'href="../../mentions-legales/"').replace('href="./#offres"', 'href="../../#offres"').replace('href="questionnaire/"', 'href="../../questionnaire/"').replace('href="charte/"', 'href="../../charte/"')

odir = root / "o"
for old in odir.glob("*/"):
    if old.name not in {o["id"] for o in live}: shutil.rmtree(old)
for o in live:
    e = html.escape
    title = "%s — %s, %s · Nokime Jobs" % (o["role"], o["restaurant"], o["city"])
    desc = "%s : %s à %s, du %s au %s. %s%s" % (KIND[o["kind"]], o["role"], o["city"], fr_date(o["start"]), fr_date(o["end"]), pay_of(o) or "Rémunération non communiquée", ", logé" if o.get("housing") and "logé" not in pay_of(o) else "")
    shown = {"role": True, "restaurant": True, "pay": bool(pay_of(o)), "text": bool(o.get("text"))}   # as js/jobs.js: a field's English counts only where its French is shown
    tr = {k: v for k, v in (o.get("en") or {}).items() if k in shown and v and shown[k]}   # Nokime's translation: the English view reads it, the French stays the original
    en = {"oDates": "%s to %s" % (en_date(o["start"]), en_date(o["end"]))}
    if o.get("hours"): en["oHours"] = "%s h a week" % o["hours"]
    en.update({"o" + k[0].upper() + k[1:]: tr[k] for k in ("role", "restaurant", "pay", "text") if tr.get(k)})
    en = {("oRest" if k == "oRestaurant" else k): v for k, v in en.items()}
    t_ = lambda k: ' data-t="%s"' % k if k in en else ""
    facts = (('<div class="fact"><dt data-t="colMetier">Métier</dt><dd data-t="metier_%s">%s</dd></div>' % (o["metier"], METIER[o["metier"]]) if o.get("metier") in METIER else "") +   # absent or unknown: not shown
             '<div class="fact"><dt data-t="colDates">Dates</dt><dd data-t="oDates">%s</dd></div>' % html.escape("du %s au %s" % (fr_date(o["start"]), fr_date(o["end"]))) +
             ('<div class="fact"><dt data-t="colHours">Heures</dt><dd data-t="oHours">%s</dd></div>' % html.escape("%s h par semaine" % o["hours"]) if o.get("hours") else "") +
             pay_fact(o) + ('<div class="fact"><dt data-t="colHousing">Logement</dt><dd data-t="jobsHoused">Logé</dd></div>' if o.get("housing") is True else
                            '<div class="fact"><dt data-t="colHousing">Logement</dt><dd data-t="jobsNotHoused">Non logé</dd></div>' if o.get("housing") is False else ""))   # as js/jobs.js: absent, not shown
    orig = ('<div class="job-orig only-en" lang="fr"><p class="job-orig-label" lang="en" data-t="jobsOriginal">Original offer, in French</p><p><b>%s</b> · %s%s</p>%s</div>' %
            (html.escape(o["role"]), html.escape(o["restaurant"]), " · " + html.escape(pay_of(o)) if pay_of(o) else "", '<p>%s</p>' % html.escape(o["text"]) if tr.get("text") else "")) if tr else ""   # an untranslated text is already shown, in French
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
    h = h.replace(BASE + '">', BASE + "o/" + o["id"] + '/">').replace('<meta name="twitter:card"', '<script type="application/ld+json">%s</script>\n<meta name="twitter:card"' % json.dumps(ld, ensure_ascii=False).replace("<", "\\u003c"))
    h = re.sub(r'<body[^>]*>', '<body>', h)
    mail = "mailto:%s?subject=%s&body=%s" % (o["contact"]["email"], urllib.parse.quote("Candidature — %s — %s" % (o["role"], o["restaurant"])), urllib.parse.quote("Bonjour,\n\nJe vous écris pour le poste de %s à partir du %s.\n\n" % (o["role"], fr_date(o["start"]))))
    main = '''<main>
  <section class="page-hero offer-page">
    <div class="wrap">
      <p class="eyebrow">%s · %s</p>
      <h1 class="display"%s>%s</h1>
      <p class="lead"><span%s>%s</span>%s</p>
    </div>
  </section>

  <section class="pass">
    <div class="wrap">
      <div class="offer-card">
        <dl class="job-facts">%s</dl>
        %s%s
        <div class="cta-row">
          <a class="btn primary" href="%s"><span data-t="jobsApply">Écrire à l’établissement</span><span class="arrow" aria-hidden="true">→</span></a>
          %s
          <a class="btn" href="../../#offres"><span data-t="jobsAllOffers">Toutes les offres</span></a>
        </div>
      </div>
    </div>
  </section>
</main>

''' % ('<span data-t="kind_%s">%s</span>' % (o["kind"], e(KIND[o["kind"]])), e(o["city"]) + (" · " + e(DEPT[str(o["dept"])]) if str(o["dept"]) in DEPT else ""), t_("oRole"), e(o["role"]), t_("oRest"), e(o["restaurant"]),
       '<span class="distinction" role="img" aria-label="Distinction Nokime" title="Distinction Nokime">%s</span>' % SEAL if o.get("distinction") else '<span class="distinction empty" aria-hidden="true"></span>', facts,
       ('<p class="offer-text"%s>%s</p>' % (t_("oText"), e(o["text"]))) if o.get("text") else "", orig, e(mail),
       ('<a class="btn" href="tel:%s">%s</a>' % (e(re.sub(r"\s", "", o["contact"]["phone"])), e(o["contact"]["phone"]))) if o["contact"].get("phone") else "")
    own = '<script>Object.assign(NOKIME_I18N.en,%s);</script>\n' % json.dumps(en, ensure_ascii=False).replace("<", "\\u003c")
    page = rel(h + header + main + footer + scripts.replace("</script>\n", "</script>\n" + own, 1))
    (odir / o["id"]).mkdir(parents=True, exist_ok=True)
    (odir / o["id"] / "index.html").write_text(page, encoding="utf-8")

# ---------------------------------------------------------------- the questionnaire
sys.path.insert(0, str(root / "tools"))
from questionnaire_questions import QUESTIONS, MOIS, key as qkey
YEARS = 5   # the year selects offer next year and the five before it: answers are kept three years after the placement ends

NNBSP, NBSP = "\u202f", "\u00a0"
def typo(t):
    """French typography on plain text: a narrow no-break space before ; : ? ! and in 15 000 € or 3 %, no-break spaces inside « »."""
    t = re.sub(r"(?<=\S) ([;:?!])(?=\s|$)", NNBSP + r"\1", t)
    t = t.replace("« ", "«" + NBSP).replace(" »", NBSP + "»")
    t = re.sub(r"(\d) (\d{3})(?=\D|$)", r"\1" + NNBSP + r"\2", t)
    return re.sub(r"(\d) ([€%])", r"\1" + NNBSP + r"\2", t)
def between(text, name, block):
    """Replace what lies between <!-- name:start ... --> and <!-- name:end --> (or the same as /* */ comments)."""
    pat = re.compile(r"((?:<!--|/\*) %s:start[^\n]*\n)(.*?)(^[ \t]*(?:<!--|/\*) %s:end)" % (name, name), re.S | re.M)
    new, n = pat.subn(lambda m: m.group(1) + block + m.group(3), text)
    if n != 1: sys.exit("questionnaire: marker %s not found exactly once; nothing more was written" % name)
    return new
e = lambda s: html.escape(typo(s), quote=False)
year = datetime.date.today().year
def month_select(name):
    return ('<select name="%sMonth" required aria-label="Mois" data-t-attr="aria-label:qMonth"><option value="" data-t="qMonth">Mois</option>' % name +
            "".join('<option value="%02d" data-t="rMois%d">%s</option>' % (i + 1, i + 1, e(m[0])) for i, m in enumerate(MOIS)) + "</select>")
def year_select(name):
    return ('<select name="%sYear" required aria-label="Année" data-t-attr="aria-label:qYear"><option value="" data-t="qYear">Année</option>' % name +
            "".join("<option>%d</option>" % y for y in range(year + 1, year - YEARS, -1)) + "</select>")
periode = ('            <div class="f-period">\n'
           '              <fieldset><legend data-t="fStart">Début</legend>%s%s</fieldset>\n'
           '              <fieldset><legend data-t="fEnd">Fin</legend>%s%s</fieldset>\n'
           '            </div>\n') % (month_select("start"), year_select("start"), month_select("end"), year_select("end"))
qs = ['            <div class="f-qs">\n']
for q in QUESTIONS:
    typ = "radio" if q["kind"] == "radio" else "checkbox"
    extra = ' <span class="muted" data-t="qSeveral">(plusieurs réponses possibles)</span>' if q["kind"] == "check" else ""
    qs.append('              <fieldset class="f-q" data-q="%s"><legend><span data-t="%s">%s</span>%s</legend>\n                <div class="chips wrap-ok">' % (q["id"], qkey(q["id"]), e(q["fr"]), extra) +
              "".join('<label class="chip"><input type="%s" name="q_%s" value="%s"%s><span data-t="%s">%s</span></label>' % (typ, q["id"], o[0], " required" if typ == "radio" else "", qkey(q["id"], o[0]), e(o[1])) for o in q["options"]) +
              "</div></fieldset>\n")
qs.append("            </div>\n")
page = root / "questionnaire" / "index.html"
i18n = root / "js" / "i18n.js"
en_lines = ["    %s: %s, " % (qkey(q["id"]), json.dumps(q["en"], ensure_ascii=False)) + " ".join("%s: %s," % (qkey(q["id"], o[0]), json.dumps(o[2], ensure_ascii=False)) for o in q["options"]) for q in QUESTIONS]
en_lines.append("    " + " ".join("rMois%d: %s," % (i + 1, json.dumps(m[1])) for i, m in enumerate(MOIS)))
p_new = between(between(page.read_text(encoding="utf-8"), "periode", periode), "questions", "".join(qs))
i_new = between(i18n.read_text(encoding="utf-8"), "questions", "\n".join(en_lines) + "\n")
page.write_text(p_new, encoding="utf-8"); i18n.write_text(i_new, encoding="utf-8")
print("questionnaire: %d questions written" % len(QUESTIONS))

urls = [BASE, BASE + "publier/", BASE + "ecoles/", BASE + "questionnaire/", BASE + "contact/", BASE + "charte/"] + [BASE + "o/%s/" % o["id"] for o in live]
(root / "sitemap.xml").write_text('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' + "".join("  <url><loc>%s</loc></url>\n" % u for u in urls) + "</urlset>\n", encoding="utf-8")
print("offers: %d live, %d pages written, sitemap %d urls" % (len(live), len(live), len(urls)))
