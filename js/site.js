/* Nokime — site script. No build step, no dependencies.
   Language: French is read out of the page itself at load; English comes from i18n.js.
   Dark only: there is no theme code.
   The demo: real Copius price bands (js/art.js), mid-point of the band, all-kg products. */
(function () {
  "use strict";

  var LS_LANG = "nokime-lang";
  var ADDRESS = "contact@copius.fr";
  var I18N = window.NOKIME_I18N || { fr: {}, en: {} };
  var ART = window.NOKIME_ART || {};

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
    $$("[data-art]").forEach(drawInto);
    $$("[data-demo]").forEach(renderDemo);
  }
  function setLang(l) { lang = l; try { localStorage.setItem(LS_LANG, l); } catch (e) {} applyLang(); }

  /* ---------- drawings ---------- */
  function art(id, label) {
    var a = ART[id]; if (!a) return "";
    return '<svg class="art" viewBox="0 0 96 96" role="img" aria-label="' + esc(label || a[lang] || a.fr) + '">' + a.svg + "</svg>";
  }
  function drawInto(el) {
    var id = el.getAttribute("data-art"), a = ART[id]; if (!a) return;
    var caption = el.hasAttribute("data-caption");
    el.innerHTML = caption
      ? '<svg class="art" viewBox="0 0 96 96" aria-hidden="true">' + a.svg + "</svg><figcaption>" + esc(a[lang] || a.fr) + "</figcaption>"
      : art(id);
  }

  /* ---------- the live demo ---------- */
  var PRESETS = {
    bar: { price: 19, lines: [["sea-bass", 180], ["asparagus", 120], ["butter", 40], ["lemon", 30]] },
    risotto: { price: 17, lines: [["rice", 80], ["porcini", 100], ["parmesan", 30], ["butter", 30], ["shallot", 20]] }
  };
  function money(v) {
    return new Intl.NumberFormat(lang === "fr" ? "fr-FR" : "en-GB", { style: "currency", currency: "EUR", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
  }
  function mid(a) { return (a.lo + a.hi) / 2; }
  function bandText(a) {
    var lo = Math.round(a.lo), hi = Math.round(a.hi);
    return lang === "fr" ? lo + "–" + hi + " €/" + a.unit : "€" + lo + "–" + hi + "/" + a.unit;
  }
  /* "September to February" from [9,10,11,12,1,2]: find the month whose predecessor is absent. */
  function seasonText(months) {
    var t = T(), names = t.months || I18N.fr.months;
    if (!months || months.length === 0 || months.length >= 12) return t.demoAllYear;
    var set = {}; months.forEach(function (m) { if (m >= 1 && m <= 12) set[m] = true; });
    var starts = [], m;
    for (m = 1; m <= 12; m++) if (set[m] && !set[m === 1 ? 12 : m - 1]) starts.push(m);
    if (!starts.length) return t.demoAllYear;
    return starts.map(function (s) {
      var e = s; while (set[e === 12 ? 1 : e + 1] && (e === 12 ? 1 : e + 1) !== s) e = e === 12 ? 1 : e + 1;
      return e === s ? names[s - 1] : fill(t.demoFrom, { a: names[s - 1], b: names[e - 1] });
    }).join(", ");
  }

  function renderDemo(el) {
    var t = T();
    if (!el._state) {
      var key = el.getAttribute("data-demo") || "bar", p = PRESETS[key] || PRESETS.bar;
      el._state = { preset: key, price: p.price, qty: {} };
      p.lines.forEach(function (l) { el._state.qty[l[0]] = l[1]; });
    }
    var st = el._state, preset = PRESETS[st.preset];
    var withPresets = el.hasAttribute("data-presets");
    var h = '<div class="demo-head"><p class="demo-title">' + esc(t.demoTitle) + "</p></div>" +
            '<p class="demo-hint">' + esc(t.demoHint) + "</p>";
    if (withPresets) {
      h += '<div class="demo-presets" role="group" aria-label="' + esc(t.demoPresets) + '">' +
           '<button type="button" class="chip" data-preset="bar" aria-pressed="' + (st.preset === "bar") + '">' + esc(t.preset1) + "</button>" +
           '<button type="button" class="chip" data-preset="risotto" aria-pressed="' + (st.preset === "risotto") + '">' + esc(t.preset2) + "</button></div>";
    }
    h += '<div class="demo-lines">';
    preset.lines.forEach(function (l) {
      var a = ART[l[0]]; if (!a) return;
      var name = a[lang] || a.fr;
      h += '<div class="demo-line">' + art(l[0], name) +
           '<div class="demo-name"><b>' + esc(name) + '</b><span class="demo-band">' + esc(bandText(a)) + "</span></div>" +
           '<label class="demo-qty"><input type="number" min="0" step="10" inputmode="numeric" data-qty="' + l[0] + '" value="' + esc(st.qty[l[0]]) + '" aria-label="' + esc(fill(t.demoQtyLabel, { n: name })) + '"><span>g</span></label>' +
           '<div class="demo-cost mono" data-cost="' + l[0] + '"></div></div>';
    });
    h += "</div>" +
         '<div class="demo-row total"><span>' + esc(t.demoTotal) + '</span><b class="mono" data-total></b></div>' +
         '<div class="demo-row"><label>' + esc(t.demoPrice) + ' <input type="number" min="0" step="0.5" inputmode="decimal" data-price value="' + esc(st.price) + '" aria-label="' + esc(t.demoPriceLabel) + '"> €</label></div>' +
         '<div class="demo-row"><span>' + esc(t.demoRatio) + '</span><b class="mono" data-ratio></b></div>' +
         '<p class="verdict" data-verdict></p>' +
         '<p class="demo-season"><span>' + esc(t.demoSeason) + '</span> <span data-season></span></p>' +
         '<details class="more"><summary>' + esc(t.demoNoteQ) + '</summary><p class="demo-note">' + esc(t.demoNote) + "</p></details>";
    el.innerHTML = h;

    function update() {
      var total = 0, top = null, topCost = -1;
      preset.lines.forEach(function (l) {
        var a = ART[l[0]]; if (!a) return;
        var q = Math.max(0, parseFloat(st.qty[l[0]]) || 0), c = q / 1000 * mid(a);
        total += c; $("[data-cost='" + l[0] + "']", el).textContent = money(c);
        if (c > topCost) { topCost = c; top = a; }
      });
      $("[data-total]", el).textContent = money(total);
      var price = Math.max(0, parseFloat(st.price) || 0), ratio = price > 0 ? total / price * 100 : 0;
      $("[data-ratio]", el).textContent = price > 0 ? Math.round(ratio) + "\u202f%" : "—";
      var v = $("[data-verdict]", el);
      v.className = "verdict " + (price <= 0 ? "under" : ratio > 38 ? "over" : ratio < 30 ? "under" : "in");
      v.textContent = price <= 0 ? "—" : ratio > 38 ? t.demoOver : ratio < 30 ? t.demoUnder : t.demoIn;
      $("[data-season]", el).textContent = top ? (top[lang] || top.fr) + ", " + seasonText(top.season) : "";
    }
    $$("[data-qty]", el).forEach(function (inp) { inp.addEventListener("input", function () { st.qty[inp.getAttribute("data-qty")] = inp.value; update(); }); });
    $("[data-price]", el).addEventListener("input", function (e) { st.price = e.target.value; update(); });
    $$("[data-preset]", el).forEach(function (b) {
      b.addEventListener("click", function () {
        var k = b.getAttribute("data-preset"); if (k === st.preset) return;
        var p = PRESETS[k]; st.preset = k; st.price = p.price; st.qty = {};
        p.lines.forEach(function (l) { st.qty[l[0]] = l[1]; });
        renderDemo(el);
      });
    });
    update();
  }

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
