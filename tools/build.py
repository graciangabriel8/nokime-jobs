#!/usr/bin/env python3
"""Offer pages and the sitemap, from js/offers.js.

Each real offer becomes o/<id>/index.html: a page a student can share and a search
engine can read, with schema.org JobPosting data so job search features can list it.
Demo offers never get a page. Expired offers get none either, and their old page is
removed. The sitemap lists the site's pages and the live offers, each with the date of the
last commit of its page (today while the page has uncommitted changes).

It writes the structured data of hirondelle/index.html (WebPage, BreadcrumbList, FAQPage)
from that page's own title, description and questions, between its ld markers.
It does the same for each advice article, conseils/<slug>/index.html (Article, BreadcrumbList), and lists them in the sitemap.

It also writes the questionnaire from tools/questionnaire_questions.py: the period selects and
the question fieldsets of questionnaire/index.html, and the English question strings in
js/i18n.js, each between its start/end markers. Nothing about any answer is read or written here.

    python3 tools/build.py
"""
import json, re, sys, pathlib, datetime, shutil, html, subprocess, urllib.parse
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

for o in live:
    if not re.fullmatch(r"[a-z0-9-]{1,64}", str(o.get("id", ""))): sys.exit("js/offers.js : l’identifiant « %s » ne doit contenir que des minuscules sans accent, des chiffres et des tirets (64 au plus), sinon ses clics sur « Postuler » ne sont pas comptés." % o.get("id", "?"))
    if not isinstance(o.get("housing"), bool): sys.exit("js/offers.js : l’offre « %s » doit dire housing: true ou false." % o.get("id", "?"))
    if "hirondelle" in o and o["hirondelle"] is not None and not (type(o["hirondelle"]) is int and 2026 <= o["hirondelle"] <= datetime.date.today().year):
        sys.exit("js/offers.js : l’offre « %s » : hirondelle doit être l’année de la décision (2026 au plus tôt, jamais une année à venir), ou absent." % o.get("id", "?"))
    # job search needs the date the offer first went online: without one, every rebuild would re-date it
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", str(o.get("published") or "")): sys.exit("js/offers.js : l’offre « %s » doit dire published: \"AAAA-MM-JJ\", le jour où elle a été mise en ligne." % o.get("id", "?"))
    if "postalCode" in o and not (re.fullmatch(r"\d{5}", str(o["postalCode"])) and str(o["postalCode"]).startswith("20" if str(o.get("dept")) in ("2A", "2B") else str(o.get("dept")))):
        sys.exit("js/offers.js : l’offre « %s » : postalCode doit être un code postal à cinq chiffres de son département (dept)." % o.get("id", "?"))
    if "website" in o and not re.fullmatch(r"https?://\S+", str(o["website"])): sys.exit("js/offers.js : l’offre « %s » : website doit être une adresse en http(s)://." % o.get("id", "?"))
    if "salary" in o:   # the structured pay: only numbers the offer's visible pay text already gives
        s, pay = o["salary"], ("" if o.get("payHidden") else re.sub(r"[\s  .]", "", str(o.get("pay") or "")))
        nums = [s.get(k) for k in ("value", "min", "max") if isinstance(s, dict) and k in s]
        if not (isinstance(s, dict) and s.get("unit") in ("HOUR", "DAY", "WEEK", "MONTH", "YEAR") and nums and all(type(n) in (int, float) and n > 0 for n in nums)
                and (("value" in s) != ("min" in s or "max" in s)) and s.get("min", 0) <= s.get("max", float("inf"))):
            sys.exit("js/offers.js : l’offre « %s » : salary doit être {\"value\": n, \"unit\": …} ou {\"min\": n, \"max\": n, \"unit\": …}, unit parmi HOUR, DAY, WEEK, MONTH, YEAR." % o.get("id", "?"))
        if not all(str(int(n)) in pay for n in nums): sys.exit("js/offers.js : l’offre « %s » : salary donne un montant que son texte pay (affiché) ne dit pas ; mettez le même chiffre dans les deux, ou retirez salary." % o.get("id", "?"))
seen_hd = {}   # l’hirondelle belongs to the establishment, not to one offer: every live offer of one establishment carries the same value
for o in live:
    k = re.sub(r"\s+", " ", str(o.get("restaurant", ""))).strip().lower() + " | " + str(o.get("city", "")).strip().lower()
    if k in seen_hd and seen_hd[k][1] != o.get("hirondelle"):
        sys.exit("js/offers.js : les offres « %s » et « %s » viennent du même établissement mais ne disent pas la même hirondelle : mettez la même année sur toutes ses offres, ou retirez-la de toutes." % (seen_hd[k][0], o.get("id", "?")))
    seen_hd.setdefault(k, (o.get("id", "?"), o.get("hirondelle")))

home = (root / "index.html").read_text(encoding="utf-8")
body_end = re.search(r"<body[^>]*>", home).end()   # the home page's door sits between <body> and the masthead: an offer page gets neither the door nor its wall
head = home[:body_end] + "\n\n"; header = home[home.index('<header class="site-head">'):home.index("</header>") + len("</header>")] + "\n\n"
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
SWALLOW = '<svg viewBox="0 0 100 100" aria-hidden="true" focusable="false"><path fill="currentColor" d="M99 22 L88 28 C87 21 80 18 75 23 C73 26 70 30 66 32 C56 20 34 10 8 12 C26 22 38 34 48 50 C38 54 20 62 4 74 C14 72 24 70 32 70 C30 80 28 90 26 98 C36 88 46 78 52 66 C58 80 64 90 74 98 C70 84 68 70 68 58 C80 52 92 40 99 22 Z"/></svg>'   # l’hirondelle de Nokime Jobs, as js/jobs.js draws it
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
def rel(s): return s.replace('href="css/', 'href="../../css/').replace('href="fonts/', 'href="../../fonts/').replace('src="js/', 'src="../../js/').replace('href="apple-touch-icon.png"', 'href="../../apple-touch-icon.png"').replace('href="./"', 'href="../../"').replace('href="publier/"', 'href="../../publier/"').replace('href="ecoles/"', 'href="../../ecoles/"').replace('href="contact/"', 'href="../../contact/"').replace('href="mentions-legales/"', 'href="../../mentions-legales/"').replace('href="./#offres"', 'href="../../#offres"').replace('href="questionnaire/"', 'href="../../questionnaire/"').replace('href="charte/"', 'href="../../charte/"').replace('href="hirondelle/"', 'href="../../hirondelle/"')

odir = root / "o"
for old in odir.glob("*/"):
    if old.name not in {o["id"] for o in live}: shutil.rmtree(old)
for o in live:
    e = html.escape
    title = "%s — %s, %s · Nokime Jobs" % (o["role"] if o["role"].lower().startswith(KIND[o["kind"]].lower()) else KIND[o["kind"]] + "\u202f: " + o["role"], o["restaurant"], o["city"])   # the kind first (« stage », « saison » are what people search), once
    desc = "%s : %s à %s, du %s au %s. %s%s" % (KIND[o["kind"]], o["role"], o["city"], fr_date(o["start"]), fr_date(o["end"]), pay_of(o) or "Rémunération non communiquée", ", logé" if o.get("housing") and "logé" not in pay_of(o) else "") + ((". " + fr_str("hdLabel").replace("{y}", str(o["hirondelle"]))) if o.get("hirondelle") else "")
    shown = {"role": True, "restaurant": True, "pay": bool(pay_of(o)), "text": bool(o.get("text"))}   # as js/jobs.js: a field's English counts only where its French is shown
    tr = {k: v for k, v in (o.get("en") or {}).items() if k in shown and v and shown[k]}   # Nokime's translation: the English view reads it, the French stays the original
    en = {"oDates": "%s to %s" % (en_date(o["start"]), en_date(o["end"]))}
    if o.get("hours"): en["oHours"] = "%s h a week" % o["hours"]
    if o.get("hirondelle"): en["oHd"] = "%d" % o["hirondelle"]; en["oHdLabel"] = "Hirondelle since %d" % o["hirondelle"]
    en.update({"o" + k[0].upper() + k[1:]: tr[k] for k in ("role", "restaurant", "pay", "text") if tr.get(k)})
    en = {("oRest" if k == "oRestaurant" else k): v for k, v in en.items()}
    t_ = lambda k: ' data-t="%s"' % k if k in en else ""
    place = ", ".join(x for x in (str(o.get("street") or "").strip(), (str(o.get("postalCode") or "") + " " + o["city"]).strip() if o.get("postalCode") else "") if x)
    facts = (('<div class="fact"><dt data-t="colMetier">Métier</dt><dd data-t="metier_%s">%s</dd></div>' % (o["metier"], METIER[o["metier"]]) if o.get("metier") in METIER else "") +   # absent or unknown: not shown
             '<div class="fact"><dt data-t="colDates">Dates</dt><dd data-t="oDates">%s</dd></div>' % html.escape("du %s au %s" % (fr_date(o["start"]), fr_date(o["end"]))) +
             ('<div class="fact"><dt data-t="colHours">Heures</dt><dd data-t="oHours">%s</dd></div>' % html.escape("%s h par semaine" % o["hours"]) if o.get("hours") else "") +
             pay_fact(o) + ('<div class="fact"><dt data-t="colHousing">Logement</dt><dd data-t="jobsHoused">Logé</dd></div>' if o.get("housing") is True else
                            '<div class="fact"><dt data-t="colHousing">Logement</dt><dd data-t="jobsNotHoused">Non logé</dd></div>' if o.get("housing") is False else "") +
             ('<div class="fact"><dt data-t="colMinors">Mineurs</dt><dd data-t="jobsMinorsOk">Acceptés</dd></div>' if o["kind"] == "stage" and o.get("minors") is True else
              '<div class="fact"><dt data-t="colMinors">Mineurs</dt><dd data-t="jobsMinorsNo">18 ans et plus</dd></div>' if o["kind"] == "stage" and o.get("minors") is False else "") +   # as js/jobs.js: absent, not shown
             ('<div class="fact"><dt data-t="colAddress">Adresse</dt><dd>%s</dd></div>' % html.escape(place) if place else ""))   # street and postcode, when given: the address job search reads is the one on the page
    orig = ('<div class="job-orig only-en" lang="fr"><p class="job-orig-label" lang="en" data-t="jobsOriginal">Original offer, in French</p><p><b>%s</b> · %s%s</p>%s</div>' %
            (html.escape(o["role"]), html.escape(o["restaurant"]), " · " + html.escape(pay_of(o)) if pay_of(o) else "", '<p>%s</p>' % html.escape(o["text"]) if tr.get("text") else "")) if tr else ""   # an untranslated text is already shown, in French
    # the job description job search reads: the offer's own text, then every fact of the page, word for word
    items = ["<li>%s\u202f: %s</li>" % (dt, ". ".join(dd)) for dt, dd in ((m.group(1), re.findall(r"<dd[^>]*>(.*?)</dd>", m.group(2))) for m in re.finditer(r'<div class="fact[^"]*"><dt[^>]*>(.*?)</dt>(.*?)</div>', facts))]
    jdesc = ("<p>%s</p>" % e(o["text"]) if o.get("text") else "") + "<ul>%s</ul>" % "".join(items)
    hours = int(o.get("hours") or 0)
    emp = [EMP[o["kind"]]] if not hours else ["FULL_TIME" if hours >= 35 else "PART_TIME"] if o["kind"] == "alternance" else [EMP[o["kind"]], "FULL_TIME" if hours >= 35 else "PART_TIME"]
    addr = {"@type": "PostalAddress", "streetAddress": str(o.get("street") or "").strip(), "addressLocality": o["city"], "postalCode": str(o.get("postalCode") or ""), "addressRegion": DEPT.get(str(o["dept"]), ""), "addressCountry": "FR"}
    org = {"@type": "Organization", "name": o["restaurant"]}   # never an award here: the hirondelle's markup waits for the lawyer's answer (see js/offers.js)
    if o.get("website"): org["sameAs"] = o["website"]
    ld = {"@context": "https://schema.org", "@type": "JobPosting", "title": o["role"], "description": jdesc, "datePosted": o["published"],
          "validThrough": (o.get("expires") or o["end"]) + "T23:59:59", "employmentType": emp if len(emp) > 1 else emp[0],
          "hiringOrganization": org,
          "jobLocation": {"@type": "Place", "address": {k: v for k, v in addr.items() if v}},
          "identifier": {"@type": "PropertyValue", "name": "Nokime Jobs", "value": o["id"]},
          "jobStartDate": o["start"], "directApply": True}
    if o.get("salary") and pay_of(o):   # only the numbers the visible pay gives (checked above)
        sal = o["salary"]; ld["baseSalary"] = {"@type": "MonetaryAmount", "currency": "EUR", "value": dict({"@type": "QuantitativeValue", "unitText": sal["unit"]}, **{k + ("Value" if k != "value" else ""): sal[k] for k in ("value", "min", "max") if k in sal})}
    h = head
    h = re.sub(r"<title>[^<]*</title>", "<title>%s</title>" % e(title), h)
    h = re.sub(r'<meta name="description" content="[^"]*">', '<meta name="description" content="%s">' % e(desc), h)
    h = re.sub(r'<meta property="og:title" content="[^"]*">', '<meta property="og:title" content="%s">' % e(title), h)
    h = re.sub(r'<meta property="og:description" content="[^"]*">', '<meta property="og:description" content="%s">' % e(desc), h)
    h = h.replace(BASE + '">', BASE + "o/" + o["id"] + '/">').replace('<meta name="twitter:card"', '<script type="application/ld+json">%s</script>\n<meta name="twitter:card"' % json.dumps(ld, ensure_ascii=False).replace("<", "\\u003c"))
    h = re.sub(r'<body[^>]*>', '<body>', h)
    mail = "mailto:%s?subject=%s&body=%s" % (o["contact"]["email"], urllib.parse.quote("Candidature via Nokime Jobs — %s — %s" % (o["role"], o["restaurant"])), urllib.parse.quote("Bonjour,\n\nJe vous écris pour le poste de %s à partir du %s.\n\n" % (o["role"], fr_date(o["start"]))))
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
      <div class="offer-card%s">
        <dl class="job-facts">%s</dl>
        %s%s
        <div class="cta-row">
          <a class="btn primary" data-offer="%s" href="%s"><span data-t="jobsApply">Postuler</span><span class="arrow" aria-hidden="true">→</span></a>
          %s
          <a class="btn" href="../../#offres"><span data-t="jobsAllOffers">Toutes les offres</span></a>
        </div>
      </div>
    </div>
  </section>
</main>

''' % ('<span data-t="kind_%s">%s</span>' % (o["kind"], e(KIND[o["kind"]])), e(o["city"]) + (" · " + e(DEPT[str(o["dept"])]) if str(o["dept"]) in DEPT else ""), t_("oRole"), e(o["role"]), t_("oRest"), e(o["restaurant"]),
       ('<a class="hd" href="/hirondelle/" title="%s" aria-label="%s" data-t-attr="title:hdTitle,aria-label:oHdLabel">%s<span class="hd-since" data-t="oHd" aria-hidden="true">%s</span></a>' % (e(fr_str("hdTitle")), e(fr_str("hdLabel").replace("{y}", str(o["hirondelle"]))), SWALLOW, e(fr_str("hdSince").replace("{y}", str(o["hirondelle"]))))) if o.get("hirondelle") else "", (' has-hd" data-hd-key="%s' % e(o["restaurant"] + "|" + o["city"])) if o.get("hirondelle") else "", facts,
       ('<p class="offer-text"%s>%s</p>' % (t_("oText"), e(o["text"]))) if o.get("text") else "", orig, e(o["id"]), e(mail),
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

# ---------------------------------------------------------------- the hirondelle's structured data
# Built from the page itself, so what search engines read is word for word what the page shows: its title, its
# description, and each question of its « Questions » with the text paragraphs of its answer. Never an award.
hp = root / "hirondelle" / "index.html"; hs = hp.read_text(encoding="utf-8")
plain = lambda x: html.unescape(re.sub(r"<[^>]+>", "", x)).strip()
faq = re.search(r'<div class="facts-grid hp-faq">(.*?)\n      </div>', hs, re.S)
qa = [(plain(q), " ".join(plain(a) for a in re.findall(r'<p data-t="[^"]+">(.*?)</p>', body)))
      for q, body in re.findall(r'<details class="more"><summary data-t="[^"]+">(.*?)</summary>(.*?)</details>', faq.group(1) if faq else "")]
if not qa or not all(q and a for q, a in qa): sys.exit("hirondelle/index.html : questions introuvables (div.hp-faq) ; rien n’a été écrit pour elle")
hurl = BASE + "hirondelle/"
hld = {"@context": "https://schema.org", "@graph": [
    {"@type": ["WebPage", "FAQPage"], "@id": hurl + "#page", "url": hurl, "name": plain(re.search(r"<title>(.*?)</title>", hs).group(1)),
     "description": html.unescape(re.search(r'<meta name="description" content="([^"]*)">', hs).group(1)), "inLanguage": "fr",
     "isPartOf": {"@type": "WebSite", "@id": BASE + "#site", "name": "Nokime Jobs", "url": BASE},
     "primaryImageOfPage": {"@type": "ImageObject", "url": BASE + "og-hirondelle.png", "width": 1200, "height": 630},
     "breadcrumb": {"@id": hurl + "#breadcrumb"},
     "mainEntity": [{"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in qa]},
    {"@type": "BreadcrumbList", "@id": hurl + "#breadcrumb", "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "Nokime Jobs", "item": BASE},
        {"@type": "ListItem", "position": 2, "name": plain(re.search(r'data-t="navHirondelle">(.*?)</a>', hs).group(1)), "item": hurl}]}]}
hp.write_text(between(hs, "ld", '<script type="application/ld+json">%s</script>\n' % json.dumps(hld, ensure_ascii=False).replace("<", "\\u003c")), encoding="utf-8")
print("hirondelle: structured data with %d questions" % len(qa))

# ---------------------------------------------------------------- the advice articles' structured data
# Each conseils/<slug>/index.html gets an Article and its BreadcrumbList, from the page's own title, description and heading.
articles = sorted(p.parent.name for p in (root / "conseils").glob("*/index.html"))
for slug in articles:
    ap = root / "conseils" / slug / "index.html"; s = ap.read_text(encoding="utf-8"); aurl = BASE + "conseils/%s/" % slug
    head1 = plain(re.search(r"<h1[^>]*>(.*?)</h1>", s, re.S).group(1))
    nokime = {"@type": "Organization", "name": "Nokime Jobs", "url": BASE}
    ald = {"@context": "https://schema.org", "@graph": [
        {"@type": "Article", "@id": aurl + "#article", "url": aurl, "headline": head1, "name": plain(re.search(r"<title>(.*?)</title>", s).group(1)),
         "description": html.unescape(re.search(r'<meta name="description" content="([^"]*)">', s).group(1)), "inLanguage": "fr",
         "image": BASE + "og.png", "author": nokime, "publisher": nokime,
         "isPartOf": {"@type": "WebSite", "@id": BASE + "#site", "name": "Nokime Jobs", "url": BASE},
         "mainEntityOfPage": {"@type": "WebPage", "@id": aurl, "breadcrumb": {"@id": aurl + "#breadcrumb"}}},
        {"@type": "BreadcrumbList", "@id": aurl + "#breadcrumb", "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Nokime Jobs", "item": BASE},
            {"@type": "ListItem", "position": 2, "name": head1, "item": aurl}]}]}
    ap.write_text(between(s, "ld", '<script type="application/ld+json">%s</script>\n' % json.dumps(ald, ensure_ascii=False).replace("<", "\\u003c")), encoding="utf-8")
print("articles: structured data on %d" % len(articles))

# ---------------------------------------------------------------- the sitemap
urls = [BASE, BASE + "publier/", BASE + "ecoles/", BASE + "questionnaire/", BASE + "contact/", BASE + "charte/", BASE + "hirondelle/"] + [BASE + "conseils/%s/" % a for a in articles] + [BASE + "o/%s/" % o["id"] for o in live]
def git(*a):
    r = subprocess.run(["git", "-C", str(root), *a], capture_output=True, text=True)
    return r.stdout.strip() if r.returncode == 0 else None
in_git = git("rev-parse", "--show-toplevel") == str(root)   # tools/check.py builds on a copy without .git: there, keep the dates already written
before = dict(re.findall(r"<loc>([^<]+)</loc><lastmod>([^<]+)</lastmod>", (root / "sitemap.xml").read_text(encoding="utf-8"))) if (root / "sitemap.xml").is_file() else {}
def lastmod(u):   # the last commit of the page's file, or today while it has uncommitted changes
    if not in_git: return before.get(u, "")
    f = u[len(BASE):] + "index.html"
    return today if git("status", "--porcelain", "--", f) else (git("log", "-1", "--format=%cs", "--", f) or today)
(root / "sitemap.xml").write_text('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' + "".join("  <url><loc>%s</loc>%s</url>\n" % (u, "<lastmod>%s</lastmod>" % d if d else "") for u, d in ((u, lastmod(u)) for u in urls)) + "</urlset>\n", encoding="utf-8")
print("offers: %d live, %d pages written, sitemap %d urls" % (len(live), len(live), len(urls)))
