# Deployment

Server directory `~/www/html/krausgebaut`, on the account `krswrk`, host `nix`. Mechanism, deploy keys and the mailer are in `../../../docs/DEPLOYMENT.md`.

* **Both `krausgebaut.de` and `www.krausgebaut.de` are symlinked** from `~/www/` straight to `html/krausgebaut/public/`.
* **The mail is on the same account.** The MX record points at `in-mx.uberspace.de` and `krausgebaut.de` is registered for mail on `krswrk`, so the contact form delivers locally to `mail@krausgebaut.de`. The server sends as the shared sender – „The sender on `krswrk`“ in the workspace document; the `no-reply@krausgebaut.de` in the committed `.env` is the ddev default.
* **`analytics.krausgebaut.de` is served from its own document root** on the same account and belongs to no repository; `bin/deploy` never touches it.
* **The committed `.env` carries the real addresses as defaults**; secrets go into `.env.local`.
