# Content

`config/content/*.json` is read by `DefaultController::loadContent()` (missing or malformed → empty list); file order is rendered order.

* **services.json** is ordered alphabetically by `title`; `Fokus` as `tag` marks the two lead services. An entry with `feature: true` is lifted out of the tile grid into a panel below it and sits last in the file.
* **references.json** is ordered alphabetically by `title`, and `stack[]` stays alphabetical too. With `hatDetailseite` the card links to the internal case study and the entry carries its body; otherwise it links out to `url`; with neither it renders `Mehr auf Anfrage`. **A reference is withdrawn by deleting its entry.**
* **testimonials.json** holds only real quotes, each released by its author. The section renders only when the array is non-empty.
