# krausgebaut

Sales website for **krausgebaut**, Marcel Kraus's internet and IT services (https://www.krausgebaut.de): services, references and a contact path for acquiring clients. *Committed, personal, direct*—one contact person who builds himself, no agency speak. German only: one long homepage, case studies and the two legal pages.

## Rules

* **The site speaks to clients buying a service**, in the first person, addressing them as “Sie”.
* **The content lives in `config/content/*.json`**; editing it needs no code change.
* **The contact form asks for name, e-mail, company, phone and message**; name, e-mail and message are required. The intent comes from the message, so there is no subject select; the mail subject is `Nachricht von {Name}`. The mechanism is in `../../docs/WEB_STACK.md`, the legal mailbox is `mail+legal@krausgebaut.de`.
* **A printed piece answers under `/s/<prefix>-<code>`** and leads to the homepage; the scheme is in `../../docs/WORKSPACE.md` › Short links, this site's codes in `DefaultController::PRINT_CAMPAIGNS`, `PRINT_EDITIONS` and `PRINT_SOURCES`.
* **The first `ddev start` needs sudo** for `/etc/hosts`—run it in an interactive shell. The Tailwind build is `ddev exec npm run build`.

## Documents

| # | Document | Read before |
| --- | --- | --- |
| 1 | `docs/DESIGN.md` | touching a template, the stylesheet, the logo, a favicon, the sharing image or the head data |
| 2 | `docs/CONTENT.md` | editing a content file |
| 3 | `docs/DEPLOYMENT.md` | touching the server, a hostname or the mail |

## Agent skills

Issues and specs live as local markdown files under `.scratch/` (`docs/agents/issue-tracker.md`). Triage uses the five canonical roles (`docs/agents/triage-labels.md`). Domain docs are single-context: one `GLOSSARY.md` and `docs/adr/` at the repository root (`docs/agents/domain.md`).
