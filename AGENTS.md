# AGENTS.md – teris-dev/erecht24-bundle

Guide for AI coding agents that **integrate this bundle into a Symfony application**
(and, at the end, for agents working on the bundle itself). Human documentation:
`README.md` (German) and `docs/`.

## What the bundle does

eRecht24 (German legal-text generator) keeps a customer's imprint ("Impressum") and
privacy policy ("Datenschutzerklärung"), each in German and English. When the customer
clicks "Synchronisieren" in the eRecht24 Projekt-Manager, eRecht24 sends a **push**
(only a notification: secret + document type) to a URL of the application. The bundle
verifies the secret, **pulls** the text via the eRecht24 API and hands it to the
application.

Requirements: PHP ≥ 8.2, Symfony 7.4 or 8.x, `symfony/http-client`. Twig optional.

## Step 0 – Ask before you build

Ask the user (or state your assumption if you cannot ask):

1. **Mode** – one website per application (**single mode**, API key in `.env.local`) or
   many websites/tenants in one application (**multi-tenant**, API keys in the database)?
2. **Where should the texts live?** Single mode default: built-in cache store + Twig
   function. Multi-tenant: usually the application's own pages/entities.
3. **Which texts?** Default `imprint` + `privacyPolicy`. `privacyPolicySocialMedia` is
   optional and only works if the customer created it at eRecht24. Configure with
   `erecht24.documents: [imprint, privacyPolicy]` (pushes for other types are answered with 422).
4. **Public base URL** of the application (needed to register the push endpoint).

Never invent an eRecht24 API key. For development use the public demo key
`e81cbf18a5239377aa4972773d34cc2b81ebc672879581bce29a0a4c414bf117` (pull works; push
registration does **not**, its 3 client slots are taken).

## Step 1 – Install (both modes)

```bash
composer require teris-dev/erecht24-bundle
```

No Flex recipe exists yet, so do by hand what a recipe would do:

1. `config/bundles.php`: `Teris\ERecht24Bundle\ERecht24Bundle::class => ['all' => true],`
   (Flex usually adds this automatically on `composer require`; check before adding.)
2. `config/routes/erecht24.yaml`:
   ```yaml
   erecht24:
       resource: '@ERecht24Bundle/config/routes.php'
       prefix: /erecht24
   ```
3. If `symfony/security-bundle` is installed: the push route must be reachable without
   login. Add a firewall **before** the main firewall:
   ```yaml
   security:
       firewalls:
           erecht24:
               pattern: ^/erecht24/push
               security: false
   ```
   Also make sure no `access_control` rule requires a role for `^/erecht24/push`.
4. The console generates the push URL from `framework.router.default_uri`. The skeleton
   wires it to `'%env(DEFAULT_URI)%'` with the default `http://localhost` – set
   `DEFAULT_URI=https://<public host>` in `.env.local` / the production environment,
   otherwise eRecht24 is given an unreachable push URL.

Look up the full configuration with `bin/console config:dump-reference erecht24`.

## Step 2a – Single mode

```yaml
# config/packages/erecht24.yaml
erecht24:
    api_key: '%env(ERECHT24_API_KEY)%'
    client_id: '%env(ERECHT24_CLIENT_ID)%'
    client_secret: '%env(ERECHT24_CLIENT_SECRET)%'
```

```dotenv
# .env (committed, defaults only – never real keys)
ERECHT24_API_KEY=
ERECHT24_CLIENT_ID=
ERECHT24_CLIENT_SECRET=
```

Real values go into `.env.local` or `bin/console secrets:set`. Templates:

```twig
{{ erecht24_document('imprint', app.request.locale) }}
{{ erecht24_document('privacyPolicy', app.request.locale) }}
{% set doc = erecht24_legal_document('imprint') %}{# LegalDocument|null, e.g. doc.modifiedAt #}
```

`erecht24_document()` already returns safe HTML – do **not** add `|raw`, do not escape it.
Any locale not starting with `de` gets English; empty English falls back to German.

The **user** (not you, unless they ask) then runs on the production server:

```bash
bin/console erecht24:client:register        # prints ERECHT24_CLIENT_ID / _SECRET → .env.local
bin/console erecht24:client:test-push --type=ping
bin/console erecht24:client:test-push --type=imprint
```

## Step 2b – Multi-tenant mode

Implement two interfaces (one class may implement both) and reference them by service ID:

```yaml
# config/packages/erecht24.yaml
erecht24:
    connection_provider: App\LegalText\ERecht24Connections   # ConnectionProviderInterface
    document_handler: App\LegalText\ERecht24Documents         # LegalDocumentHandlerInterface
```

Skeleton with all imports (adapt the storage calls to your entities/repositories):

```php
namespace App\LegalText;

use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Teris\ERecht24Bundle\Document\DocumentManagedLocallyException;
use Teris\ERecht24Bundle\Document\LegalDocumentHandlerInterface;
use Teris\ERecht24Bundle\Document\MessageHandlerInterface;      // optional
use Teris\ERecht24Bundle\Model\Connection;
use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Model\LegalDocument;
use Teris\ERecht24Bundle\Model\Message;
use Teris\ERecht24Bundle\Model\Registration;

final class ERecht24Sites implements ConnectionProviderInterface, LegalDocumentHandlerInterface, MessageHandlerInterface
{
    public function get(string $connectionId): ?Connection
    {
        $site = /* load site */;
        if (null === $site || !$site->erecht24ApiKey) {
            return null;                                   // unknown site or no API key
        }

        // clientId/secret stay null until the push client is registered
        return new Connection($connectionId, $site->erecht24ApiKey, $site->erecht24ClientId, $site->erecht24Secret);
    }

    public function storeRegistration(string $connectionId, ?Registration $registration): void
    {
        // $registration->clientId (int), $registration->secret (string); null = client deleted
    }

    public function handle(string $connectionId, LegalDocument $document): void
    {
        // $document->type === DocumentType::Imprint | DocumentType::PrivacyPolicy | …
        // persist $document->htmlDe / $document->htmlEn; throw DocumentManagedLocallyException to decline
    }

    public function handleMessage(string $connectionId, Message $message): void
    {
        // $message->messageDe, $message->callToActionDe, $message->link → admin notice
    }
}
```

**`ConnectionProviderInterface`** – credentials per tenant:

- `$connectionId` is your tenant/site identifier. It becomes part of the push URL
  `/erecht24/push/{connectionId}` and must match `[A-Za-z0-9_.-]{1,64}` (UUID/ULID/int are fine).
- Store per tenant: `apiKey` (varchar 64), `clientId` (int, nullable), `secret`
  (varchar 128, nullable). Treat `apiKey` and `secret` as credentials.
- `get()` returns `null` only for unknown tenants or tenants without API key. A tenant with an
  API key but no registered push client returns a `Connection` with `clientId`/`secret` = null
  (pull works, push is answered with 404 until `register()` ran).

**`LegalDocumentHandlerInterface`** – receives every pulled text:

- `LegalDocument`: `type` (enum `DocumentType::Imprint|PrivacyPolicy|PrivacyPolicySocialMedia`),
  `htmlDe`, `htmlEn`, `modifiedAt`, `fetchedAt`, `html(string $locale)`.
- Persist the HTML where the tenant's pages read it. Overwrite, don't append.
- Throw `Teris\ERecht24Bundle\Document\DocumentManagedLocallyException` if the tenant
  maintains this text manually → eRecht24 gets HTTP 422. Any other exception → HTTP 400
  (shown to the customer in eRecht24). Don't swallow errors.
- Optional: also implement `Teris\ERecht24Bundle\Document\MessageHandlerInterface`
  (`handleMessage(string $connectionId, Message $message)`) to receive eRecht24 notices
  for the tenant's admin UI. Independently, the events
  `Teris\ERecht24Bundle\Event\LegalDocumentUpdated` and `...\Event\MessageReceived` are dispatched.
- With an own handler the Twig functions are **not** registered; render from your own storage.
  The HTML comes from eRecht24 and is trusted legal content: output it unescaped
  (`{{ page.imprintHtml|raw }}`), do not strip tags.

**Lifecycle hooks in the application** (e.g. where the tenant saves settings):

```php
use Teris\ERecht24Bundle\ClientRegistrar;
use Teris\ERecht24Bundle\Synchronizer;

// API key entered or changed (after persisting the key):
$registrar->register($connectionId);   // registers or updates the push client → storeRegistration()
$synchronizer->syncAll($connectionId); // pull texts immediately

// API key removed / tenant deleted (BEFORE deleting the credentials):
$registrar->unregister($connectionId);
```

`register()`/`syncAll()` call the eRecht24 API and throw `Teris\ERecht24Bundle\Api\ApiException`
(`getMessage()` English, `$messageDe` German, `$statusCode`) – show `$messageDe` to German users.

## Rules that are easy to get wrong

- **Max. 3 push clients per eRecht24 project/API key**, each with a different URL (HTTP 403/409
  otherwise). Always `unregister()` when a site goes away. Sites beyond the limit can still
  pull: schedule `bin/console erecht24:sync <connectionId>` (cron or Symfony Scheduler).
  `erecht24:sync` handles one connection (default `default`); in multi-tenant mode loop over
  your tenants yourself, e.g. a scheduled task calling `Synchronizer::syncAll($id)` per site
  and catching `ApiException` per site so one failing tenant doesn't stop the others.
- Updating a client (`register()` on a connection that has a `clientId`) makes eRecht24 issue a
  **new secret** – it is passed to `storeRegistration()`; persist it, or pushes fail with 403.
- Never call the eRecht24 API while rendering a page in multi-tenant mode – read from your storage.
- Never commit API keys or secrets. Don't confuse the two keys: the **API key** belongs to the
  customer's eRecht24 project; the **plugin/developer key** identifies the software. The bundle
  ships its own developer key (`ERecht24Bundle::PLUGIN_KEY`, public by design). Only developers
  who received their own developer key from eRecht24 override it via
  `erecht24.plugin_key: '%env(ERECHT24_PLUGIN_KEY)%'` (and set `erecht24.client_info.plugin_name`).
  Never put a customer API key there.
- Do not write your own push controller, HTTP client or secret check – the bundle provides them
  (`PushController` uses `hash_equals`).
- Don't sanitize/strip the HTML from eRecht24 in a way that removes headings or links; it is
  legally relevant content.

## Verify your integration

```bash
bin/console lint:container
bin/console debug:router erecht24_push       # route exists, GET|POST
bin/console debug:config erecht24
bin/console erecht24:sync [connectionId]     # pulls texts (needs a real or the demo API key)
```

Push endpoint smoke test (expected: 404 without registration, 403 with a wrong secret,
`{"message":"pong"}` with the right secret):

```bash
curl -X POST -H 'Content-Type: application/json' \
     -d '{"erecht24_secret":"<secret>","erecht24_type":"ping"}' https://<host>/erecht24/push/<connectionId>
```

Testing in the application: replace `http_client` responses with
`Symfony\Component\HttpClient\MockHttpClient` / `framework.http_client.mock_response_factory`,
and test your `ConnectionProviderInterface`/`LegalDocumentHandlerInterface` implementations
directly. Do not hit the real eRecht24 API in CI.

## Public API (stable)

| Class | Use |
|---|---|
| `Teris\ERecht24Bundle\Synchronizer` | `sync(id, DocumentType)`, `syncAll(id)`, `syncMessage(id)` |
| `Teris\ERecht24Bundle\ClientRegistrar` | `register(id, ?pushUri)`, `unregister(id)`, `pushUri(id)` |
| `Teris\ERecht24Bundle\Api\ApiClient` | low-level API (`fetchDocument`, `listClients`, `testPush`, …) |
| `Teris\ERecht24Bundle\Connection\ConnectionProviderInterface` | implement in multi-tenant mode |
| `Teris\ERecht24Bundle\Document\LegalDocumentHandlerInterface` | implement for own storage |
| `Teris\ERecht24Bundle\Document\MessageHandlerInterface` | optional |
| `Teris\ERecht24Bundle\Document\CacheLegalDocumentStore` | built-in store, `get(id, DocumentType)` |

All are autowirable by class name. Console: `erecht24:client:register|unregister|list|test-push`,
`erecht24:sync`. A complete single-mode example app: https://github.com/teris-dev/recht-zeitig

## Working on this bundle itself

- Keep it slim: no entities, no UI, no new runtime dependencies without strong reason.
- Services are wired explicitly in `src/ERecht24Bundle.php` (no autowiring inside a reusable bundle).
- `composer test` (no network), `composer test:live` (real API, demo key, read-only),
  `composer analyse` (PHPStan level max) must pass. CI also runs lowest dependencies (Symfony 7.4).
- Behaviour of the push endpoint follows the eRecht24 spec: see `docs/01-api-analyse.md`.
- Update `README.md`, `docs/` and this file when public behaviour changes.
