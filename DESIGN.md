# Nokime Jobs — le passe bleu

## Thesis

The board is the pass of a kitchen: a steel band on which every offer is a plain white card, read like a list in one pass, because a candidate must understand an offer at a glance. Around the board the page is the poster: white paper, black ink, one blue, hairlines and a lot of air. The rail lives on in the drawing beside the headline, in the mark and in the steps; the offers themselves are only clear.

## Where it comes from

Two versions were compared side by side. The red one (le passe) was found interesting but too much; the blue one (the poster) calm but shy. This version kept the object from the first and the voice and restraint of the second. The owner then saw the offers as paper tickets hanging from the rail and found the tickets themselves too much: he asked for offers that are very clear and understandable, so each offer became a plain card (see how an offer reads, below).

- **From le passe**, because it is what makes the site not shy: the steel band behind the board and the posting form; the rail drawing beside the headline; the steps drawn as a line through three balls; round buttons and chips; the two-tickets-under-a-rail mark; the dotted ring beside each restaurant name where the distinction goes.
- **From the poster**, because it is what makes it calm: white paper everywhere outside the board; one ultramarine, `#2038D5`, as the only colour; Bricolage Grotesque and DM Mono; the confident headline (800, width 85 %); nothing moving on load.
- **Toned down from le passe**, because it is what made it too much: the red is gone entirely; the drop-in and the sway are gone; the perforations between page sections became hairlines; the large rosette of the distinction section became a small mark beside its text; fewer uppercase mono labels.
- **Dropped from le passe**, at the owner's request: the offers as tickets (the rail and ball over each, the torn foot, the perforations, the paper shadow, the hover lift, the printer-style mono lines, the J–D months strip), the blank ticket of the empty state, and the posting form as a ticket on the rail.

## Palette

Every colour is a token on `:root` in `css/site.css`. Ratios are WCAG 2 contrast ratios, computed from the hex values.

| Token | Hex | Role | Contrast |
|---|---|---|---|
| `--paper` | `#FFFFFF` | the page, the cards (an offer, the empty state, the form, an offer's page) | ground |
| `--ink` | `#17191E` | text, the rail's outline, the kind in its pill, the fact values, pressed form chips | 17.6:1 on paper, 14.6:1 on steel |
| `--ink-2` | `#5A606B` | secondary text: an offer's place and date, the fact labels, the count | 6.3:1 on paper, 5.2:1 on steel |
| `--ink-3` | `#6E7480` | control borders, the border of the kind's pill, placeholders, the empty distinction ring; never text | 4.7:1 on paper, 3.9:1 on steel (≥ 3:1 for UI parts) |
| `--steel` | `#E7EAED` | the pass: behind the board and the posting form | ground |
| `--bleu` | `#2038D5` | the one colour, see below | 8.1:1 on paper, 6.7:1 on steel; white on blue 8.1:1 |

The blue appears in exactly six places: the promise clause of the headline, the primary buttons (white on blue), the pressed filter chip, the ball under the current page in the menu, the earned distinction, and the focus rings (2.5px, 8.1:1 on paper, 6.7:1 on steel). In the rail drawing and in the mark, one ticket carries it. Everything else is ink, greys, white and steel.

Decorative only, no text: `--line #D9DDE2`, the hairlines and the 1px border of a card. Light only, one theme; `body` has an explicit white ground.

## Type

- **Bricolage Grotesque** (variable: weight 200–800, width 75–100 %, optical size 12–96) is the voice. The headline is 800 at width 85 %, `clamp(42px, 6.6vw, 96px)`, line-height .95, −0.02em; page titles the same at `clamp(40px, 6vw, 88px)`; section titles 700 at width 88 %, `clamp(28px, 3.2vw, 42px)`; h3 700 at width 90 %, 22px; an offer's role 25px (23px on a phone); inside an offer everything is Bricolage: the kind's pill 600 at 13px, the fact labels 600 at 12px in uppercase (+0.06em), the fact values 500 at 16px with tabular numerals, the restaurant 500 at 17px; lead 450, `clamp(19px, 1.9vw, 22px)`/1.42; body 17px/1.55.
- **DM Mono** (400, 500) labels, and never appears inside an offer: the section eyebrows, the count of offers, the form's section labels and character count, and the figures typed into the form (dates, hours, département, phone). Tier names, the footer line and the legal titles are set in Bricolage, not in uppercase mono.

Both are self-hosted woff2 (latin, latin-ext), OFL, the same files as the poster; nothing loads from a third party.

## Layout

The home page runs in three bands: white (the headline, with the rail drawing beside it), steel (the pass: the filters, then the offers as white cards, one per row across the whole content column, 16px apart; from 960px each card is one row in three columns), white again (for those looking, what an offer says, for restaurants, the distinction, in practice). Sections are separated by a 1px hairline and generous padding (96px above, 104px below on a desktop). The posting form is one white panel on the steel band, its sections split by 1px hairlines. An offer's own page (from `tools/build.py`) is the role as the page title and its card on the steel band below, with the same facts grid as the board.

Radius follows role: paper has none, cards are softened at 12px, controls are round like the balls of the rail, inputs are softened at 8px. No card has a shadow; the only shadow is under the phone menu.

## How an offer reads

An offer is a white card: a 1px `--line` border, radius 12px, no shadow. On a phone it reads top to bottom. First a top line: the kind in a pill (ink text, 1px `--ink-3` border; a demo adds " · exemple"), the place (town · région) in ink-2, and at the far right the publication date, small in ink-2; where that line is short (a phone, or the left column on a desktop) the place drops under the kind and the date. Then the role as the card's title, linked to its own page for a live offer; the restaurant, with the distinction's place right after its name; the facts in a grid (dates, hours, pay, lodging), each a small label above its value, two columns, four once the box is 520px wide, and a fact the offer does not give is simply not shown, except the pay: without it the fact reads “Non communiquée”, with the legal floor for the kind, where there is one, in one ink-2 line under it, on a row of its own; the folded description; and one blue action to write to the restaurant, with the phone as a link beside it when there is one. From 960px the card is one row in three columns: the top line, the role and the restaurant on the left, the facts in the middle, the action on the right, vertically centred; the description folds out under the first two. With no offer (the live state), or none under the chosen filters, the list holds one plain card with the message and the blue button to post an offer.

## The distinction

A blue rosette, awarded beside a restaurant's name, never ranked; a dotted ring while empty. In its own section the rosette is a modest mark (56px, 40px on a phone) set beside the title and aligned with its first line.

## The questionnaire

The same parts, nothing new to learn. Under the page title, two short sections on white paper, two columns from 960px, each a title, one sentence and its details folded behind "En savoir plus". The form is the posting form's white panel on the steel band, its sections split by hairlines; single choices are the same round chips, allowed to wrap onto two lines on a phone, pressed in ink; the months and years are selects with the inputs' border. The blue is only the send button and the focus rings. On the home page it is a secondary button under the distinction's "En savoir plus", on the écoles page a link under the step about the questionnaire, and a link in every footer.

## Motion

Nothing moves on load, and nothing moves on the cards. Under `prefers-reduced-motion: no-preference`, colours ease on hover (0.15s) and in-page links scroll smoothly. That is all.

## What it avoids

No red, no second accent, no gradient washes, no dark ground, no emoji, no centred layout, no decorative 01/02/03, no drop-in or sway, no dashed lines between sections, no big seal, no ticket, torn edge, perforation or shadow on an offer, nothing about the maker outside the legal notice.

## Changed markup, same behaviour

`js/jobs.js` renders an offer as a card (`article.job` holding `.job-head`, `dl.job-facts`, the folded text and `.job-act`) and the empty state as a plain card (`.jobs-empty`); the months strip is gone from `js/jobs.js` and from `tools/build.py`. Every data field, filter, the région select, the count, the apply mail, the phone link, `?demo=1` and expiry are unchanged. `tools/build.py` renders an offer page as the same card on the steel band and still writes the JobPosting data and the sitemap. French copy was moved by script, never retyped; against the poster, the only text-node changes are the three decorative step numerals per page (home, publier, écoles).
