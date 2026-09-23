# Nokime Jobs — le site

Stages, alternances et saisons en cuisine. Gratuit pour les candidats ; les restaurants publient et répondent directement. Static, no build step for the pages; one small script renders the offer pages.

- French is the source language and lives in the HTML; `js/site.js` reads it out of every `data-t` element at load. English lives in `js/i18n.js`.
- Offers live in `js/offers.js` (`NOKIME_JOBS`, French, one object per offer; `expires` drops them). `NOKIME_JOBS_DEMO` shows only with `?demo=1`.
- `python3 tools/build.py` writes one page per live offer under `o/<id>/` (with schema.org JobPosting data) and the sitemap. Run it after every change to the offers.
- `js/site.js` (language switch, mail links, menu) is shared with the group site; the look is this site's own, see Identity.

Serve locally: `python3 -m http.server 8648` (or `preview_start` name `nokime-jobs`).
Ship: `python3 tools/bump.py` (assets touched), `python3 tools/check-i18n.py`, `python3 tools/build.py`, commit, push.

## Le questionnaire

`questionnaire/` is a short multiple-choice questionnaire for people who did an internship, an apprenticeship or a season in a kitchen. It prepares the distinction and nothing else: no answer is ever published, neither alone nor added up for an establishment, and nothing about any restaurant is published. It is reached from the home page's distinction section, from the écoles page and from the footer; it has no menu item.

- The button builds a mail to contact@copius.fr in `js/jobs.js`: every answer in French, the two confirmations, a codes line to copy into the private file, and a closing line asking for the proof. Nothing is sent by the site.
- **The questions are written once**, in `tools/questionnaire_questions.py`. `tools/build.py` writes the period selects and the question fieldsets into `questionnaire/index.html` and the English strings into `js/i18n.js`, each between its `start`/`end` markers. Never rename a question or option id once answers use it.
- **Answers, identities, proofs and the verification log never enter this repository.** They live in a private folder outside any repository (`~/Projets/kairos/jobs-retours/`, whose `LISEZMOI.md` says how to check a proof, log it, keep the answers, withdraw them and delete them after three years). `tools/build.py` reads nothing from it.

## Identity

Le passe bleu: the offers are plain white cards on the steel band of a kitchen pass (`#E7EAED`), read like a list in one pass, and the rest of the site is white paper, ink `#17191E` and one blue (`#2038D5`), used only for the headline's promise, primary buttons, the pressed filter, the current page, the distinction and focus rings. Two faces, self-hosted in `fonts/` (OFL): Bricolage Grotesque speaks (the headline at 800, width 85 %, and every word of an offer), DM Mono labels (eyebrows, the count, the form's section labels). Cards are softened at 12px with a 1px border and no shadow, controls are round; hairlines between sections; the rail survives in the headline drawing, the mark and the steps; nothing moves on load. No dark mode. `css/site.css` holds every token on `:root`; `DESIGN.md` gives the reasons, what came from each earlier version, and the contrast ratios. `python3 tools/og-card.py` renders `og.png` and `apple-touch-icon.png` in the same identity, reading the rail drawing and the mark out of `index.html`.
