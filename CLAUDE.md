# krausgebaut

## Overview

Sales-facing business website for **krausgebaut**—the internet and IT services branch of Marcel Kraus's freelance work (https://www.krausgebaut.de). It presents the services, references and a contact path for acquiring clients. Positioned as *committed, personal, direct*: one contact person who builds himself, no agency speak.

German only: one long homepage plus case studies and the two legal pages.

**A client-facing sales site, not a curriculum vitae.** Speak to clients buying a service; availability for hire, remote or on-site flexibility and “seeking” belong to marcelkraus.

## Development

The first `ddev start` needs sudo to add the hostname to `/etc/hosts`—run it in an interactive shell. The Tailwind build is `ddev exec npm run build`.

## Short links

**A printed piece answers under `/s/<prefix>-<code>`** and leads to the homepage, counted as print under its campaign. The scheme is the workspace's, in `../../docs/WORKSPACE.md`, “Short links”; this site lists `DefaultController::PRINT_CAMPAIGNS`, `PRINT_EDITIONS` and `PRINT_SOURCES`. The address answers 302 because what is already printed must be free to lead elsewhere.

## Design

The light “spec-sheet” look, no dark mode. Tokens, contrast rules and the family bracket are in `../docs/BRAND_FAMILY.md`.

**Eyebrow and badge always come from their partial.** The badge is driven by the optional `tag` field in the content JSON.

**What this site does differently:**

* **Hero:** light (`neutral-50`), no photo, the gear oversized and cropped off the right edge as texture, key facts in free-standing chips.
* **Section rhythm:** light → `neutral-100` → light → dark (`Ablauf`) → light → dark cross-promo. The first dark block arrives late on purpose. `Leistungen` uses a sticky title column on the left and cards on the right; every other section stacks its heading above the content.
* **Principle cards** in the `Arbeitsweise` section close on a second paragraph holding a one-word promise, preceded by a dash in the accent color—the same marker language as the required-field asterisk. The dash comes from the template and the word from the `note` key, so the copy stays free of inline markup.
* **Mono micro label inside a card** is `text-[0.625rem]`.
* **Tap targets** in the footer carry `py-2.5` (contact rows) and `py-2` (legal links), measuring 40 and 32 pixels. That clears the 24 pixel minimum of WCAG 2.5.8 but not the 44 the header aims for.
* **Reference cards carry no images.** Two usable screenshots exist; six of the eight references have none, and two illustrated cards next to six empty ones look worse than none at all.
* **Voice:** first person singular (“ich”).

**Place names carry two roles, each in its own places.** *Where I am* is “Erftstadt bei Köln”—title, meta description, hero lead, JSON-LD and the imprint. *How far I travel* is “Rhein-Erft-Kreis”—the region chip, the footer and the contact panel. The town has to stay, because search engines match it against the imprint address; “bei Köln” makes it placeable for everyone outside the district.

## Brand mark and favicons

The logo in `_logo.html.twig` is gear-brace and wordmark as one lockup—**the brace embraces the “k,” so the lockup stays whole.** `fill-accent` for gear and “kraus,” `fill-neutral-900` for “gebaut.”

The favicon tile is the petrol ground carrying the white gear.

## Content

`config/content/*.json` is read by `DefaultController::loadContent()` (missing or malformed → empty list). Editing content needs no code change.

* **services.json** is ordered alphabetically by `title`; `Fokus` as `tag` marks the two lead services. An entry with `feature: true` is lifted out of the tile grid into a set-apart panel below it and sits last in the file—the one exception the sorting rule allows.
* **references.json** is ordered alphabetically by `title`, and `stack[]` stays alphabetical too—file order is rendered order. With `hatDetailseite` the card links to the internal case study and the entry carries its body; otherwise it links out to `url`. An entry with neither renders `Mehr auf Anfrage` in the link slot, so the cards stay aligned. **To withdraw a reference, delete the entry**—there is deliberately no visibility flag, because an unused switch is a switch that rots.
* **testimonials.json** holds only real quotes, each released by its author. The section renders only when the array is non-empty.

## Contact form

The mechanism is in `../../docs/WEB_STACK.md`. Specific here:

* Fields: name, e-mail, company, phone, message; required are name, e-mail and message. **The intent comes from the message**, so there is deliberately no subject select, and the mail subject is `Nachricht von {Name}`.
* The legal mailbox is `mail+legal@krausgebaut.de`.

## SEO / meta

Centralized in `base.html.twig`. `ProfessionalService` + `Person` JSON-LD in the homepage `structured_data` block. Subpage title scheme: `{Page} · krausgebaut von Marcel Kraus`. Case studies are indexable and listed in the sitemap.

**The homepage `<title>` is 85 characters and the meta description 219**, so both get truncated in search results. Deliberately left as is.

Sharing image composition: white, the logo lockup with the `Internet- & IT-Dienstleistungen` eyebrow on the left, domain and location as a mono line at the bottom, and the gear oversized and cropped off the right edge in **solid** accent.

## Deployment

Server directory `~/www/html/krausgebaut`, on the account `krswrk`, host `nix`. Mechanism, deploy keys and the mailer are in `../../docs/DEPLOYMENT.md`.

**Layout on the host:** both `krausgebaut.de` and `www.krausgebaut.de` are symlinked from `~/www/` straight to `html/krausgebaut/public/`.

**The mail is on the same account.** The MX record points at `in-mx.uberspace.de`, and `krausgebaut.de` is registered for mail on `krswrk`, so `mail@krausgebaut.de` is a mailbox of that account and the contact form delivers locally. The sender is not this domain—see “The sender on `krswrk`” in `../../docs/DEPLOYMENT.md`.

**`analytics.krausgebaut.de` is served from its own document root** on the same account: the measurement belongs to no repository, and `bin/deploy` never touches it. The domain is where it is named, not where it runs—which is why it is written down here and nowhere in this project's code.

The committed `.env` carries the real addresses as defaults—`mail@krausgebaut.de` as recipient, `no-reply@krausgebaut.de` as sender. They are no secret, since the mailto redirect hands the recipient to anyone who follows it, and a placeholder that has to be replaced on every machine is a step that gets forgotten. Secrets go into `.env.local`.

## Agent skills

### Issue tracker

Issues and specs live as local markdown files under `.scratch/`. See `docs/agents/issue-tracker.md`.

### Triage labels

The five canonical roles, each label equal to its name. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `CONTEXT.md` and `docs/adr/` at the repository root. See `docs/agents/domain.md`.
