/* Nokime — site script. No build step, no dependencies.
   Language: French is read out of the page itself at load; English comes from i18n.js.
   Light only: there is no theme code. */
(function () {
  "use strict";

  var LS_LANG = "nokime-lang";
  var ADDRESS = "contact@nokime.fr";
  var I18N = window.NOKIME_I18N || { fr: {}, en: {} };

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function fill(s, vars) { return s.replace(/\{(\w+)\}/g, function (_, k) { return vars[k] !== undefined ? vars[k] : ""; }); }

  /* ---------- language ---------- */
  var lang = (function () {
    // ?lang=en or ?lang=fr in a shared link wins, and is remembered so the next page keeps it
    var q = /[?&]lang=(fr|en)(?:&|$)/.exec(location.search);
    // then it leaves the address bar, or a reload would undo a later click on the FR/EN button
    if (q) {
      try { localStorage.setItem(LS_LANG, q[1]); } catch (e) {}
      try { history.replaceState(history.state, "", location.pathname + location.search.replace(/([?&])lang=(?:fr|en)(&|$)/, function (m, a, b) { return b ? a : ""; }) + location.hash); } catch (e) {}
      return q[1];
    }
    try { var v = localStorage.getItem(LS_LANG); if (v === "fr" || v === "en") return v; } catch (e) {}
    // then French, whatever the browser's language: the page is French, and search engines read it with an
    // English browser, so guessing from navigator.language had Google index the site in English
    return "fr";
  })();
  function T() { return I18N[lang] || I18N.fr; }

  /* The page is the French source: read it once before anything is replaced. */
  function harvest() {
    var fr = I18N.fr;
    $$("[data-t]").forEach(function (el) { var k = el.getAttribute("data-t"); if (fr[k] === undefined) fr[k] = el.textContent; });
    $$("[data-t-attr]").forEach(function (el) {
      el.getAttribute("data-t-attr").split(",").forEach(function (pair) {
        var p = pair.split(":"), attr = p[0].trim(), k = p[1].trim();
        if (fr[k] === undefined) fr[k] = el.getAttribute(attr) || "";
      });
    });
    var tk = document.body.getAttribute("data-title"); if (tk && fr[tk] === undefined) fr[tk] = document.title;
    var dk = document.body.getAttribute("data-desc"), md = $('meta[name="description"]');
    if (dk && md && fr[dk] === undefined) fr[dk] = md.getAttribute("content") || "";
  }

  var MAIL = {
    fr: { general: { subject: "Nokime Jobs — contact", body: "" }, schools: { subject: "Nokime Jobs — une école", body: "Établissement :\nCe que j’en pense :\nCe qui manque pour la classe :\n" }, filled: { subject: "Poste pourvu — Nokime Jobs", body: "Offre : [poste, établissement]\nPourvu le : \nPremier jour de la personne : \nPourvu grâce à Nokime Jobs : oui / non\n" } },
    en: { general: { subject: "Nokime Jobs — contact", body: "" }, schools: { subject: "Nokime Jobs — a school", body: "School:\nWhat I think of it:\nWhat is missing for the classroom:\n" }, filled: { subject: "Position filled — Nokime Jobs", body: "Offer: [position, establishment]\nFilled on: \nPerson’s first day: \nFilled thanks to Nokime Jobs: yes / no\n" } }
  };

  function applyLang() {
    var t = T();
    document.documentElement.setAttribute("lang", lang);
    $$("[data-t]").forEach(function (el) { var k = el.getAttribute("data-t"); if (t[k] !== undefined) el.textContent = t[k]; });
    $$("[data-t-attr]").forEach(function (el) {
      el.getAttribute("data-t-attr").split(",").forEach(function (pair) {
        var p = pair.split(":"), attr = p[0].trim(), k = p[1].trim();
        if (t[k] !== undefined) el.setAttribute(attr, t[k]);
      });
    });
    $$("[data-mail]").forEach(function (a) {
      var m = (MAIL[lang] || MAIL.fr)[a.getAttribute("data-mail")];
      if (m) a.href = "mailto:" + ADDRESS + "?subject=" + encodeURIComponent(m.subject) + (m.body ? "&body=" + encodeURIComponent(m.body) : "");
    });
    var tk = document.body.getAttribute("data-title"); if (tk && t[tk]) document.title = t[tk];
    var dk = document.body.getAttribute("data-desc"), md = $('meta[name="description"]');
    if (dk && t[dk] && md) md.setAttribute("content", t[dk]);
    $$("#langBtn,[data-lang-switch]").forEach(function (lb) { lb.textContent = lang === "fr" ? "EN" : "FR"; lb.setAttribute("lang", lang === "fr" ? "en" : "fr"); lb.setAttribute("aria-label", t.langSwitch || ""); });
    var mb = $("#menuBtn"); if (mb && t.menuLabel) mb.setAttribute("aria-label", t.menuLabel);
  }
  function setLang(l) { lang = l; try { localStorage.setItem(LS_LANG, l); } catch (e) {} applyLang(); }

  /* ---------- navigation ---------- */
  function markCurrent() {
    var here = location.pathname.replace(/index\.html$/, "");
    var brand = document.querySelector(".brand"), home = brand ? brand.pathname.replace(/index\.html$/, "") : "/";
    $$(".nav a").forEach(function (a) {
      var target = a.pathname.replace(/index\.html$/, "");
      if (target !== home && here.indexOf(target) === 0) a.setAttribute("aria-current", "page");
    });
  }

  /* ---------- boot ---------- */
  harvest();
  applyLang();
  markCurrent();
  $$("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });

  /* « Postuler »: count the click for its offer (day and offer id only, kept server-side by api/postuler.php), once per offer per page load;
     the mailto proceeds untouched, and with no sendBeacon nothing happens */
  var counted = {};
  document.addEventListener("click", function (e) {
    var a = e.target && e.target.closest ? e.target.closest("a[data-offer]") : null;
    if (!a || !navigator.sendBeacon) return;
    var id = a.getAttribute("data-offer");
    if (!id || counted[id]) return;
    counted[id] = true;
    try { navigator.sendBeacon("/api/postuler.php", new URLSearchParams({ o: id })); } catch (err) {}
  });

  $$("#langBtn,[data-lang-switch]").forEach(function (lb) { lb.addEventListener("click", function () { setLang(lang === "fr" ? "en" : "fr"); }); });
  var mb = $("#menuBtn"), nav = $("#nav");
  if (mb && nav) {
    mb.addEventListener("click", function () {
      var open = nav.classList.toggle("open"); mb.setAttribute("aria-expanded", String(open));
    });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && nav.classList.contains("open")) { nav.classList.remove("open"); mb.setAttribute("aria-expanded", "false"); } });
  }
})();

/* l'hirondelle de Nokime Jobs: behind the card of an establishment that has it (.has-hd, js/jobs.js and tools/build.py), a field of
   large, very light swallows scattered at random, but seeded by the establishment (data-hd-key) so it is the same on every visit.
   Drawn as an SVG background at the card's own size; redrawn when the card changes size. */
(function () {
  var P = "M99 22 L88 28 C87 21 80 18 75 23 C73 26 70 30 66 32 C56 20 34 10 8 12 C26 22 38 34 48 50 C38 54 20 62 4 74 C14 72 24 70 32 70 C30 80 28 90 26 98 C36 88 46 78 52 66 C58 80 64 90 74 98 C70 84 68 70 68 58 C80 52 92 40 99 22 Z";
  function seed(str) { var h = 2166136261; for (var i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); } return h >>> 0; }
  function rng(a) { return function () { a = a + 0x6D2B79F5 | 0; var t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
  function field(key, w, h) {
    var r = rng(seed(key)), birds = [], tries = 0, want = Math.max(2, Math.round(w * h / 95000));
    while (birds.length < want && tries++ < 400) {   // bounded: at most 400 tries a card
      var s = 150 + r() * 40, x = -s * 0.25 + r() * (w - s * 0.5), y = -s * 0.25 + r() * (h - s * 0.5), ok = true;
      for (var i = 0; i < birds.length; i++) { var dx = birds[i].x - x, dy = birds[i].y - y; if (dx * dx + dy * dy < Math.pow((birds[i].s + s) * 0.55, 2)) { ok = false; break; } }
      if (ok) birds.push({ x: x, y: y, s: s, rot: -25 + r() * 50, flip: r() < 0.35 });
    }
    var g = birds.map(function (b) {
      var c = b.s / 2;
      return "<g transform='translate(" + b.x.toFixed(1) + " " + b.y.toFixed(1) + ") rotate(" + b.rot.toFixed(1) + " " + c + " " + c + ")" +
        (b.flip ? " translate(" + b.s.toFixed(1) + " 0) scale(-1 1)" : "") + " scale(" + (b.s / 100).toFixed(3) + ")'><path d='" + P + "'/></g>";
    }).join("");
    return "url(\"data:image/svg+xml," + encodeURIComponent("<svg xmlns='http://www.w3.org/2000/svg' width='" + w + "' height='" + h + "' viewBox='0 0 " + w + " " + h + "'><g fill='#DCE2FA'>" + g + "</g></svg>").replace(/'/g, "%27") + "\")";
  }
  var watch = window.ResizeObserver ? new ResizeObserver(function () { paint(); }) : null;   // a card that grows (a fold opened, the fonts, the language) is redrawn
  function paint() {
    Array.prototype.forEach.call(document.querySelectorAll(".has-hd[data-hd-key]"), function (el) {
      if (watch && !el.hasAttribute("data-hd-watched")) { el.setAttribute("data-hd-watched", ""); watch.observe(el); }
      var w = Math.ceil(el.offsetWidth), h = Math.ceil(el.offsetHeight);
      if (!w || !h || el.getAttribute("data-hd-size") === w + "x" + h) return;
      el.setAttribute("data-hd-size", w + "x" + h);
      el.style.backgroundImage = field(el.getAttribute("data-hd-key"), w, h);
      el.style.backgroundSize = w + "px " + h + "px";
    });
  }
  window.nokimeHdPaint = paint;
  var timer; window.addEventListener("resize", function () { clearTimeout(timer); timer = setTimeout(paint, 150); });
  paint();
})();
