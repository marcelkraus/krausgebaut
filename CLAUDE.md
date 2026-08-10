# krausgebaut

## Overview

Sales-facing business website for **krausgebaut** – the internet and IT
services branch of Marcel Kraus's freelance work
(https://www.krausgebaut.de). It presents the services, references and a
contact path for acquiring clients. Positioned as *committed, personal,
direct* – one contact person who builds himself, no agency speak.

German only, no internationalisation: one long homepage plus case studies and
the two legal pages.

## Technology stack

- **Backend:** Symfony 8.1, PHP 8.4 (skeleton), Twig
- **Styling:** Tailwind CSS 4 (standalone CLI) with the typography plugin
- **Fonts:** self-hosted – Aller (display + body, static TTF), JetBrains Mono
  (mono / technical labels, variable woff2)
- **Form:** hand-rolled (no symfony/form) + symfony/validator +
  symfony/rate-limiter + CSRF
- **Content:** JSON files in `config/content/`, read with plain `json_decode`
- **Mail:** symfony/mailer; ddev Mailpit in development
- **Logging:** symfony/monolog-bundle (rotating file in prod)
- **Tests:** PHPUnit via symfony/test-pack
- **Development:** ddev (apache-fpm, Node 22)

Deliberately **not** included: Doctrine, symfony/form, symfony/serializer,
EasyAdmin, AssetMapper/Encore/Vite. There is no database (MariaDB only runs
because ddev ships it).

## Development

```bash
ddev start                                    # https://krausgebaut.ddev.site
ddev launch -m                                # Mailpit, captured mail
ddev exec npm run build                       # Tailwind, minified
ddev exec npm run dev                         # Tailwind, watch mode
ddev exec php bin/console cache:clear
```

The first `ddev start` needs sudo to add the hostname to `/etc/hosts` – run it
in an interactive shell.

**Rebuild Tailwind after every change to a template or to `input.css`.**
`public/css/output.css` is committed and included via a static `<link>`, so an
un-rebuilt stylesheet ships silently broken. `input.css` sets
`@import "tailwindcss" source(none)` and declares the templates explicitly:
without that, Tailwind scans the whole tree and a stray word in a Markdown file
turns into a CSS rule.

### Quality gates (before merging to main)

```bash
ddev exec php bin/console lint:twig templates
ddev exec php bin/console lint:yaml config
ddev exec php bin/console lint:container
find src -name '*.php' -exec ddev exec php -l {} \;
ddev exec php bin/phpunit
ddev exec npm run build
```

## Layout

```
config/content/     services.json, references.json, testimonials.json
src/Controller/     DefaultController – all routes, form handling, JSON loading
src/Dto/            ContactRequest – form DTO with validation constraints
src/EventListener/  SecurityHeadersListener
templates/          base.html.twig, default/, partials/
public/             css/, fonts/, images/, favicon.*, apple-touch-icon.png
```

Each partial is the single source for its pattern: `_logo` (brand lockup),
`_gear` (mark alone, `currentColor`, decorative), `_badge` (accent pill),
`_eyebrow` (mono label with square marker), `_icons` (line-icon macro),
`_contact_form`.

## Routing

| Route | Name | Description |
|-------|------|-------------|
| `GET /` | `app_homepage` | Single-page home |
| `GET /referenzen/{slug}` | `app_case_study` | Case study (indexable) |
| `POST /kontakt` | `app_contact` | Form handling (PRG) |
| `GET /impressum` | `app_imprint` | Imprint (`noindex,follow`) |
| `GET /datenschutz` | `app_privacy` | Privacy policy (`noindex,follow`) |
| `GET /robots.txt` | `app_robots` | robots (absolute sitemap URL) |
| `GET /sitemap.xml` | `app_sitemap` | Sitemap (home + case studies) |

## Design system

A light "spec-sheet" look – technical, precise, no dark mode.

- **Colours:** the only chromatic colour is the petrol `accent` token
  (`--color-accent`, plus `accent-hover`); everything else is Tailwind's
  `neutral-*`, hairlines `neutral-200`. **No hex values in templates** – use
  the tokens. The marker dots of the two outbound footer links carry their own
  `brand-*` tokens, so foreign colours stay out of the accent scale.
- **Typography:** `font-display` = `font-sans` = Aller (wordmark, headlines and
  body, which ties the type to the logo); `font-mono` = JetBrains Mono for
  eyebrows, labels and technical data.
- **Corners:** soft on purpose – `rounded-lg` for buttons, fields and tags,
  `rounded-xl` for cards and containers.
- **Cards are free-standing:** `rounded-xl border border-neutral-200 bg-white`
  in a `gap-6` grid. Deliberately **no** hairline (`gap-px`) grids.
- **Eyebrow and badge always come from their partial**, never hand-written.
  The badge is driven by the optional `tag` field in the content JSON.
- **Principle cards** (`Arbeitsweise` section) close on a second paragraph
  holding a one-word promise, preceded by an em dash in the accent colour – the
  same marker language as the required-field asterisk in the form. The dash
  comes from the template and the word from the `note` key, so the copy stays
  free of inline markup.
- **Hero:** light (`neutral-50`), no photo, the gear oversized and cropped off
  the right edge as texture, key facts in free-standing chips.
- **Section rhythm:** light → `neutral-100` → light → dark (`Ablauf`) → light
  → dark cross-promo. The one dark block arrives late on purpose. `Leistungen`
  uses a sticky title column on the left and cards on the right; every other
  section stacks its heading above the content.
- **Container:** `max-w-6xl mx-auto px-6 lg:px-8`; legal pages and case
  studies `max-w-3xl`.
- **Voice:** first person singular ("ich").
- **Place names carry two roles and must not be mixed.** *Where I am* is
  "Erftstadt bei Köln" – title, meta description, hero lead, JSON-LD and the
  imprint. *How far I travel* is "Rhein-Erft-Kreis" – the region chip, the
  footer and the contact panel. The town has to stay, because search engines
  match it against the imprint address; "bei Köln" makes it placeable for
  everyone outside the district.
- **Positioning:** a client-facing sales site, **not** a job application or CV.
  Keep employment-framing out of the copy (availability-for-hire, remote /
  on-site flexibility, "seeking"). Speak to clients buying a service.
- **Brand mark:** the logo in `_logo.html.twig` is gear-brace and wordmark as
  **one lockup** – the brace embraces the "k", so the two must not be split.
  The wordmark is outlined, so the logo carries no font dependency. Colours
  come from `fill-*` classes: `fill-accent` for gear and "kraus",
  `fill-neutral-900` for "gebaut"; `mono: true` renders everything in
  `fill-current`.

Master artwork is **not** kept in the repository. Marcel supplies it on demand,
and every shipped asset is derived from it.

### Favicons

Three files at the web root, all derived from the master artwork – a petrol
tile carrying the white gear:

| File | Role |
|------|------|
| `favicon.svg` | primary – scales to any size a browser asks for |
| `favicon.ico` | 16 + 32 px; also answers the implicit `/favicon.ico` request browsers make without a `<link>` |
| `apple-touch-icon.png` | 180×180, iOS home screen |

The SVG **must** keep its `width`/`height` attributes. Without them it has no
intrinsic size, so the browser rasterises it into a default box and scales that
into the tab slot, which puts a pale rim around the tile. When regenerating the
rasters, check every file for a fully opaque, single-colour border.

The tile is `#015F79`, taken verbatim from the master; the accent token
resolves to `#005F78`. The same brand petrol, one step apart because the token
goes through Tailwind's oklch palette – invisible and deliberate. Icons follow
the artwork, the interface follows the palette; do not "fix" one to match the
other.

## Content

`config/content/*.json` is read by `DefaultController::loadContent()` (missing
or malformed → empty list). Editing content needs no code change.

- **services.json** – ordered alphabetically by `title`: `key` (stable
  identifier, not rendered), `title`, `text`, `icon` (a name from `_icons`),
  optional `tag` (badge; `Fokus` marks the two lead services). An entry with
  `feature: true` is lifted out of the tile grid into a set-apart panel below
  it.
- **references.json** – ordered alphabetically by `title`: `slug`, `title`,
  `kunde`, `kategorie`, `summary`, `hatDetailseite` (bool), optional `stack[]`
  (kept alphabetical – the file order is the rendered order), `url` and `tag`.
  With `hatDetailseite` the card links to the internal case study and the entry
  carries its body (`rolle`, `ausgangslage`, `loesung`, `ergebnis`); otherwise
  it links out to `url`. An entry with neither renders `Mehr auf Anfrage` in
  the link slot, so the cards stay aligned. To withdraw a reference, delete the
  entry – there is deliberately no visibility flag, because an unused switch is
  a switch that rots.
- **testimonials.json** – `zitat`, `name`, `rolle`, `firma`. The section
  renders **only when the array is non-empty**; never publish placeholder
  quotes.

## Contact form

Hand-rolled, handled in `DefaultController::contact()`.

- Data → `App\Dto\ContactRequest`, validated with symfony/validator (German
  messages). Fields: name, e-mail, company, phone, message; required are name,
  e-mail and message. Errors keep the **first** violation per field, because
  the constraints are written in order of relevance. There is deliberately no
  subject select – the intent comes from the message, and the mail subject is
  `Anfrage von {Name}`.
- **One required-marker, not two:** the asterisk on the label is the single
  signal (`aria-required` alongside it, plus the `Pflichtfelder *` legend).
  Optional fields carry no marker and no `optional` placeholder – a placeholder
  is the wrong carrier for semantics, it disappears on the first keystroke.
- **Spam protection without a captcha:** a hidden honeypot field (`website`)
  plus a signed, time-boxed timestamp (`ts`/`ts_sig`, HMAC over
  `%kernel.secret%`). Honeypot filled, a missing or tampered signature, or a
  submission under 3 s ⇒ silently dropped (fake success). A valid but expired
  (> 2 h) signature re-renders as a normal error asking the visitor to resend.
- **Rate limiting:** `contact_form`, sliding window, 5/hour per IP.
- **CSRF** enabled, token `contact`.
- Success ⇒ mail to `CONTACT_TO` (reply-to = sender), PRG redirect to
  `/#kontakt` with flash `contact_success`. Errors ⇒ home re-renders with
  status 422, per-field errors, old input and the first invalid field name
  (`contact_focus`); inline JS focuses it.
- **No address is ever a live link.** The footer and the contact section point
  at the form; the imprint and the privacy policy show
  `mail(at)krausgebaut(dot)de` as plain, unlinked text. Do not reintroduce a
  scripted `mailto` – assembling the address in markup puts it there in a
  trivially scriptable shape and defeats the point.

## SEO / meta

Centralised in `base.html.twig`: canonical, Open Graph, Twitter card, sharing
image `public/images/sharing.jpg` (1200×630). `ProfessionalService` + `Person`
JSON-LD in the homepage `structured_data` block. Subpage title scheme:
`{Page} · krausgebaut von Marcel Kraus`. Case studies are indexable and listed
in the sitemap; legal pages are `noindex`.

The sharing image is a finished asset, not a build product. Should it ever be
redrawn: white, the logo lockup with the `Internet- & IT-Dienstleistungen`
eyebrow on the left, domain and location as a mono line at the bottom, and the
gear oversized and cropped off the right edge in **solid** accent. Type is
sized for a chat card around 320 px wide rather than for the canvas – which is
why the mono lines sit well above their on-site sizes and why the gear is
opaque instead of a tint.

## Analytics

Self-hosted Matomo (**SiteId 13**), inlined in `base.html.twig` behind
`{% if app.environment == 'prod' %}` – dev and test never track. Cookieless
(`disableCookies`) with the visitor IP anonymised server-side, so nothing is
stored on or read from the device and no consent banner is required. Covered by
section 5 of the privacy policy.

## Deployment

Production runs on **Uberspace 7** (`ssh kraus`, CentOS 7, PHP 8.5, Apache) at
https://www.krausgebaut.de.

**Layout.** The application lives in `~/html/krausgebaut`. Uberspace gives every
domain its own document root under `/var/www/virtual/kraus/`, and both
`krausgebaut.de` and `www.krausgebaut.de` are symlinked from there straight to
`html/krausgebaut/public`. The source tree therefore sits *outside* every
document root. Do **not** add a rewrite in `~/html`: that directory is above the
document root and its `.htaccess` is never read. The non-www redirect lives in
`public/.htaccess`.

**Rolling out.** The server directory is a git checkout tracking `origin/main`:

```bash
ssh kraus 'cd ~/html/krausgebaut && bin/deploy'
```

That fetches, resets hard, installs the production dependencies and clears the
cache. `public/css/output.css` is committed, so the server needs no npm run.
The reset is hard, but `.env.local`, `vendor/` and `var/` are ignored and
survive it.

**Repository access.** The server authenticates with a per-repository **deploy
key**. Deploy keys are bound to one repository, and since they all live on
`github.com` the host name cannot tell them apart – so `~/.ssh/config` gives the
project an alias and the remote URL names the alias instead of the real domain:

```
Host github-krausgebaut
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_krausgebaut
    IdentitiesOnly yes
```

`IdentitiesOnly yes` matters: without it ssh offers every key it finds, and a
wrong one first burns the single authentication attempt a deploy key allows.

**`.env.local`** (mode 600, never committed) holds `APP_ENV=prod`,
`APP_DEBUG=0`, a generated `APP_SECRET`, `CONTACT_TO`, `CONTACT_FROM`,
`DEFAULT_URI=https://www.krausgebaut.de` and the mailer DSN below. The
committed `.env` carries a `change-me@example.invalid` placeholder for the
addresses – keeping the real recipient out of the repository follows the same
reasoning as obfuscating it on the legal pages.

**Mail.** Delivery goes through the local MTA; the SPF record includes
`spf.uberspace.de`, so no SMTP credentials are needed. `krausgebaut.de` carries
an MX record (`0 menkar.uberspace.de.`). Two traps, both worked around in the
DSN below:

- Plain `sendmail://default` calls `sendmail -bs`, which the qmail wrapper
  rejects with `421 unable to read controls`. The command has to be forced into
  pipe mode.
- The command **must be URL-encoded** inside the DSN. With literal spaces the
  query string is mangled and the mailer fails with `Unsupported sendmail
  command flags`, surfacing as a bare 500 because Apache replaces the Symfony
  error page.

```
MAILER_DSN="sendmail://default?command=%2Fusr%2Fsbin%2Fsendmail%20-t%20-i"
```

**Headers.** Uberspace adds `Strict-Transport-Security` and forces
`X-Frame-Options: SAMEORIGIN`, overriding the `DENY` that
`SecurityHeadersListener` sets. The server has the last word.

## Logging

Production logs into a **rotating file** under `var/log` (14 days, 7 for
deprecations) instead of the recipe's `php://stderr`, which is not readable
from the Uberspace account and would leave an error without a trace.
`fingers_crossed` at `action_level: error` keeps a normal request silent and
delivers an error together with the requests that led up to it. 404 and 405 are
excluded.

    ssh kraus 'tail -f ~/html/krausgebaut/var/log/prod-*.log'

## Security headers

`App\EventListener\SecurityHeadersListener` sets `X-Content-Type-Options:
nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` and
`X-Frame-Options: DENY` on every main response. No CSP – all JS is inline and
would need nonces.

## Code conventions

- **Comments, identifiers and this documentation are English.** Visible site
  content is German, with correct German quotation marks „…“.
- **No hex colour values in templates** – use the design tokens. Standalone
  asset files such as `favicon.svg` may carry hex.

## Environment variables

| Variable | Description |
|----------|-------------|
| `APP_ENV` | Environment (`dev` / `prod`) |
| `APP_SECRET` | Symfony secret (also keys CSRF and the anti-spam timestamp) |
| `MAILER_DSN` | Mail delivery (dev: ddev Mailpit via `.env.local`) |
| `CONTACT_TO` | Recipient of form submissions – real value in `.env.local` |
| `CONTACT_FROM` | Sender address of form mail |
| `DEFAULT_URI` | Base URL for absolute URLs generated in CLI (sitemap) |
| `APP_SHARE_DIR` | Symfony shared-state directory |

## Open points

1. Add real reference screenshots (optional `image` field).
2. Publish the testimonials section only with real, attributed quotes.
3. The homepage `<title>` is 85 characters and the meta description 219, so
   both get truncated in search results. Deliberately left as is.
