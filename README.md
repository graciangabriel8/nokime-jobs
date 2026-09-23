# Nokime Jobs — le site

Stages, alternances et saisons en cuisine. Gratuit pour les candidats ; les restaurants publient et répondent directement. Static, no build step for the pages; one small script renders the offer pages.

- French is the source language and lives in the HTML; `js/site.js` reads it out of every `data-t` element at load. English lives in `js/i18n.js`.
- Offers live in `js/offers.js` (`NOKIME_JOBS`, French, one object per offer; `expires` drops them). `NOKIME_JOBS_DEMO` shows only with `?demo=1`.
- `python3 tools/build.py` writes one page per live offer under `o/<id>/` (with schema.org JobPosting data) and the sitemap. Run it after every change to the offers.
- `js/site.js` (language switch, mail links, menu) is shared with the group site; the look is this site's own, see Identity.

Serve locally: `python3 -m http.server 8648` (or `preview_start` name `nokime-jobs`).
Ship: `python3 tools/bump.py` (assets touched), `python3 tools/check-i18n.py`, `python3 tools/build.py`, commit, push.

## Identity

Le passe bleu: the offers are paper tickets on the steel rail of a kitchen pass (`#E7EAED`), and the rest of the site is white paper, ink `#17191E` and one blue (`#2038D5`), used only for the headline's promise, primary buttons, the pressed filter, the current page, the distinction and focus rings. Two faces, self-hosted in `fonts/` (OFL): Bricolage Grotesque speaks (the headline at 800, width 85 %), DM Mono prints (kinds, dates, figures, months). Paper has no radius, controls are round; hairlines between sections, perforations only inside tickets; nothing moves on load. No dark mode. `css/site.css` holds every token on `:root`; `DESIGN.md` gives the reasons, what came from each earlier version, and the contrast ratios. `python3 tools/og-card.py` renders `og.png` and `apple-touch-icon.png` in the same identity, reading the rail drawing and the mark out of `index.html`.
