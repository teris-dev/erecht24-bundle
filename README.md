# eRecht24 Bundle für Symfony

Automatischer **Push & Pull von eRecht24-Rechtstexten** (Impressum und
Datenschutzerklärung, jeweils Deutsch und Englisch) für Symfony 7.4 LTS und 8.x.

- **Single-Modus:** eine Webseite, API-Key in `.env.local`
- **Multi-Tenant:** viele Webseiten in einer Anwendung, API-Keys in der Datenbank
- Ändert der Kunde seine Texte im eRecht24 Projekt-Manager, meldet eRecht24 das per Push.
  Das Bundle holt die Texte ab und übergibt sie an deine Anwendung.
- Schlank: keine Entities, keine UI, nur `symfony/http-client` als Laufzeit-Abhängigkeit
  über das Framework hinaus

> Hintergrund zur API und zur Architektur: [`docs/`](docs/README.md)

## Mit KI integrieren

Das Bundle bringt Anleitungen für KI-Assistenten mit:

- [`AGENTS.md`](AGENTS.md): vollständige Integrationsanleitung für Agenten (Codex, Cursor,
  Copilot, Claude Code über `CLAUDE.md` …). Nach der Installation liegt sie unter
  `vendor/teris-dev/erecht24-bundle/AGENTS.md`.
- [`skills/erecht24-integration`](skills/erecht24-integration/SKILL.md): Agent Skill mit dem
  Ablauf Schritt für Schritt. Ins eigene Projekt übernehmen:
  ```bash
  mkdir -p .claude/skills && cp -r vendor/teris-dev/erecht24-bundle/skills/erecht24-integration .claude/skills/
  ```
- [`llms.txt`](llms.txt): Index für KIs, die das Repository vor der Installation lesen.

Ein Prompt genügt, zum Beispiel: *„Installiere teris-dev/erecht24-bundle im Multi-Tenant-Modus
und folge der AGENTS.md des Pakets.“*

## Installation

```bash
composer require teris-dev/erecht24-bundle
```

```php
// config/bundles.php
Teris\ERecht24Bundle\ERecht24Bundle::class => ['all' => true],
```

Push-Route einbinden:

```yaml
# config/routes/erecht24.yaml
erecht24:
    resource: '@ERecht24Bundle/config/routes.php'
    prefix: /erecht24
```

Der Push-Endpunkt muss **öffentlich** erreichbar sein. Liegt er hinter einer Firewall,
gib ihn frei. Geschützt wird er über das Secret, das eRecht24 bei jedem Push mitschickt:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        erecht24:
            pattern: ^/erecht24/push
            security: false
```

## Single-Modus

```yaml
# config/packages/erecht24.yaml
erecht24:
    api_key: '%env(ERECHT24_API_KEY)%'
    client_id: '%env(default::ERECHT24_CLIENT_ID)%'
    client_secret: '%env(default::ERECHT24_CLIENT_SECRET)%'

framework:
    router:
        default_uri: 'https://www.example.de'   # damit die Konsole die Push-URL kennt
```

```dotenv
# .env.local
ERECHT24_API_KEY=…   # eRecht24 Projekt-Manager → Projekt → API-Schlüssel
```

Push-Endpunkt bei eRecht24 anmelden, die ausgegebenen Werte in `.env.local` eintragen
und die Verbindung testen:

```bash
bin/console erecht24:client:register
# → ERECHT24_CLIENT_ID=…  ERECHT24_CLIENT_SECRET=…  in .env.local eintragen
bin/console erecht24:client:test-push --type=ping
bin/console erecht24:client:test-push --type=imprint
```

Ausgabe im Template:

```twig
{{ erecht24_document('imprint', app.request.locale) }}
{{ erecht24_document('privacyPolicy', 'en') }}

{% set doc = erecht24_legal_document('imprint') %}
Stand: {{ doc.modifiedAt|date('d.m.Y') }}
```

Die Texte liegen im Cache-Pool `cache.app`. Fehlt ein Text, zum Beispiel nach einem Deploy,
holt das Bundle ihn beim ersten Aufruf nach. Für den Produktivbetrieb empfiehlt sich ein
persistenter Pool (`erecht24.cache_pool`).

## Multi-Tenant-Modus

Deine Anwendung implementiert zwei Interfaces. Das kann auch dieselbe Klasse sein.

**1. Zugangsdaten liefern:** `ConnectionProviderInterface`

```php
use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Teris\ERecht24Bundle\Model\{Connection, Registration};

final class SiteLegalSettings implements ConnectionProviderInterface
{
    public function __construct(private SiteRepository $sites, private EntityManagerInterface $em) {}

    public function get(string $connectionId): ?Connection
    {
        $site = $this->sites->find($connectionId);
        if (null === $site?->getErecht24ApiKey()) {
            return null;
        }

        return new Connection($connectionId, $site->getErecht24ApiKey(), $site->getErecht24ClientId(), $site->getErecht24Secret());
    }

    public function storeRegistration(string $connectionId, ?Registration $registration): void
    {
        $site = $this->sites->find($connectionId);
        $site->setErecht24ClientId($registration?->clientId);
        $site->setErecht24Secret($registration?->secret);
        $this->em->flush();
    }
}
```

**2. Texte entgegennehmen:** `LegalDocumentHandlerInterface`

```php
use Teris\ERecht24Bundle\Document\{LegalDocumentHandlerInterface, MessageHandlerInterface};
use Teris\ERecht24Bundle\Document\DocumentManagedLocallyException;
use Teris\ERecht24Bundle\Model\{DocumentType, LegalDocument, Message};

final class SiteLegalPages implements LegalDocumentHandlerInterface, MessageHandlerInterface
{
    public function handle(string $connectionId, LegalDocument $document): void
    {
        $site = $this->sites->find($connectionId);
        if ($site->isManagedLocally($document->type)) {
            throw new DocumentManagedLocallyException();   // → eRecht24 erhält HTTP 422
        }

        $page = $site->getPage($document->type === DocumentType::Imprint ? 'impressum' : 'datenschutz');
        $page->setContent($document->htmlDe, 'de');
        $page->setContent($document->htmlEn, 'en');
        $this->em->flush();
    }

    // optional
    public function handleMessage(string $connectionId, Message $message): void
    {
        // Hinweis im Admin-Bereich der Site anzeigen
    }
}
```

```yaml
# config/packages/erecht24.yaml
erecht24:
    connection_provider: App\ERecht24\SiteLegalSettings
    document_handler: App\ERecht24\SiteLegalPages
```

Push-Client an- und abmelden, wenn ein Kunde seinen API-Key einträgt oder entfernt:

```php
$registrar->register($site->getId());     // Teris\ERecht24Bundle\ClientRegistrar
$synchronizer->syncAll($site->getId());   // Texte sofort holen
// …
$registrar->unregister($site->getId());   // beim Entfernen des Keys / Löschen der Site
```

Die Push-URL lautet `https://<default_uri>/erecht24/push/{connectionId}`. Erlaubt sind
`[A-Za-z0-9_.-]`, maximal 64 Zeichen, zum Beispiel eine ULID.

## Was passiert bei einem Push?

| eRecht24 sendet | Bundle | Antwort |
|---|---|---|
| falsches/fehlendes Secret | – | 403 |
| `ping` | – | 200 `{"message":"pong"}` |
| `imprint`, `privacyPolicy` | Pull → `handle()` → Event `LegalDocumentUpdated` | 200 |
| Typ nicht in `erecht24.documents` | – | 422 |
| `handle()` wirft `DocumentManagedLocallyException` | – | 422 |
| Pull oder `handle()` scheitert | Fehler wird geloggt (Kanal `erecht24`) | 400 |
| `message` | Pull → `handleMessage()` (falls implementiert) → Event `MessageReceived` | 200 |

Bei 400 und 422 zeigt eRecht24 dem Kunden im Projekt-Manager einen Fehler an.

## Wichtig zu wissen

- **Maximal 3 Push-Clients pro eRecht24-Projekt.** Jede Webseite braucht eine eigene Push-URL.
  Weitere Webseiten können ohne Push per `erecht24:sync` (Cron oder Scheduler) abgleichen,
  denn Pull ist nicht begrenzt.
- Melde den Client wieder ab (`erecht24:client:unregister`), wenn eine Seite stillgelegt
  wird. Sonst belegt er einen der drei Plätze.
- Ein `register` auf eine bereits registrierte Verbindung aktualisiert den Client.
  eRecht24 vergibt dabei ein **neues Secret**.
- Das HTML von eRecht24 wird unverändert ausgegeben (`is_safe`).

## Eigener Developer-Key

eRecht24 verlangt neben dem API-Key des Kunden einen **Developer-Key**, der die Software
identifiziert. Das Bundle bringt seinen eigenen mit (`ERecht24Bundle::PLUGIN_KEY`).
Plugin-Keys sind bei Open-Source-Plugins öffentlich. Wer von eRecht24 einen eigenen
Developer-Key erhalten hat, etwa für ein eigenes Produkt auf Basis des Bundles,
überschreibt ihn:

```yaml
erecht24:
    plugin_key: '%env(ERECHT24_PLUGIN_KEY)%'
    client_info:
        plugin_name: 'acme/website-builder'
```

## Konfigurationsreferenz

```yaml
erecht24:
    plugin_key: '…'            # Developer-Key; voreingestellt = der des Bundles, überschreibbar
    base_uri: 'https://api.e-recht24.de/v2'
    api_key: ~                 # Single-Modus
    client_id: ~               # Single-Modus
    client_secret: ~           # Single-Modus
    connection_provider: ~     # Multi-Tenant: Service-ID
    document_handler: ~        # eigene Ablage: Service-ID; ~ = eingebauter Cache-Speicher + Twig
    cache_pool: cache.app      # nur eingebauter Speicher
    documents: [imprint, privacyPolicy]   # optional: privacyPolicySocialMedia
    client_info:               # wird bei der Registrierung an eRecht24 übermittelt
        cms: Symfony
        cms_version: <Symfony-Version>
        plugin_name: teris-dev/erecht24-bundle
        author_mail: ''
```

## Konsole

| Befehl | Zweck |
|---|---|
| `erecht24:client:register [connection] [--push-uri=]` | Push-Endpunkt anmelden bzw. aktualisieren |
| `erecht24:client:unregister [connection]` | Push-Client löschen |
| `erecht24:client:list [connection]` | alle Clients des Projekts anzeigen |
| `erecht24:client:test-push [connection] --type=ping` | eRecht24 einen Test-Push schicken lassen |
| `erecht24:sync [connection]` | alle Texte abholen (Fallback per Cron) |

## Entwicklung

```bash
composer install
composer test          # PHPUnit ohne Netz
composer test:live     # gegen die echte API mit dem Demo-Key (nur lesend)
composer analyse       # PHPStan, Level max
```

## Lizenz

MIT, siehe [LICENSE](LICENSE).
