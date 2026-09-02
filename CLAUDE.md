# krausgebaut

This project lives in the krauswerk, in the group Brand Family, and this
document is not complete on its own. The rules it follows stand a level up –
`../../docs/WEB_STACK.md`, `../../docs/DEPLOYMENT.md` and the group's
`../docs/BRAND_FAMILY.md` – and a session inside the workspace has the
`CLAUDE.md` of the workspace and of the group loaded. Read alone, in a
repository cloned elsewhere, it lacks that context. This document carries only
what is true of this project alone.

## Overview

Sales-facing business website for **krausgebaut** – the internet and IT services
branch of Marcel Kraus's freelance work (https://www.krausgebaut.de). It
presents the services, references and a contact path for acquiring clients.
Positioned as *committed, personal, direct*: one contact person who builds
himself, no agency speak.

German only: one long homepage plus case studies and the two legal pages.

**A client-facing sales site, not a curriculum vitae.** Keep employment framing
out of the copy – availability for hire, remote or on-site flexibility,
„seeking“. Speak to clients buying a service; the curriculum vitae is
marcelkraus.

## Development

```bash
ddev start                                    # https://krausgebaut.ddev.site
ddev launch -m                                # Mailpit, captured mail
ddev exec npm run build                       # Tailwind, minified
ddev exec npm run dev                         # Tailwind, watch mode
```

The first `ddev start` needs sudo to add the hostname to `/etc/hosts` – run it
in an interactive shell.

## Layout

```
config/content/     services.json, references.json, testimonials.json
src/Controller/     DefaultController – all routes, form handling, JSON loading
src/Dto/            ContactRequest
src/EventListener/  SecurityHeadersListener
templates/          base.html.twig, default/, partials/
public/             css/, fonts/, images/, favicon.*, apple-touch-icon.png
```

Partials: `_logo`, `_gear` (mark alone, `currentColor`, decorative), `_badge`
(the accent-tinted label), `_tag_class` (the neutral stack tag as a bare class
string), `_button_class` (two sizes), `_eyebrow`, `_icons`, `_contact_form`.

**Eyebrow and badge always come from their partial**, never hand-written. The
badge is driven by the optional `tag` field in the content JSON.

## Routing

| # | Route | Name | Description |
|---|-------|------|-------------|
| 1 | `GET /` | `app_homepage` | Single-page home |
| 2 | `GET /referenzen/{slug}` | `app_case_study` | Case study (indexable) |
| 3 | `POST /kontakt` | `app_contact` | Form handling (PRG) |
| 4 | `GET /kontakt-per-email` | `app_contact_email` | Redirect to `mailto:` |
| 5 | `GET /kontakt-per-whats-app` | `app_contact_whats_app` | Redirect to WhatsApp |
| 6 | `GET /bewerten` | `app_review` | Redirect to the Google review URL |
| 7 | `GET /impressum` | `app_imprint` | Imprint (`noindex,follow`) |
| 8 | `GET /datenschutz` | `app_data_privacy` | Privacy policy (`noindex,follow`) |
| 9 | `GET /robots.txt` | `app_robots` | robots, absolute sitemap URL |
| 10 | `GET /sitemap.xml` | `app_sitemap` | Sitemap (home + case studies) |

## Design

The light "spec-sheet" look, no dark mode. Tokens, contrast rules and the family
bracket are in `../docs/BRAND_FAMILY.md`.

**What this site does differently:**

* **Hero:** light (`neutral-50`), no photo, the gear oversized and cropped off
  the right edge as texture, key facts in free-standing chips.
* **Section rhythm:** light → `neutral-100` → light → dark (`Ablauf`) → light →
  dark cross-promo. The first dark block arrives late on purpose.
  `Leistungen` uses a sticky title column on the left and cards on the right;
  every other section stacks its heading above the content.
* **Principle cards** in the `Arbeitsweise` section close on a second paragraph
  holding a one-word promise, preceded by a dash in the accent color – the same
  marker language as the required-field asterisk. The dash comes from the
  template and the word from the `note` key, so the copy stays free of inline
  markup.
* **Mono micro label inside a card** is `text-[0.625rem]`.
* **Tap targets** in the footer carry `py-2.5` (contact rows) and `py-2` (legal
  links), measuring 40 and 32 pixels. That clears the 24 pixel minimum of WCAG
  2.5.8 but not the 44 the header aims for.
* **Voice:** first person singular („ich“).

**Place names carry two roles and must not be mixed.** *Where I am* is
„Erftstadt bei Köln“ – title, meta description, hero lead, JSON-LD and the
imprint. *How far I travel* is „Rhein-Erft-Kreis“ – the region chip, the footer
and the contact panel. The town has to stay, because search engines match it
against the imprint address; „bei Köln“ makes it placeable for everyone outside
the district.

## Brand mark and favicons

The logo in `_logo.html.twig` is gear-brace and wordmark as one lockup – **the
brace embraces the „k“, so the two must not be split.** `fill-accent` for gear
and „kraus“, `fill-neutral-900` for „gebaut“.

The favicon tile is the petrol ground carrying the white gear.

## Content

`config/content/*.json` is read by `DefaultController::loadContent()` (missing
or malformed → empty list). Editing content needs no code change.

* **services.json** – ordered alphabetically by `title`: `key` (stable
  identifier, not rendered), `title`, `text`, `icon` (a name from `_icons`),
  optional `tag` (badge; `Fokus` marks the two lead services). An entry with
  `feature: true` is lifted out of the tile grid into a set-apart panel below
  it and sits last in the file – the one exception the sorting rule allows.
* **references.json** – ordered alphabetically by `title`: `slug`, `title`,
  `kunde`, `kategorie`, `summary`, `hatDetailseite` (bool), optional `stack[]`
  (kept alphabetical – file order is rendered order), `url`, `tag`. With
  `hatDetailseite` the card links to the internal case study and the entry
  carries its body (`rolle`, `ausgangslage`, `loesung`, `ergebnis`); otherwise
  it links out to `url`. An entry with neither renders `Mehr auf Anfrage` in the
  link slot, so the cards stay aligned. **To withdraw a reference, delete the
  entry** – there is deliberately no visibility flag, because an unused switch
  is a switch that rots.
* **testimonials.json** – `zitat`, `name`, `rolle`, `firma`. The section renders
  **only when the array is non-empty**; never publish placeholder quotes.

## Contact form

The mechanism is in `../../docs/WEB_STACK.md`. Specific here:

* Fields: name, e-mail, company, phone, message; required are name, e-mail and
  message. **There is deliberately no subject select** – the intent comes from
  the message, and the mail subject is `Nachricht von {Name}`.
* The legal mailbox is `mail+legal@krausgebaut.de`.

## SEO / meta

Centralised in `base.html.twig`. `ProfessionalService` + `Person` JSON-LD in the
homepage `structured_data` block. Subpage title scheme:
`{Page} · krausgebaut von Marcel Kraus`. Case studies are indexable and listed
in the sitemap.

Sharing image composition: white, the logo lockup with the
`Internet- & IT-Dienstleistungen` eyebrow on the left, domain and location as a
mono line at the bottom, and the gear oversized and cropped off the right edge
in **solid** accent.

## Tests

47 cases in five files.

| # | File | Covers |
|---|------|--------|
| 1 | `RoutingTest` | every route answers; legal pages carry `noindex`; the redirect routes redirect; a case study exists only where `references.json` says it does – a slug with `hatDetailseite: false` has to answer 404, because that card links out |
| 2 | `ContentTest` | every service and reference reaches the page, both files stay alphabetical, the testimonials section stays hidden while the file is empty |
| 3 | `ContactControllerTest` | the form and all three silent drops, the CSRF refusal, the throttle and the transport failure – the last two by replacing the service rather than exhausting it |
| 4 | `SecurityHeadersListenerTest` | the three headers on every public path, because the listener fails silently |
| 5 | `AnalyticsTest` | the tracker stays out of anything but production |

## Deployment

Server directory `~/www/html/krausgebaut`, on the account `krswrk`, host
`nix`. Mechanism, deploy keys and the mailer are in `../../docs/DEPLOYMENT.md`.

**Layout on the host:** both `krausgebaut.de` and `www.krausgebaut.de` are
symlinked from `~/www/` straight to `html/krausgebaut/public/`.

**The mail stayed where it was.** The MX record points at `menkar`, and the
domain is registered for mail on the account `kraus` – so it must **never** be
registered for mail on the account the site runs on. The local MTA would then
treat it as local, and `mail@krausgebaut.de` would land in a mailbox nobody
reads instead of reaching the MX. Sending is unaffected: SPF authorizes every
Uberspace host.

**`analytics.krausgebaut.de` is not served from here.** It stands on the same
account now, but on a document root of its own: the measurement belongs to no
repository, and `bin/deploy` never touches it. The domain is where it is named,
not where it runs – which is why it is written down here and nowhere in this
project's code.

The committed `.env` carries the real addresses as defaults –
`mail@krausgebaut.de` as recipient, `no-reply@krausgebaut.de` as sender. They
are no secret, since the mailto redirect hands the recipient to anyone who
follows it, and a placeholder that has to be replaced on every machine is a step
that gets forgotten. What stays out of the repository is `.env.local`.

## Environment variables

| # | Variable | Description |
|---|----------|-------------|
| 1 | `APP_ENV` | Environment (`dev` / `prod`) |
| 2 | `APP_SECRET` | Symfony secret, also keys CSRF and the anti-spam timestamp |
| 3 | `MAILER_DSN` | Mail delivery |
| 4 | `CONTACT_TO` | Recipient of form submissions |
| 5 | `CONTACT_FROM` | Sender address of form mail |
| 6 | `DEFAULT_URI` | Base URL for absolute URLs generated in CLI (sitemap) |
| 7 | `APP_SHARE_DIR` | Symfony shared-state directory |

The Google review URL is **not** an environment variable – it is
`app.google_review_url` in `config/services.yaml`, read with `getParameter()`.

## Open points

1. **Reference cards carry no images.** Neither `references.json` nor the card
   template knows an `image` field. Two usable screenshots exist; six of the
   eight references have none, and two illustrated cards next to six empty ones
   look worse than none at all.
2. **The testimonials section stays hidden** until there are real, attributed
   quotes.
3. **The homepage `<title>` is 85 characters and the meta description 219**, so
   both get truncated in search results. Deliberately left as is.
