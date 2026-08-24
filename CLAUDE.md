# krausgebaut

## Overview

Sales-facing business website for **krausgebaut** — the internet and IT
services branch of Marcel Kraus's freelance work (https://www.krausgebaut.de).
It presents the services, references and a contact path for acquiring clients.
Positioned as *committed, personal, direct*: one contact person who builds
himself, no agency speak.

German only, no internationalisation: one long homepage plus case studies and
the two legal pages.

It is one of three sites in a brand family, together with marcelkraus (the
personal hub and curriculum vitae) and krausgedruckt (3D printing). Header and
footer are the family bracket and are shared by construction — see *The family
bracket* below.

## Technology stack

- **Backend:** Symfony 8.1, PHP 8.4 (skeleton), Twig
- **Styling:** Tailwind CSS 4 (standalone CLI) with the typography plugin
- **Fonts:** self-hosted — Aller (display + body, static TTF), JetBrains Mono
  (mono / technical labels, variable woff2)
- **Form:** hand-rolled (no symfony/form) + symfony/validator +
  symfony/rate-limiter + CSRF
- **Content:** JSON files in `config/content/`, read with plain `json_decode`
- **Mail:** symfony/mailer; ddev Mailpit in development
- **Logging:** symfony/monolog-bundle (rotating file in prod)
- **Tests:** PHPUnit (`phpunit/phpunit`) with `symfony/browser-kit` and
  `symfony/css-selector`
- **Development:** ddev (apache-fpm, Node 22)

**There is no database at all** — no Doctrine, no `DATABASE_URL`, and
`omit_containers: [db]` in the ddev config so no container starts either.

Deliberately **not** included: Doctrine, symfony/form, symfony/serializer,
EasyAdmin, AssetMapper/Encore/Vite, and a custom error page — a 404 or a 405
renders the plain Symfony page, as on both sibling sites.

## Development

```bash
ddev start                                    # https://krausgebaut.ddev.site
ddev launch -m                                # Mailpit, captured mail
ddev exec npm run build                       # Tailwind, minified
ddev exec npm run dev                         # Tailwind, watch mode
ddev exec php bin/console cache:clear
```

The first `ddev start` needs sudo to add the hostname to `/etc/hosts` — run it
in an interactive shell.

**Rebuild Tailwind after every change to a template or to `input.css`.**
`public/css/output.css` is committed and included via a static `<link>`, so an
un-rebuilt stylesheet ships silently broken. `input.css` sets
`@import "tailwindcss" source(none)` and declares the templates explicitly:
without that, Tailwind scans the whole tree and a stray word in a Markdown file
turns into a CSS rule.

**Never assemble a utility class from a variable.** Tailwind reads the
templates as text, so `bg-{{ token }}` never reaches the build.

### Quality gates (before merging to main)

```bash
ddev exec php bin/console lint:twig templates
ddev exec php bin/console lint:yaml config
ddev exec php bin/console lint:container
ddev exec bash -c 'find src tests -name "*.php" -exec php -l {} \;'
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
`_gear` (mark alone, `currentColor`, decorative), `_badge` (the accent-tinted
label), `_tag_class` (the neutral stack tag as a bare class string),
`_button_class` (the button skin, in two sizes), `_eyebrow` (mono label with
square marker), `_icons` (line-icon macro), `_contact_form`.

## Routing

| Route | Name | Description |
|-------|------|-------------|
| `GET /` | `app_homepage` | Single-page home |
| `GET /referenzen/{slug}` | `app_case_study` | Case study (indexable) |
| `POST /kontakt` | `app_contact` | Form handling (PRG) |
| `GET /kontakt-per-email` | `app_contact_email` | Redirect to `mailto:` |
| `GET /kontakt-per-whats-app` | `app_contact_whats_app` | Redirect to WhatsApp |
| `GET /bewerten` | `app_review` | Redirect to the Google review URL |
| `GET /impressum` | `app_imprint` | Imprint (`noindex,follow`) |
| `GET /datenschutz` | `app_data_privacy` | Privacy policy (`noindex,follow`) |
| `GET /robots.txt` | `app_robots` | robots (absolute sitemap URL) |
| `GET /sitemap.xml` | `app_sitemap` | Sitemap (home + case studies) |

## Design system

A light "spec-sheet" look — technical, precise, no dark mode.

### Colors

The only chromatic color is the petrol accent; everything else is Tailwind's
`neutral-*`, hairlines `neutral-200`. **No hex values in templates** — use the
tokens. All tokens bind to `var(--color-…)` rather than to copied hex values,
so the interface cannot drift from the palette.

**One vocabulary, all three brands.** The role is in the name, so the
difference between the sites falls into the values and not into the naming. The
same five tokens exist on marcelkraus and krausgedruckt under the same names.

| Token | Value | Role |
| --- | --- | --- |
| `accent` | `cyan-800` | the brand: surfaces, borders, markers, the mark — **never type** |
| `accent-on-light` | `cyan-800` | type on a light ground (7.22:1 on white) |
| `accent-on-dark` | `cyan-600` | type on a dark ground |
| `accent-hover` | `cyan-900` | hover of a filled surface |
| `accent-on-light-hover` | `cyan-900` | hover of type on a light ground |

The petrol is a **dark** color: it carries a light ground as it is and misses
AA as type on a dark one (2.74:1), where it needs the brighter step. Two of the
values coincide here and that is fine — the names still say which rule they
answer.

The naming makes the rule checkable: **`text-accent` without a role suffix must
not appear anywhere.**

The hover of a filled surface always moves in whichever direction keeps its
label readable. Here the label is white, so the fill **darkens**; on
krausgedruckt the label is near-black and the fill lightens.

**The footer is a dark ground, so every marker dot there carries an `-on-dark`
step** — the site's own three included.

**The two foreign brands** carry their own tokens, so their colors stay out of
the accent scale. A role step exists only where the base value fails, so a
missing step is information.

| Token | Value | Measured | Used for |
| --- | --- | --- | --- |
| `brand-marcelkraus` | `purple-700` | 2.54:1 on `neutral-900` | the brand, not for the dark footer |
| `brand-marcelkraus-on-dark` | `purple-400` | 6.42:1 | the footer marker dot |
| `brand-krausgedruckt` | `orange-600` | 4.98:1 on `neutral-900`, 5.50:1 on `neutral-950` | marker dot, eyebrow and the cross-promo band |
| `brand-krausgedruckt-hover` | `orange-700` | – | hover of the cross-promo button |

**The gray ramp needs the same care as the accent and has no names for it.**
`neutral-400` carries dark grounds and fails on white (2.58:1); `neutral-500`
passes on white but not on `neutral-100`, and not against a translucent header
over a dark section (3.78:1); `neutral-600` carries every light ground. So:
`neutral-600` for labels on light, `neutral-400` for secondary text on dark.

### Type, shapes, container

- **Typography:** `font-display` = `font-sans` = Aller (wordmark, headlines and
  body, which ties the type to the logo); `font-mono` = JetBrains Mono for
  eyebrows, labels and technical data.
- **Body text is `neutral-600`; the legal pages are `neutral-700`.** The
  declaration on `<body>` said `neutral-700` while every page overrode it, so
  the stated base colour was the one colour the site did not use. It now names
  the normal case. The darker step stays where it belongs — the long reading
  texts of the imprint and the privacy policy, which set it explicitly.
- **The mono scale has three trackings and no fourth.** `0.2em` marks a
  heading role — the eyebrow and the footer's column headings. `0.15em` is
  every other run of capitals: navigation, buttons, tags, micro labels, the
  copyright line. Mixed-case mono carries **none**, because tracking is what
  makes capitals legible and it only loosens everything else. Sizes: `text-sm`
  in the mobile menu, `text-xs` in the normal case, `text-[0.625rem]` for a
  micro label inside a card. There was an eleven-pixel step doing the same job
  as the ten-pixel one; it is gone.
- **Corners scale with the surface:** `rounded-[2px]` for the square markers,
  `rounded-md` for tags and badges, `rounded-lg` for buttons and fields,
  `rounded-xl` for cards and containers. A tag stands around 24 pixels tall,
  where an 8-pixel radius reads as a pill and fights the square marker; six
  pixels at that height is the shape a button has at twice the size.
- **Cards are free-standing:** `rounded-xl border border-neutral-200 bg-white`
  in a `gap-6` grid. Deliberately **no** hairline (`gap-px`) grids.
- **Eyebrow and badge always come from their partial**, never hand-written.
  The badge is driven by the optional `tag` field in the content JSON.
- **Container:** `max-w-6xl mx-auto px-6 lg:px-8`; legal pages and case studies
  `max-w-3xl`. The legal pages clear the fixed header themselves
  (`pt-36 lg:pt-40`) because there is no padding on `main`; those numbers are
  the header height plus the same rhythm the sibling gets from `main.pt-20`, so
  all three sites' legal pages sit at identical measurements. Changing one
  without the others is what put them 16 pixels apart.
- **Section headings on the legal pages** are `font-display`, not mono. Mono is
  reserved for labels and technical data; a heading set in it ended up smaller
  and quieter than the body text it introduced.
- **Tap targets** in the footer carry `py-2.5` (contact rows) and `py-2` (the
  legal links). The contact rows measure 40 pixels and the legal links 32,
  which clears the 24 pixel minimum of WCAG 2.5.8 but not the 44 the header
  aims for.

### The family bracket

**Header and footer are binding.** Position, measurements, grid and behavior
stay identical across all three sites; the content of the lists does not.
Measured at 1440 px: header grid x=144 w=1152 h=80, logo x=176 w=160 h=36,
navigation x=360, footer columns at 176 / 649 / 981.

- **Header:** a three-column grid, not a flex row. The fixed first column
  (`lg:grid-cols-[10rem_1fr_auto]`) starts at `lg`, where the navigation is
  visible and the shared edge is actually perceived; below that the grid is
  `grid-cols-[auto_1fr_auto]`, because reserving 160 pixels for a logo that
  needs less pushed the actions off a 320 pixel screen, which WCAG 1.4.10
  forbids. In a flex row the navigation is squeezed between logo and actions and
  its starting edge follows the width of its own labels — which puts it in a
  different place than on the sibling sites. The fixed first column gives all
  three brands the same edge. The enquiry button stays visible on a phone and
  shrinks to its icon there; hiding it below `sm` left the whole page without a
  call to action on every phone.
- **The mobile menu** closes on the burger, on Escape (focus returns to the
  burger), on a link, on a tap outside and when the viewport grows to desktop.
  The burger swaps to a cross while open — an `aria-label` alone leaves sighted
  users without a signal.
- **The focus ring** follows the same rule as type: on the dark sections it
  takes the brighter step, because the accent misses the 3:1 that WCAG 1.4.11
  asks of an indicator.

### The body

- **Hero:** light (`neutral-50`), no photo, the gear oversized and cropped off
  the right edge as texture, key facts in free-standing chips.
- **Section rhythm:** light → `neutral-100` → light → dark (`Ablauf`) → light →
  dark cross-promo. The one dark block arrives late on purpose. `Leistungen`
  uses a sticky title column on the left and cards on the right; every other
  section stacks its heading above the content.
- **Principle cards** (`Arbeitsweise` section) close on a second paragraph
  holding a one-word promise, preceded by an em dash in the accent color — the
  same marker language as the required-field asterisk in the form. The dash
  comes from the template and the word from the `note` key, so the copy stays
  free of inline markup.
- **Voice:** first person singular ("ich").
- **Place names carry two roles and must not be mixed.** *Where I am* is
  "Erftstadt bei Köln" — title, meta description, hero lead, JSON-LD and the
  imprint. *How far I travel* is "Rhein-Erft-Kreis" — the region chip, the
  footer and the contact panel. The town has to stay, because search engines
  match it against the imprint address; "bei Köln" makes it placeable for
  everyone outside the district.
- **Positioning:** a client-facing sales site, **not** a job application or
  curriculum vitae. Keep employment-framing out of the copy
  (availability-for-hire, remote / on-site flexibility, "seeking"). Speak to
  clients buying a service; the curriculum vitae is marcelkraus.

### Brand mark

The logo in `_logo.html.twig` is gear-brace and wordmark as **one lockup** —
the brace embraces the "k", so the two must not be split. Colors come from
`fill-*` classes: `fill-accent` for gear and "kraus", `fill-neutral-900` for
"gebaut"; `mono: true` renders everything in `fill-current`.

**The wordmark must stay outlined.** A master that keeps it as `<text>` carries
a `font-family` and therefore a dependency the logo is not allowed to have — it
would fall back to a generic sans wherever Aller is absent, and the mark is the
one thing on the page that has to be exact. A curve export is the requirement,
not a compromise.

Master artwork is **not** kept in the repository. Marcel supplies it on demand,
and every shipped asset is derived from it.

### Favicons

Three files at the web root, all derived from the master artwork — a petrol
tile carrying the white gear:

| File | Role |
|------|------|
| `favicon.svg` | primary — scales to any size a browser asks for |
| `favicon.ico` | 16 + 32 px; also answers the implicit `/favicon.ico` request browsers make without a `<link>` |
| `apple-touch-icon.png` | 180×180, iOS home screen |

The SVG **must** keep its `width`/`height` attributes. Without them it has no
intrinsic size, so the browser rasterises it into a default box and scales that
into the tab slot, which puts a pale rim around the tile. Every generated file
is checked for a fully opaque, single-color border before it ships.

The tile is `#005F78`, taken verbatim from the master — and that is exactly
what the `accent` token resolves to. **Artwork and palette agree to the digit,
and they have to stay that way.** Icons follow the artwork, the interface
follows the palette, and the two must not disagree; when the artwork changes,
the icon files are re-derived from it rather than edited.

## Content

`config/content/*.json` is read by `DefaultController::loadContent()` (missing
or malformed → empty list). Editing content needs no code change.

- **services.json** — ordered alphabetically by `title`: `key` (stable
  identifier, not rendered), `title`, `text`, `icon` (a name from `_icons`),
  optional `tag` (badge; `Fokus` marks the two lead services). An entry with
  `feature: true` is lifted out of the tile grid into a set-apart panel below
  it.
- **references.json** — ordered alphabetically by `title`: `slug`, `title`,
  `kunde`, `kategorie`, `summary`, `hatDetailseite` (bool), optional `stack[]`
  (kept alphabetical — the file order is the rendered order), `url` and `tag`.
  With `hatDetailseite` the card links to the internal case study and the entry
  carries its body (`rolle`, `ausgangslage`, `loesung`, `ergebnis`); otherwise
  it links out to `url`. An entry with neither renders `Mehr auf Anfrage` in the
  link slot, so the cards stay aligned. To withdraw a reference, delete the
  entry — there is deliberately no visibility flag, because an unused switch is
  a switch that rots.
- **testimonials.json** — `zitat`, `name`, `rolle`, `firma`. The section renders
  **only when the array is non-empty**; never publish placeholder quotes.

## Contact form

Hand-rolled, handled in `DefaultController::contact()` — the same shape as on
both sibling sites, so a submission takes one path through the family.

- Data → `App\Dto\ContactRequest`, validated with symfony/validator (German
  messages). Fields: name, e-mail, company, phone, message; required are name,
  e-mail and message. Errors keep the **first** violation per field, because the
  constraints are written in order of relevance. There is deliberately no
  subject select — the intent comes from the message, and the mail subject is
  `Nachricht von {Name}`.
- **One required-marker, not two:** the asterisk on the label is the single
  signal (`aria-required` alongside it, plus the `Pflichtfelder *` legend).
  Optional fields carry no marker and no `optional` placeholder — a placeholder
  is the wrong carrier for semantics, it disappears on the first keystroke.
- **Spam protection without a captcha:** a hidden honeypot field (`website`)
  plus a signed, time-boxed timestamp (`ts`/`ts_sig`, HMAC over
  `%kernel.secret%`). Honeypot filled, a missing or tampered signature, or a
  submission under 3 s ⇒ silently dropped (fake success). A valid but expired
  (> 2 h) signature re-renders as a normal error asking the visitor to resend.
- **A transport failure never reaches the visitor as an error page.** `send()`
  is wrapped and answers a `TransportExceptionInterface` through the normal
  form-error path. The sendmail DSN has two documented ways of being wrong on
  this host, and Apache replaces the Symfony error page with its own — without
  the catch the enquiry is lost behind a bare 500.
- **Rate limiting:** `contact_form`, sliding window, 5/hour per IP.
- **CSRF** enabled, token `contact`.
- Success ⇒ mail to `CONTACT_TO` (reply-to = sender), PRG redirect to
  `/#kontakt` with flash `contact_success`. Errors ⇒ home re-renders with status
  422, per-field errors, old input and the first invalid field name
  (`contact_focus`); inline JS focuses it. The error border stays red while the
  field has focus, so the marking does not disappear at the moment it is needed.
- **The address never appears in the markup.** The footer offers an E-Mail and a
  WhatsApp entry, but both point at a route that answers with a redirect; the
  `mailto:` never reaches the page. The imprint and the privacy policy show
  `mail(at)krausgebaut(dot)de` as plain, unlinked text. Do not reintroduce a
  `mailto:` written into the markup.

## SEO / meta

Centralised in `base.html.twig`: canonical, Open Graph, Twitter card, sharing
image `public/images/sharing.jpg` (1200×630). `ProfessionalService` + `Person`
JSON-LD in the homepage `structured_data` block. Subpage title scheme:
`{Page} · krausgebaut von Marcel Kraus`. Case studies are indexable and listed
in the sitemap; legal pages are `noindex`.

The sharing image is a finished asset, not a build product. Should it ever be
redrawn: white, the logo lockup with the `Internet- & IT-Dienstleistungen`
eyebrow on the left, domain and location as a mono line at the bottom, and the
gear oversized and cropped off the right edge in **solid** accent. Type is sized
for a chat card around 320 px wide rather than for the canvas — which is why the
mono lines sit well above their on-site sizes and why the gear is opaque instead
of a tint.

## Analytics

Self-hosted Matomo (**SiteId 13**), inlined in `base.html.twig` behind
`{% if app.environment == 'prod' %}` — dev and test never track. Cookieless
(`disableCookies`) with the visitor IP anonymised server-side, so nothing is
stored on or read from the device and no consent banner is required. Covered by
section 5 of the privacy policy.

## Tests

PHPUnit with browser-kit and css-selector, 47 cases in five files.

- **`RoutingTest`** walks every route: each answers, the legal pages carry
  `noindex`, the redirect routes redirect, and a case study exists only where
  `references.json` says it does — a slug with `hatDetailseite: false` has to
  answer 404, because that card links out instead.
- **`ContentTest`** checks that every service and every reference reaches the
  page, that both files stay alphabetical, and that the testimonials section
  stays hidden while the file is empty. The featured service is the exception
  the sorting rule allows: it leaves the tile grid for a panel of its own and
  sits last in the file.
- **`ContactControllerTest`** pins the form and all three silent drops
  (honeypot, sub-three-second submission, tampered signature), the CSRF
  refusal, the throttle and the transport failure — the last two by replacing
  the service rather than by exhausting it.
- **`SecurityHeadersListenerTest`** asserts the three headers on every public
  path, because the listener fails silently.
- **`AnalyticsTest`** keeps the tracker out of anything but production.

**The rate limiter is injected as `RateLimiterFactoryInterface`**, not as the
concrete class. That is what lets a test put a refusing factory in its place;
with the concrete type the container refuses the replacement and the page
answers 500, which is how the difference was found.

```bash
ddev exec php bin/phpunit
```

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

**Rolling out.** The server directory is a git checkout tracking `origin/main`.
**A push to `main` rolls out by itself**: the workflow runs the gates first and
starts `bin/deploy` over SSH only if they pass, so nothing reaches the server
that has not been linted, tested and built.

The key GitHub authenticates with is restricted in the server's
`authorized_keys` to exactly one command — `cd ~/html/krausgebaut &&
bin/deploy` — with no terminal and no forwarding. A leaked secret can
therefore redeploy the state already on `main`, and nothing else. Withdraw it
by deleting that line on the server; the entries there are labelled so they
can be told apart. Three secrets carry it: `DEPLOY_SSH_KEY`, `DEPLOY_HOST`
and `DEPLOY_USER`.

By hand, unchanged and always available:

```bash
ssh kraus 'cd ~/html/krausgebaut && bin/deploy'
```

That fetches, resets hard, installs the production dependencies, **removes**
`var/cache/prod` and rebuilds it. `public/css/output.css` is committed, so the
server needs no npm run. The reset is hard, but `.env.local`, `vendor/` and
`var/` are ignored and survive it.

The cache is removed rather than cleared, and that is not a detail:
`cache:clear` loads the existing compiled container before it replaces it, so a
release that drops a bundle dies on a class that no longer exists. The script
runs under `set -euo pipefail`, so a failed `composer install` is not followed
by a cache rebuild against half-installed dependencies.

**Repository access.** The server authenticates with a per-repository **deploy
key**. Deploy keys are bound to one repository, and since they all live on
`github.com` the host name cannot tell them apart — so `~/.ssh/config` gives the
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
`DEFAULT_URI=https://www.krausgebaut.de` and the mailer DSN below. The committed
`.env` carries the real addresses as defaults, matching the sibling projects:
`mail@krausgebaut.de` as the recipient, `no-reply@krausgebaut.de` as the sender.
They are no secret — the mailto redirect hands the recipient to anyone who
follows it — and a placeholder that has to be replaced on every machine is a
step that gets forgotten. What stays out of the repository is `.env.local`,
which is where production overrides them.

**Mail.** Delivery goes through the local MTA; the SPF record includes
`spf.uberspace.de`, so no SMTP credentials are needed. `krausgebaut.de` carries
an MX record (`0 menkar.uberspace.de.`). Two traps, both worked around in the
DSN below:

- Plain `sendmail://default` calls `sendmail -bs`, which the qmail wrapper
  rejects with `421 unable to read controls`. The command has to be forced into
  pipe mode.
- The command **must be URL-encoded** inside the DSN. With literal spaces the
  query string is mangled and the mailer fails with `Unsupported sendmail
  command flags`. The contact form catches the transport exception, so the
  visitor gets a readable message — but the mail is still lost, so the DSN has
  to be right.

```
MAILER_DSN="sendmail://default?command=%2Fusr%2Fsbin%2Fsendmail%20-t%20-i"
```

**Headers.** Uberspace adds `Strict-Transport-Security` and forces
`X-Frame-Options: SAMEORIGIN`, overriding the `DENY` that
`SecurityHeadersListener` sets. The server has the last word.

## Logging

Production logs into a **rotating file** under `var/log` (14 days, 7 for
deprecations) instead of the recipe's `php://stderr`, which is not readable from
the Uberspace account and would leave an error without a trace.
`fingers_crossed` at `action_level: error` keeps a normal request silent and
delivers an error together with the requests that led up to it. 404 and 405 are
excluded.

    ssh kraus 'tail -f ~/html/krausgebaut/var/log/prod-*.log'

## Security headers

`App\EventListener\SecurityHeadersListener` sets `X-Content-Type-Options:
nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` and
`X-Frame-Options: DENY` on every main response. No CSP — all JS is inline and
would need nonces.

## Code conventions

- **Comments, identifiers and this documentation are English.** Visible site
  content is German, with correct German quotation marks „…“.
- **No hex color values in templates** — use the design tokens. Standalone
  asset files such as `favicon.svg` may carry hex.

## Environment variables

| Variable | Description |
|----------|-------------|
| `APP_ENV` | Environment (`dev` / `prod`) |
| `APP_SECRET` | Symfony secret (also keys CSRF and the anti-spam timestamp) |
| `MAILER_DSN` | Mail delivery (dev: ddev Mailpit via `.env.local`) |
| `CONTACT_TO` | Recipient of form submissions – real value in `.env.local` |
| `CONTACT_FROM` | Sender address of form mail |
| `GOOGLE_REVIEW_URL` | Redirect target of `/bewerten` |
| `DEFAULT_URI` | Base URL for absolute URLs generated in CLI (sitemap) |
| `APP_SHARE_DIR` | Symfony shared-state directory |

## Open points

1. **Reference cards carry no images.** Neither `references.json` nor the card
   template knows an `image` field. Two usable screenshots exist (OwnYard and
   krausgedruckt); six of the eight references have none, and two illustrated
   cards next to six empty ones look worse than none at all.
2. **The testimonials section stays hidden** until there are real, attributed
   quotes.
3. **The homepage `<title>` is 85 characters and the meta description 219**, so
   both get truncated in search results. Deliberately left as is.
