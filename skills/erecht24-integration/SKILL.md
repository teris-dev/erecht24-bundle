---
name: erecht24-integration
description: Integrate teris-dev/erecht24-bundle into a Symfony app so the eRecht24 imprint (Impressum) and privacy policy (Datenschutzerklärung) update automatically via push & pull. Use when the user wants eRecht24 legal texts, an automatically updated Impressum/Datenschutz, or asks to install or configure the erecht24 bundle, in single-site or multi-tenant setups.
---

# eRecht24 bundle integration

Full reference: `vendor/teris-dev/erecht24-bundle/AGENTS.md` (read it once the package is
installed; otherwise https://github.com/teris-dev/erecht24-bundle/blob/main/AGENTS.md).

## Workflow

1. **Clarify** – single site (API key in `.env.local`) or multi-tenant (API keys in the
   database)? Where should texts be stored and rendered? Which texts (`erecht24.documents`,
   default imprint + privacyPolicy)? Public base URL? Do not invent API keys.
2. **Install** – `composer require teris-dev/erecht24-bundle`; ensure the bundle is in
   `config/bundles.php`; add `config/routes/erecht24.yaml` importing
   `@ERecht24Bundle/config/routes.php` with prefix `/erecht24`; exempt `^/erecht24/push` from
   any firewall/access_control; set `DEFAULT_URI=https://<public host>` (feeds
   `framework.router.default_uri`, from which the push URL is generated).
3. **Configure**
   - Single: `erecht24.api_key|client_id|client_secret` from env vars (empty defaults in
     `.env`), render with `{{ erecht24_document('imprint', app.request.locale) }}` (no `|raw`).
   - Multi-tenant: implement `ConnectionProviderInterface` and `LegalDocumentHandlerInterface`
     (optionally `MessageHandlerInterface`), set `erecht24.connection_provider` and
     `erecht24.document_handler` to their service IDs, store `apiKey`, `clientId`, `secret`
     per tenant, call `ClientRegistrar::register()` + `Synchronizer::syncAll()` when a key is
     saved and `ClientRegistrar::unregister()` before it is removed. Classes live in
     `Teris\ERecht24Bundle\{Connection,Document,Model}` – copy the skeleton from AGENTS.md.
     Render stored HTML with `|raw` (trusted eRecht24 content).
4. **Verify** – `bin/console lint:container`, `bin/console debug:router erecht24_push`,
   `bin/console erecht24:sync [connectionId]`; tests use `MockHttpClient`, never the real API.
5. **Hand over** – tell the user to run `bin/console erecht24:client:register` (then store the
   printed ID/secret) and `bin/console erecht24:client:test-push --type=ping` on the public
   server, and that eRecht24 allows only 3 push clients per project.

## Don't

- write an own push controller, secret check or eRecht24 HTTP client
- commit API keys/secrets or put a customer API key into `erecht24.plugin_key`
- call the eRecht24 API during page rendering in multi-tenant mode
- forget to persist the new secret after re-registering (pushes then fail with 403)
