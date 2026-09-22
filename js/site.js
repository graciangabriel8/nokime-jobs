/* Nokime — site script. No build step, no dependencies.
   Language: French is read out of the page itself at load; English comes from i18n.js.
   Dark only: there is no theme code. */
(function () {
  "use strict";

  var LS_LANG = "nokime-lang";
  var ADDRESS = "contact@copius.fr";
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
    try { var v = localStorage.getItem(LS_LANG); if (v === "fr" || v === "en") return v; } catch (e) {}
    var n = (navigator.language || "fr").toLowerCase();
    return n.indexOf("fr") === 0 ? "fr" : "en";
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
    fr: { general: { subject: "Nokime Jobs — contact", body: "" }, schools: { subject: "Nokime Jobs — une école", body: "Établissement :\nCe que j’en pense :\nCe qui manque pour la classe :\n" } },
    en: { general: { subject: "Nokime Jobs — contact", body: "" }, schools: { subject: "Nokime Jobs — a school", body: "School:\nWhat I think of it:\nWhat is missing for the classroom:\n" } }
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
    var lb = $("#langBtn");
    if (lb) { lb.textContent = lang === "fr" ? "EN" : "FR"; lb.setAttribute("lang", lang === "fr" ? "en" : "fr"); lb.setAttribute("aria-label", t.langSwitch || ""); }
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

  var lb = $("#langBtn"); if (lb) lb.addEventListener("click", function () { setLang(lang === "fr" ? "en" : "fr"); });
  var mb = $("#menuBtn"), nav = $("#nav");
  if (mb && nav) {
    mb.addEventListener("click", function () {
      var open = nav.classList.toggle("open"); mb.setAttribute("aria-expanded", String(open));
    });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && nav.classList.contains("open")) { nav.classList.remove("open"); mb.setAttribute("aria-expanded", "false"); } });
  }
})();
