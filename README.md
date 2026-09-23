# Nokime Jobs — le site

Stages, alternances et saisons en hôtellerie-restauration, en cuisine, en salle, à l’hébergement et au spa. Gratuit pour les candidats ; les établissements publient et répondent directement. Static, no build step for the pages; one small script renders the offer pages.

- French is the source language and lives in the HTML; `js/site.js` reads it out of every `data-t` element at load. English lives in `js/i18n.js`.
- Offers live in `js/offers.js` (`NOKIME_JOBS`, French, one object per offer; `expires` drops them). Each offer names its department in `metier` (`cuisine`, `salle`, `hebergement` or `spa`: Cuisine, Salle, Hébergement, Spa; Kitchen, Dining room, Rooms division, Spa in English; `spa` is a hotel's spa, never a beauty salon or a day spa outside a hotel), shown in the card's pill beside the kind, as the first fact of its own page, and filtered by the métier select beside the région select; an offer without one shows no department and drops out only when a department is chosen. `NOKIME_JOBS_DEMO` shows only with `?demo=1`.
- `python3 tools/build.py` writes one page per live offer under `o/<id>/` (with schema.org JobPosting data) and the sitemap. Run it after every change to the offers.
- `js/site.js` (language switch, mail links, menu) is shared with the group site; the look is this site's own, see Identity.

Serve locally: `python3 -m http.server 8648` (or `preview_start` name `nokime-jobs`).
Ship: `python3 tools/bump.py` (assets touched), `python3 tools/check-i18n.py`, `python3 tools/build.py`, commit, push.

## Prices, search, « Mise en avant » and the charter

- **Prices.** Establishments choose a tier: Une offre 19 € HT (one offer online at a time), Brigade 39 € HT (up to five), Maison 79 € HT (unlimited, and « Mise en avant »), each billed for every month with at least one offer online, through a payment link; no card is taken on the site. Candidates pay 0 €. The first season is free: three months at the Brigade level, counted from the first offer, once per establishment, without commitment. The model is written in four places that must stay identical: the home page's `#prix` section (`restoFree`, `jt*`), the posting page (`postPriceA`, `p2t`/`p2p`), and the legal notice (Prix).
- **Search.** The box above the filters; `fold`, `haystack` and `found` in `js/jobs.js`. Case and accents are ignored and every word must appear in the role, the restaurant, the town, the région, the kind, the department or the text (Nokime's English too in the English view). It narrows the list together with the filters.
- **« Mise en avant ».** `boostable()`, `boostOrder()` and `MAX_BOOST = 3` in `js/jobs.js`. An offer in `js/offers.js` is featured only with `boost: true` and `charter: true`, without `boostPaused: true`, and when it states its pay (not `payHidden`), its `hours` and `housing` (true or false). After the filters and the search, at most three such offers, the most recently published, come first with the label; every other offer stays newest first. The rule is told to readers in the home page's « Comment la liste est classée » fold and in the legal notice: change them with the code.
- **The charter.** `charte/`, public and in the sitemap, linked from every footer, the Maison tier, the ordering fold and the legal notice. An establishment joins it by email; then set `charter: true` on its offers, and `boostPaused: true` while a serious report about it is checked.

## Le questionnaire

`questionnaire/` is a short multiple-choice questionnaire for people who did an internship, an apprenticeship or a season in a hotel or a restaurant, in the kitchen, the dining room, the rooms division or the spa. It prepares the distinction and nothing else: no answer is ever published, neither alone nor added up for an establishment, and nothing about any establishment is published. It is reached from the home page's distinction section, from the écoles page and from the footer; it has no menu item.

- The button builds a mail to contact@copius.fr in `js/jobs.js`: every answer in French, the two confirmations, a codes line to copy into the private file, and a closing line asking for the proof. Nothing is sent by the site.
- **The questions are written once**, in `tools/questionnaire_questions.py`. `tools/build.py` writes the period selects and the question fieldsets into `questionnaire/index.html` and the English strings into `js/i18n.js`, each between its `start`/`end` markers. Never rename a question or option id once answers use it.
- **Answers, identities, proofs and the verification log never enter this repository.** They live in a private folder outside any repository (`~/Projets/kairos/jobs-retours/`, whose `LISEZMOI.md` says how to check a proof, log it, keep the answers, withdraw them and delete them after three years). `tools/build.py` reads nothing from it.

## Identity

Le passe bleu: the offers are plain white cards on the steel band of a kitchen pass (`#E7EAED`), read like a list in one pass, and the rest of the site is white paper, ink `#17191E` and one blue (`#2038D5`), used only for the headline's promise, primary buttons, the pressed filter, the current page, the distinction and focus rings. Two faces, self-hosted in `fonts/` (OFL): Bricolage Grotesque speaks (the headline at 800, width 85 %, and every word of an offer), DM Mono labels (eyebrows, the count, the form's section labels). Cards are softened at 12px with a 1px border and no shadow, controls are round; hairlines between sections; the rail survives in the headline drawing, the mark and the steps; nothing moves on load. No dark mode. `css/site.css` holds every token on `:root`; `DESIGN.md` gives the reasons, what came from each earlier version, and the contrast ratios. `python3 tools/og-card.py` renders `og.png` and `apple-touch-icon.png` in the same identity, reading the rail drawing and the mark out of `index.html`.
