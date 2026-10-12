/* Nokime Jobs — accounts: /compte/, /compte/mot-de-passe/ and /compte/confirmer/ (phase 1, dark launch: nothing
   links here yet).
   Calls api/auth/ with fetch, same-origin credentials; the session cookie is HttpOnly, so this script never sees
   it. Each error is shown next to its field; nothing moves. The words: French is read out of the page by
   site.js (the hidden list of messages included), English is in js/i18n.js. No dependencies. */
(function () {
  "use strict";

  var I18N = window.NOKIME_I18N || { fr: {}, en: {} };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function lang() { return document.documentElement.getAttribute("lang") === "en" ? "en" : "fr"; }
  function t(k) {
    var l = I18N[lang()] || {};
    return l[k] !== undefined ? l[k] : (I18N.fr[k] !== undefined ? I18N.fr[k] : "");
  }

  /* A shown message keeps its key, so the language switch can say it again in the other language. */
  function render(el) {
    var vars = {}; try { vars = JSON.parse(el.getAttribute("data-vars") || "{}"); } catch (e) {}
    el.textContent = t(el.getAttribute("data-msg")).replace(/\{(\w+)\}/g, function (_, k) { return vars[k] !== undefined ? vars[k] : ""; });
  }
  function say(el, key, vars) {
    if (!el) return;
    el.setAttribute("data-msg", key);
    if (vars) el.setAttribute("data-vars", JSON.stringify(vars)); else el.removeAttribute("data-vars");
    render(el);
    el.hidden = false;
  }
  function hush(el) { if (!el) return; el.hidden = true; el.textContent = ""; el.removeAttribute("data-msg"); }
  $$("#langBtn,[data-lang-switch]").forEach(function (b) {
    b.addEventListener("click", function () { $$("[data-msg]").forEach(render); });   // runs after site.js's own handler
  });

  /* One call to api/auth/: the answer's JSON with its status, or {error: "server"} when nothing came back. */
  function api(path, body) {
    var opt = { credentials: "same-origin", headers: { "Accept": "application/json" } };
    if (body !== undefined) { opt.method = "POST"; opt.headers["Content-Type"] = "application/json"; opt.body = JSON.stringify(body); }
    return fetch("/api/auth/" + path, opt).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) { j = j || {}; j.status = r.status; return j; });
    }, function () { return { ok: false, error: "server", status: 0 }; });
  }

  /* ---------- errors beside their field ---------- */
  var uid = 0;
  function errFor(input) {
    var f = input && input.closest(".f-field");
    return f ? $(".f-err", f) : null;
  }
  /* A field's own hint (the password rule) stays in its description, an error or not. */
  function hintOf(input) {
    var f = input && input.closest(".f-field"), h = f ? $(".f-hint", f) : null;
    return h && h.id ? h.id : "";
  }
  function clearErrors(form) {
    $$("[aria-invalid]", form).forEach(function (i) {
      i.removeAttribute("aria-invalid");
      if (hintOf(i)) i.setAttribute("aria-describedby", hintOf(i)); else i.removeAttribute("aria-describedby");
    });
    $$(".f-err,[data-form-err],[data-form-ok]", form).forEach(hush);
  }
  function fieldError(form, name, key, vars) {
    var input = form.elements[name];
    if (input && input.length !== undefined && !input.tagName) input = input[0];   // a radio group: its first chip
    var el = errFor(input);
    if (!el) return false;
    if (!el.id) el.id = "cErr" + (++uid);
    input.setAttribute("aria-invalid", "true");
    input.setAttribute("aria-describedby", (hintOf(input) ? hintOf(input) + " " : "") + el.id);
    say(el, key, vars);
    return input;
  }
  function known(key) { return I18N.fr[key] !== undefined; }
  function showError(form, j) {
    var key = "cErr_" + (j.error || "server");
    if (!known(key)) key = "cErr_server";
    var vars = j.error === "wait" ? { n: Math.max(1, Math.ceil((j.retry_after || 60) / 60)) } : null;
    var input = j.field ? fieldError(form, j.field, key, vars) : false;
    if (input) { input.focus(); return; }
    say($("[data-form-err]", form), key, vars);
  }

  /* Empty required fields are named before anything is sent; the server checks everything again. */
  function missing(form) {
    var first = null;
    $$("input[required]", form).forEach(function (i) {
      if (i.closest("[hidden]") || (i.type === "radio" ? form.elements[i.name].value : i.value.trim())) return;
      if (i.type === "radio" && i !== form.elements[i.name][0]) return;
      fieldError(form, i.name, "cErr_required");
      if (!first) first = i;
    });
    if (first) first.focus();
    return !!first;
  }

  function bind(form, path, collect, done, failed) {
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clearErrors(form);
      if (missing(form)) return;
      var body = collect(form);
      if (body === null) return;
      var b = $("[type=submit]", form);
      if (b) b.disabled = true;
      api(path, body).then(function (j) {
        if (b) b.disabled = false;
        if (j.ok) { done(j, form); return; }
        if (j.error === "signed_out") { showOut(); say($("#cFlashErr"), "cErr_signed_out"); return; }
        showError(form, j);
        if (failed) failed(j);
      });
    });
  }
  function ok(form, key) { form.reset(); say($("[data-form-ok]", form), key); }
  function val(form, name) { return form.elements[name].value; }

  /* ---------- /compte/ ---------- */
  var out = $("#cOut"), inside = $("#cIn");
  function showOut() { if (inside) inside.hidden = true; if (out) out.hidden = false; }
  function showIn(me) {
    if (out) out.hidden = true;
    if (!inside) return;
    inside.hidden = false;
    $("#cMeEmail").textContent = me.email;
    say($("#cMeKind"), "cKind_" + (me.kind === "establishment" ? "establishment" : "candidate"));
  }

  if (out && inside) {
    var q = new URLSearchParams(location.search);
    if (q.get("ok") === "confirm") say($("#cFlashOk"), "cOkConfirm");
    if (q.get("err") === "link") say($("#cFlashErr"), "cErr_link");
    if (q.has("ok") || q.has("err")) { try { history.replaceState(history.state, "", location.pathname + location.hash); } catch (e) {} }
    api("me.php").then(function (j) { if (j.ok) showIn(j); else showOut(); });

    var signup = $("#signupForm"), age = $("#ageRow");
    $$("input[name=kind]", signup).forEach(function (r) {
      r.addEventListener("change", function () { age.hidden = signup.elements.kind.value !== "candidate"; });
    });
    bind($("#loginForm"), "login.php", function (f) { return { email: val(f, "email"), password: val(f, "password") }; }, function (j, f) {
      f.reset(); hush($("#cFlashOk")); hush($("#cFlashErr"));
      showIn(j); $("#mineLabel").focus();
    });
    bind(signup, "signup.php", function (f) {
      var b = { email: val(f, "email"), password: val(f, "password"), kind: f.elements.kind.value, lang: lang(), terms: f.elements.terms.checked };
      if (b.kind === "candidate") b.age15 = f.elements.age15.checked;
      return b;
    }, function (j, f) { ok(f, "cOkSignup"); age.hidden = true; });
    bind($("#passwordForm"), "password.php", function (f) { return { current: val(f, "current"), password: val(f, "password") }; },
      function (j, f) { ok(f, "cOkPassword"); });
    bind($("#emailForm"), "email.php", function (f) { return { password: val(f, "password"), new_email: val(f, "new_email") }; },
      function (j, f) { ok(f, "cOkEmail"); });
    bind($("#logoutForm"), "logout.php", function () { return {}; }, function () {
      showOut(); say($("#cFlashOk"), "cOkLoggedOut"); $("#loginLabel").focus();
    });
    bind($("#deleteForm"), "delete.php", function (f) {
      var typed = val(f, "confirm").trim().toUpperCase();
      if (typed !== (I18N.fr.cDeleteWord || "") && typed !== (I18N.en.cDeleteWord || "")) {
        var i = fieldError(f, "confirm", "cErr_confirm_word"); if (i) i.focus();
        return null;
      }
      return { password: val(f, "password") };
    }, function (j, f) { f.reset(); showOut(); say($("#cFlashOk"), "cOkDeleted"); $("#loginLabel").focus(); });
  }

  /* ---------- /compte/mot-de-passe/ ---------- */
  var forgot = $("#forgotForm"), reset = $("#resetForm");
  if (forgot && reset) {
    /* The link's token is read once, then leaves the address bar (and so any later Referer or history). */
    var m = /[?&]t=([A-Za-z0-9_-]{43})(?:&|$)/.exec(location.search), token = m ? m[1] : "";
    if (location.search) { try { history.replaceState(history.state, "", location.pathname + location.hash); } catch (e) {} }
    if (token) {
      forgot.hidden = true; reset.hidden = false;
      $("#mpForgotTitle").hidden = true; $("#mpResetTitle").hidden = false; $("#mpForgotLead").hidden = true;
    }
    bind(forgot, "forgot.php", function (f) { return { email: val(f, "email") }; }, function (j, f) { ok(f, "cOkForgot"); });
    bind(reset, "reset.php", function (f) { return { t: token, password: val(f, "password") }; }, function (j, f) {
      ok(f, "cOkReset"); $("[type=submit]", f).disabled = true; $("#mpSignIn").hidden = false;
    }, function (j) { $("#mpAgain").hidden = j.error !== "link"; });   // a dead link: offer a new one
  }

  /* ---------- /compte/confirmer/ ---------- */
  /* A mailed confirmation link opens this page; only its button spends the link (mail scanners open links
     but press no button). Then /compte/ says how it went, as the old link did. */
  var confirmForm = $("#confirmForm");
  if (confirmForm) {
    var cm = /[?&]t=([A-Za-z0-9_-]{43})(?:&|$)/.exec(location.search), ct = cm ? cm[1] : "";
    if (location.search) { try { history.replaceState(history.state, "", location.pathname + location.hash); } catch (e) {} }
    var go = function (q) { location.replace("../?" + q + (lang() === "en" ? "&lang=en" : "")); };
    bind(confirmForm, "confirm.php", function () { return { t: ct }; }, function () { go("ok=confirm"); },
      function (j) { if (j.error === "link") go("err=link"); });
  }
})();
