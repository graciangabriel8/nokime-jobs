/* Nokime Jobs — list, filters, applying, and the posting form. No backend: applying is a mail
   to the restaurant, posting is a mail to Nokime. Language follows site.js (localStorage). */
(function () {
  "use strict";
  var I18N = window.NOKIME_I18N || { fr: {}, en: {} };
  var ADDRESS = "contact@copius.fr";
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function lang() { try { var v = localStorage.getItem("nokime-lang"); if (v === "fr" || v === "en") return v; } catch (e) {} return (navigator.language || "fr").toLowerCase().indexOf("fr") === 0 ? "fr" : "en"; }
  function t(k, vars) { var L = lang(), s = (I18N[L] && I18N[L][k]) != null ? I18N[L][k] : (I18N.fr[k] != null ? I18N.fr[k] : k); return vars ? String(s).replace(/\{(\w+)\}/g, function (_, v) { return vars[v] != null ? vars[v] : ""; }) : s; }

  /* départements → régions, so an offer only carries its département */
  var REGIONS = {
    "Auvergne-Rhône-Alpes": "01 03 07 15 26 38 42 43 63 69 73 74", "Bourgogne-Franche-Comté": "21 25 39 58 70 71 89 90",
    "Bretagne": "22 29 35 56", "Centre-Val de Loire": "18 28 36 37 41 45", "Corse": "2A 2B 20", "Grand Est": "08 10 51 52 54 55 57 67 68 88",
    "Hauts-de-France": "02 59 60 62 80", "Île-de-France": "75 77 78 91 92 93 94 95", "Normandie": "14 27 50 61 76",
    "Nouvelle-Aquitaine": "16 17 19 23 24 33 40 47 64 79 86 87", "Occitanie": "09 11 12 30 31 32 34 46 48 65 66 81 82",
    "Pays de la Loire": "44 49 53 72 85", "Provence-Alpes-Côte d’Azur": "04 05 06 13 83 84", "Outre-mer": "971 972 973 974 976"
  };
  var DEPT_REGION = {}; Object.keys(REGIONS).forEach(function (r) { REGIONS[r].split(" ").forEach(function (d) { DEPT_REGION[d] = r; }); });
  function regionOf(o) { return DEPT_REGION[String(o.dept)] || ""; }

  var demo = /[?&]demo=1/.test(location.search);
  var today = new Date().toISOString().slice(0, 10);
  var OFFERS = (window.NOKIME_JOBS || []).concat(demo ? (window.NOKIME_JOBS_DEMO || []) : [])
    .filter(function (o) { var x = o.expires || o.end; return /^\d{4}-\d{2}-\d{2}$/.test(String(x)) && x >= today; })   /* undated or malformed: not shown */
    .sort(function (a, b) { return (b.published || "").localeCompare(a.published || ""); });

  var state = { kind: "all", region: "all", housing: false };

  function fmtDate(iso) {
    if (!iso) return "";
    var d = new Date(iso + "T12:00:00");
    return d.toLocaleDateString(lang() === "fr" ? "fr-FR" : "en-GB", { day: "numeric", month: "short", year: d.getFullYear() !== new Date().getFullYear() ? "numeric" : undefined });
  }
  /* the distinction, once earned: a blue rosette (the same drawing as tools/build.py and the home page) */
  var SEAL = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="sl-o" d="M12 1.8A2.15 2.15 0 0 1 15.9 2.58A2.15 2.15 0 0 1 19.21 4.79A2.15 2.15 0 0 1 21.42 8.1A2.15 2.15 0 0 1 22.2 12A2.15 2.15 0 0 1 21.42 15.9A2.15 2.15 0 0 1 19.21 19.21A2.15 2.15 0 0 1 15.9 21.42A2.15 2.15 0 0 1 12 22.2A2.15 2.15 0 0 1 8.1 21.42A2.15 2.15 0 0 1 4.79 19.21A2.15 2.15 0 0 1 2.58 15.9A2.15 2.15 0 0 1 1.8 12A2.15 2.15 0 0 1 2.58 8.1A2.15 2.15 0 0 1 4.79 4.79A2.15 2.15 0 0 1 8.1 2.58A2.15 2.15 0 0 1 12 1.8Z"/><circle class="sl-i" cx="12" cy="12" r="6.6"/></svg>';
  /* the facts a candidate decides on: a small label above each value */
  function fact(cls, label, value) { return '<div class="fact ' + cls + '"><dt>' + esc(label) + "</dt><dd>" + value + "</dd></div>"; }
  function facts(o) {
    return '<dl class="job-facts">' + fact("dates", t("colDates"), esc(t("jobsDates", { a: fmtDate(o.start), b: fmtDate(o.end) }))) +
      (o.hours ? fact("hours", t("colHours"), esc(t("jobsHours", { h: o.hours }))) : "") +
      (o.pay ? fact("pay", t("colPay"), esc(o.pay)) : "") +
      (o.housing ? fact("housing", t("colHousing"), esc(t("jobsHoused"))) : "") + "</dl>";
  }
  function applyMail(o) {
    var subj = t("applySubject", { role: o.role, restaurant: o.restaurant });
    var body = t("applyBody", { role: o.role, restaurant: o.restaurant, start: fmtDate(o.start) });
    var c = o.contact || {};
    return "mailto:" + esc(c.email || "") + "?subject=" + encodeURIComponent(subj) + "&body=" + encodeURIComponent(body);
  }

  function renderList() {
    var list = $("#jobsList"); if (!list) return;
    var rows = OFFERS.filter(function (o) {
      return (state.kind === "all" || o.kind === state.kind) && (state.region === "all" || regionOf(o) === state.region) && (!state.housing || o.housing);
    });
    var count = $("#jobsCount"); if (count) count.textContent = OFFERS.length ? t(rows.length === 1 ? "jobsCount1" : "jobsCountN", { n: rows.length }) : "";
    if (!rows.length) {   /* a plain card: the message and the way to post an offer */
      list.innerHTML = '<div class="jobs-empty"><p>' + esc(OFFERS.length ? t("jobsNoneFiltered") : t("jobsEmpty")) + '</p><a class="btn primary" href="' + esc(list.getAttribute("data-post-href") || "../publier/") + '"><span>' + esc(t("jobsPost")) + '</span><span class="arrow" aria-hidden="true">→</span></a></div>';
      return;
    }
    var base = list.getAttribute("data-offer-base");
    /* one card per offer, read top to bottom: the top line (kind, place, date), the role, the restaurant,
       the facts, the folded text, and the one action; from 960px the CSS sets them in three columns */
    list.innerHTML = rows.map(function (o) {
      return '<article class="job' + (o.demo ? " demo" : "") + '" id="' + esc(o.id) + '"><div class="job-head">' +
        '<div class="job-top"><span class="tag ' + esc(o.kind) + '">' + esc(t("kind_" + o.kind)) + (o.demo ? " · " + esc(t("jobsDemoTag")) : "") + "</span>" +
          '<p class="job-where">' + esc(o.city) + (regionOf(o) ? '<span class="muted"> · ' + esc(regionOf(o)) + "</span>" : "") + "</p>" +
          (o.published ? '<p class="job-pub">' + esc(t("jobsPublished", { d: fmtDate(o.published) })) + "</p>" : "") + "</div>" +
        "<h3>" + (o.demo || !base ? esc(o.role) : '<a class="job-link" href="' + esc(base + o.id + "/") + '">' + esc(o.role) + "</a>") + "</h3>" +
        '<p class="job-rest">' + esc(o.restaurant) + (o.distinction ? '<span class="distinction" role="img" aria-label="' + esc(t("distinctionTitle")) + '" title="' + esc(t("distinctionTitle")) + '">' + SEAL + "</span>" : '<span class="distinction empty" aria-hidden="true"></span>') + "</p></div>" +
        facts(o) +
        (o.text ? '<details class="more"><summary>' + esc(t("more")) + "</summary><p>" + esc(o.text) + "</p></details>" : "") +
        '<div class="job-act"><a class="btn primary" href="' + applyMail(o) + '"><span>' + esc(t("jobsApply")) + '</span><span class="arrow" aria-hidden="true">→</span></a>' +
          ((o.contact && o.contact.phone) ? '<a class="link" href="tel:' + esc(o.contact.phone.replace(/\s/g, "")) + '">' + esc(o.contact.phone) + "</a>" : "") + "</div></article>";
    }).join("");
  }

  function renderFilters() {
    var sel = $("#jobsRegion"); if (!sel) return;
    var regs = {}; OFFERS.forEach(function (o) { var r = regionOf(o); if (r) regs[r] = 1; });
    sel.innerHTML = '<option value="all">' + esc(t("jobsAllRegions")) + "</option>" + Object.keys(regs).sort().map(function (r) { return '<option value="' + esc(r) + '"' + (state.region === r ? " selected" : "") + ">" + esc(r) + "</option>"; }).join("");
    $$("[data-kind]").forEach(function (b) { b.setAttribute("aria-pressed", b.getAttribute("data-kind") === state.kind ? "true" : "false"); });
    var h = $("#jobsHousing"); if (h) h.setAttribute("aria-pressed", state.housing ? "true" : "false");
  }
  $$("[data-kind]").forEach(function (b) { b.addEventListener("click", function () { state.kind = b.getAttribute("data-kind"); renderFilters(); renderList(); }); });
  var hb = $("#jobsHousing"); if (hb) hb.addEventListener("click", function () { state.housing = !state.housing; renderFilters(); renderList(); });
  var rs = $("#jobsRegion"); if (rs) rs.addEventListener("change", function () { state.region = rs.value; renderList(); });

  /* ---------- the posting form: a mail to Nokime, every field in the body ---------- */
  var form = $("#postForm");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!form.checkValidity()) { form.reportValidity(); return; }
      var f = function (n) { var el = form.elements[n]; return el ? (el.type === "checkbox" ? (el.checked ? "oui" : "non") : String(el.value || "").trim()) : ""; };
      var kind = (form.querySelector("[name=kind]:checked") || {}).value || "";
      var lines = [
        ["Type", { stage: "Stage", alternance: "Alternance", saison: "Saison" }[kind] || kind], ["Restaurant", f("restaurant")], ["Ville", f("city")], ["Département", f("dept")],
        ["Poste", f("role")], ["Début", f("start")], ["Fin", f("end")], ["Heures par semaine", f("hours")], ["Rémunération", f("pay")], ["Logement", f("housing")],
        ["Contact", f("contactName")], ["Email", f("email")], ["Téléphone", f("phone")], ["", ""], ["Description", f("text")]
      ];
      var body = lines.map(function (l) { return l[0] ? l[0] + " : " + l[1] : ""; }).join("\n") + "\n\nEnvoyé depuis Nokime Jobs";
      location.href = "mailto:" + ADDRESS + "?subject=" + encodeURIComponent("Nokime Jobs — offre : " + f("restaurant")) + "&body=" + encodeURIComponent(body);
      var ok = $("#postSent"); if (ok) ok.hidden = false;
    });
    var kindInputs = $$("[name=kind]", form), pay = form.elements.pay;
    kindInputs.forEach(function (r) { r.addEventListener("change", function () { if (pay && !pay.value.trim() && r.value === "stage") pay.value = I18N.fr.payLegal; /* offers are French, whatever the reader's language */ }); });
    var txt = form.elements.text, cnt = $("#textCount");
    if (txt && cnt) { var upd = function () { cnt.textContent = txt.value.length + " / 300"; }; txt.addEventListener("input", upd); upd(); }
  }

  function renderAll() { renderFilters(); renderList(); }
  renderAll();
  var lb = $("#langBtn"); if (lb) lb.addEventListener("click", function () { setTimeout(renderAll, 0); });
})();
