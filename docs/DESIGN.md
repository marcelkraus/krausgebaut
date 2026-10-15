# Design

The light “spec-sheet” look, no dark mode. Tokens, contrast rules and the family bracket are in `../../docs/BRAND_FAMILY.md`; this document names what differs here.

* **Eyebrow and badge always come from their partial.** The badge is driven by the optional `tag` field in the content JSON.
* **Hero:** light (`neutral-50`), no photo, the gear oversized and cropped off the right edge as texture, key facts in free-standing chips.
* **Section rhythm:** light → `neutral-100` → light → dark (`Ablauf`) → light → dark cross-promo; the first dark block arrives late on purpose. `Leistungen` has a sticky title column on the left and cards on the right; every other section stacks its heading above the content.
* **Principle cards** in `Arbeitsweise` close on a one-word promise from the `note` key, preceded by a dash in the accent color that the template sets.
* **Mono micro label inside a card** is `text-[0.625rem]`.
* **Tap targets** in the footer are 40 px (contact rows, `py-2.5`) and 32 px (legal links, `py-2`); the header aims for 44.
* **Reference cards carry no images.**

**Place names carry two roles.** *Where I am* is „Erftstadt bei Köln“ – title, meta description, hero lead, JSON-LD and the imprint, matching the imprint address. *How far I travel* is „Rhein-Erft-Kreis“ – the region chip, the footer and the contact panel.

## Brand mark and favicons

The logo in `_logo.html.twig` is gear-brace and wordmark as one lockup; the brace embraces the „k“. `fill-accent` for gear and „kraus“, `fill-neutral-900` for „gebaut“. The favicon tile is the petrol ground carrying the white gear.

## SEO / meta

Centralized in `base.html.twig`. `ProfessionalService` + `Person` JSON-LD in the homepage `structured_data` block. Subpage title scheme: `{Page} · krausgebaut von Marcel Kraus`. Case studies are indexable and listed in the sitemap.

**The homepage `<title>` (85 characters) and meta description (219) stay as they are**, though search results truncate them.

The sharing image is white: the logo lockup with the `Internet- & IT-Dienstleistungen` eyebrow on the left, domain and location as a mono line at the bottom, the gear oversized and cropped off the right edge in **solid** accent.
