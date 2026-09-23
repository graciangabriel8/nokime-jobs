# Nokime Jobs — le passe bleu

## Thesis

The board is the pass of a kitchen: every offer is a paper ticket held on a steel rail by a ball, because a cook already reads a ticket at a glance. Around the board the page is the poster: white paper, black ink, one blue, hairlines and a lot of air. The object gives the site its character; the poster keeps it calm.

## Where it comes from

Two versions were compared side by side. The red one (le passe) was found interesting but too much; the blue one (the poster) calm but shy. This version keeps the object from the first and the voice and restraint of the second.

- **From le passe**, because it is what makes the site not shy: the offers as tickets hanging from the rail on a steel band, with a torn foot; the facts as ticket lines, label left and figure right; the J–D months strip; the blank ticket on the rail when there is no offer; the posting form as one wide ticket on the same rail; the rail drawing beside the headline; the steps drawn as a line through three balls; round buttons and chips; the two-tickets-under-a-rail mark; the dotted ring beside each restaurant name where the distinction goes.
- **From the poster**, because it is what makes it calm: white paper everywhere outside the board; one ultramarine, `#2038D5`, as the only colour; Bricolage Grotesque and DM Mono; the confident headline (800, width 85 %); nothing moving on load.
- **Toned down from le passe**, because it is what made it too much: the red is gone entirely; the kind printed on a ticket is ink; the drop-in and the sway are gone; the perforations between page sections became hairlines; the large rosette of the distinction section became a small mark beside its text; fewer uppercase mono labels outside the tickets.

## Palette

Every colour is a token on `:root` in `css/site.css`. Ratios are WCAG 2 contrast ratios, computed from the hex values.

| Token | Hex | Role | Contrast |
|---|---|---|---|
| `--paper` | `#FFFFFF` | the page, the tickets, the order pad | ground |
| `--ink` | `#17191E` | text, the rail's outline, lit months, pressed form chips | 17.6:1 on paper, 14.6:1 on steel, 16.0:1 on wash |
| `--ink-2` | `#5A606B` | secondary text, the labels printed on tickets | 6.3:1 on paper, 5.2:1 on steel, 5.7:1 on wash |
| `--ink-3` | `#6E7480` | control borders, placeholders, the empty distinction ring; never text on steel | 4.7:1 on paper, 3.9:1 on steel (≥ 3:1 for UI parts) |
| `--steel` | `#E7EAED` | the pass: behind the board and the posting form | ground |
| `--wash` | `#F2F4F6` | the unlit months | ground |
| `--bleu` | `#2038D5` | the one colour, see below | 8.1:1 on paper, 6.7:1 on steel, 7.4:1 on wash; white on blue 8.1:1 |

The blue appears in exactly six places: the promise clause of the headline, the primary buttons (white on blue), the pressed filter chip, the ball under the current page in the menu, the earned distinction, and the focus rings (2.5px, 8.1:1 on paper, 6.7:1 on steel). In the rail drawing and in the mark, one ticket carries it. Everything else is ink, greys, white and steel.

Decorative only, no text: `--line #D9DDE2` (hairlines), `--perf #A3A9B1` (perforations inside a ticket), the three rail greys. Light only, one theme; `body` has an explicit white ground.

## Type

- **Bricolage Grotesque** (variable: weight 200–800, width 75–100 %, optical size 12–96) is the voice. The headline is 800 at width 85 %, `clamp(42px, 6.6vw, 96px)`, line-height .95, −0.02em; page titles the same at `clamp(40px, 6vw, 88px)`; section titles 700 at width 88 %, `clamp(28px, 3.2vw, 42px)`; h3 700 at width 90 %, 22px; the offer's role 24px; lead 450, `clamp(19px, 1.9vw, 22px)`/1.42; body 17px/1.55.
- **DM Mono** (400, 500) is the printer: whatever a ticket printer would print — the kind, the publication date, the fact labels and figures, the months, the phone number on a ticket — plus the section eyebrows, the count and the form's section labels (the form is itself a ticket). Outside the tickets, tier names, the footer line and the legal titles are set in Bricolage, not in uppercase mono.

Both are self-hosted woff2 (latin, latin-ext), OFL, the same files as the poster; nothing loads from a third party.

## Layout

The home page runs in three bands: white (the headline, with the rail drawing beside it), steel (the pass: filters and tickets, three to a row on a desktop, two on a tablet, one on a phone), white again (for those looking, what an offer says, for restaurants, the distinction, in practice). Sections are separated by a 1px hairline and generous padding (96px above, 104px below on a desktop). Perforations only appear inside a ticket and at its torn foot. The posting form is one wide ticket hung on the same rail. An offer's own page (from `tools/build.py`) is the role as the page title and its ticket hung below.

Radius follows role: paper has none, controls are round like the balls of the rail, inputs are softened at 8px. The only shadow is the paper's, on tickets.

## How an offer reads

Top to bottom, like a ticket: the kind in ink and the publication date, a perforation, the role, the restaurant with the distinction's place beside it, the town and the région, a perforation, the facts as ticket lines (dates, hours, pay, lodging), the twelve months with the covered ones lit, the folded description, and one blue action to write to the restaurant, with the phone under it when there is one. With no offer (the live state) the rail stays up with one blank ticket on it carrying the empty message and the link to post an offer.

## The distinction

A blue rosette, awarded beside a restaurant's name, never ranked; a dotted ring while empty. In its own section the rosette is a modest mark (56px, 40px on a phone) set beside the title and aligned with its first line.

## Motion

Nothing moves on load. Under `prefers-reduced-motion: no-preference` and `hover: hover`, a ticket under the pointer lifts 2px and its shadow deepens slightly. Colours ease on hover (0.15s). That is all.

## What it avoids

No red, no second accent, no gradient washes, no dark ground, no emoji, no centred layout, no decorative 01/02/03, no drop-in or sway, no dashed lines between sections, no big seal, nothing about the maker outside the legal notice.

## Changed markup, same behaviour

`js/jobs.js` renders the ticket (`.job > .hang > .slip`) and the empty state as a blank ticket; every data field, filter, the région select, the count, the apply mail, the phone link, `?demo=1` and expiry are unchanged (the per-ticket animation delay is gone). `tools/build.py` renders an offer page as a ticket on the pass and still writes the JobPosting data and the sitemap. French copy was moved by script, never retyped; against the poster, the only text-node changes are the three decorative step numerals per page (home, publier, écoles).
