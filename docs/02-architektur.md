# Architektur des Bundles

## Ziele

- **Schlank**: kein eigenes ORM-Mapping, keine UI, keine SDK-Abhängigkeit.
  Abhängigkeiten nur `symfony/framework-bundle`, `symfony/http-client`,
  `symfony/cache` (PSR-6/Contracts). Twig ist optional.
- **Zwei Betriebsarten über eine Abstraktion**, nicht über `if ($mode)`-Zweige.
- **Robust**: Texte werden lokal vorgehalten; eine Seite rendert ihr Impressum
  auch dann, wenn die eRecht24-API gerade nicht erreichbar ist.
- Unterstützt: Symfony 7.4 LTS und 8.x, PHP ≥ 8.2 (a1next = 7.4, thirty/teris = 8.x).

## Zentrale Idee: die „Connection“

Eine Connection ist *ein eRecht24-Projekt aus Sicht unserer Anwendung*:

```php
final readonly class Connection
{
    public function __construct(
        public string  $id,        // unsere ID: "default" oder z. B. Tenant-/Site-ULID
        public string  $apiKey,    // API-Key des Kunden-Projekts
        public ?int    $clientId = null, // von POST /clients
        public ?string $secret = null,   // von POST /clients bzw. PUT
    ) {}
}
```

Woher Connections kommen, entscheidet **ein** Interface:

```php
interface ConnectionProviderInterface
{
    public function get(string $id): ?Connection;
    /** Wird nach Registrierung/Update/Löschung aufgerufen. */
    public function storeRegistration(string $id, ?int $clientId, ?string $secret): void;
}
```

| Modus | Implementierung | Herkunft der Daten |
|---|---|---|
| **Single** | `ConfigConnectionProvider` (im Bundle) | `.env.local`: `ERECHT24_API_KEY`, `ERECHT24_CLIENT_ID`, `ERECHT24_CLIENT_SECRET` |
| **Multi-Tenant** | Implementierung der Anwendung (z. B. Doctrine-Repository der Nutzereinstellungen) | Datenbank |

## Zweiter Erweiterungspunkt: wohin mit den Texten?

Das Bundle besitzt die Texte nicht, es **übergibt** sie. Die Anwendung stellt
einen Service bereit, der dieses Interface implementiert:

```php
interface LegalDocumentHandlerInterface
{
    /**
     * Wird nach jedem erfolgreichen Pull aufgerufen (Push, Command, Read-Through).
     *
     * @throws DocumentManagedLocallyException wenn der Mandant diesen Text selbst
     *         pflegt → Push wird mit 422 beantwortet (laut eRecht24-Spezifikation)
     * Jede andere Exception → Push wird mit 400 beantwortet, eRecht24 zeigt dem
     *         Kunden den Fehler im Projekt-Manager an.
     */
    public function handle(string $connectionId, LegalDocument $document): void;
}
```

Warum ein Interface und kein Event:
- Speichern ist **Pflicht**, kein optionaler Nebeneffekt. Ein Interface erzwingt
  genau eine Implementierung; ein Event könnte niemand abonnieren, ohne dass es auffällt.
- Fehler beim Speichern kommen **synchron** zurück und werden zur korrekten
  HTTP-Antwort an eRecht24. Der Kunde sieht im Projekt-Manager, dass die
  Synchronisation fehlgeschlagen ist.
- Die Anwendung entscheidet selbst über Speicherort und Form: in thirty/A1 z. B.
  direkt als Inhalt der generierten Impressums-Seite. Die Texte gehören dann zu den
  Daten der Webseite und werden ohne das Bundle ausgeliefert.

Für den Single-Modus liefert das Bundle eine fertige Implementierung mit,
`CacheLegalDocumentStore` (PSR-6-Pool ohne Ablaufzeit). Sie ist zugleich der
Lesespeicher für die Twig-Funktion. Ohne eigenen Handler funktioniert das Bundle also sofort.

Eine Klasse der Anwendung darf beide Interfaces implementieren (z. B. ein
`SiteLegalSettingsRepository`), sie bleiben aber getrennt, weil es zwei
verschiedene Aufgaben sind: Zugangsdaten liefern und Texte entgegennehmen.

Im Single-Modus kann `storeRegistration()` nicht in `.env.local` schreiben. Das
Register-Command gibt `client_id`/`secret` deshalb aus und weist an, sie in
`.env.local` (oder `secrets:set`) einzutragen. Der Modus ergibt sich allein daraus,
ob `erecht24.api_key` konfiguriert ist oder ein eigener Provider gesetzt wird.

## Komponenten

```
src/
├── ERecht24Bundle.php                 AbstractBundle: Konfiguration + Service-Definitionen
├── Api/
│   ├── ApiClient.php                  dünner Wrapper um HttpClient (alle Endpunkte)
│   └── ApiException.php               message, message_de, error_code, faq_link
├── Model/
│   ├── Connection.php
│   ├── Registration.php               client_id + secret
│   ├── DocumentType.php               enum: Imprint, PrivacyPolicy, PrivacyPolicySocialMedia
│   ├── LegalDocument.php              readonly: type, htmlDe, htmlEn, modifiedAt, fetchedAt; html(locale)
│   └── Message.php                    readonly: Hinweis aus GET /message
├── Connection/
│   ├── ConnectionProviderInterface.php
│   └── ConfigConnectionProvider.php
├── Document/
│   ├── LegalDocumentHandlerInterface.php   Erweiterungspunkt: Texte entgegennehmen
│   ├── MessageHandlerInterface.php         optional: Hinweise direkt empfangen
│   ├── DocumentManagedLocallyException.php → 422
│   └── CacheLegalDocumentStore.php         Default-Handler + Lesespeicher (Single-Modus)
├── Synchronizer.php                   Pull → Handler::handle() → Event LegalDocumentUpdated
├── ClientRegistrar.php                register / update / unregister (für Commands und Multi-Tenant-Code)
├── Controller/PushController.php      eingehender Push
├── Event/
│   ├── LegalDocumentUpdated.php
│   └── MessageReceived.php
├── Command/
│   ├── RegisterClientCommand.php      erecht24:client:register [connection]
│   ├── UnregisterClientCommand.php    erecht24:client:unregister [connection]
│   ├── ListClientsCommand.php         erecht24:client:list [connection]
│   ├── TestPushCommand.php            erecht24:client:test-push [connection] --type=ping
│   └── SyncCommand.php                erecht24:sync [connection] (für Cron/Scheduler)
└── Twig/ERecht24Extension.php         nur wenn Twig installiert: erecht24_document()
config/routes.php                      Push-Route zum Importieren
```

### Lesepfad (Seite rendert Impressum)

Bei einem eigenen Handler liest die Anwendung aus ihrem eigenen Speicher, das Bundle
ist am Lesen nicht beteiligt. Nur beim mitgelieferten `CacheLegalDocumentStore`:

```
erecht24_document('imprint', 'de')
  → Cache-Treffer?  ja → html_de
  → nein → Synchronizer::sync() (Read-Through) → speichern → html_de
```

Leere Caches (Deploy, `cache:clear`) heilen sich so beim ersten Aufruf selbst.
Für Produktivbetrieb wird ein **persistenter** Pool empfohlen
(z. B. `cache.adapter.doctrine_dbal` oder Redis), konfigurierbar über `cache_pool`.

### Push-Pfad

```
POST /erecht24/push/{connection}
  1. ConnectionProvider::get() → unbekannt: 404
  2. hash_equals(secret, erecht24_secret) → sonst 403
  3. type ungültig → 400
  4. ping → 200 {"message":"pong"}
  5. message → GET /message → Event MessageReceived → 200
  6. Dokumenttyp nicht in `documents` konfiguriert → 422 (laut Spezifikation „lokal gepflegt“)
  7. Synchronizer::sync() → Handler::handle()
       → 200 | DocumentManagedLocallyException → 422 | API-/Speicherfehler → 400
```

Die Connection-ID im Pfad löst zwei Probleme: Multi-Tenant-Routing ohne Lookup
über das Secret, und eindeutige URLs pro Webseite (Vermeidung von `409`).
Die Route muss in der Firewall öffentlich sein (`security: false` bzw. `PUBLIC_ACCESS`);
Schutz erfolgt über das Secret (zeitkonstant verglichen).

### Nachrichten (`message`)

Das Bundle zieht die Nachricht und feuert immer das Event `MessageReceived`.
Implementiert der Document-Handler zusätzlich das optionale `MessageHandlerInterface`,
bekommt er die Nachricht vorher direkt über `handleMessage()`. Anzeige und Speicherung
übernimmt die Anwendung, das Bundle selbst speichert keine Nachrichten.

## Konfiguration

```yaml
# config/packages/erecht24.yaml
erecht24:
    # Developer-Key des Bundles. Default = Key des Bundles (öffentlich, Open Source);
    # bis er vorliegt: Demo-Plugin-Key.
    plugin_key: '…'   # voreingestellt
    # Single-Modus: API-Key des Kunden. Weglassen im Multi-Tenant-Modus.
    api_key:       '%env(default::ERECHT24_API_KEY)%'
    client_id:     '%env(default::ERECHT24_CLIENT_ID)%'
    client_secret: '%env(default::ERECHT24_CLIENT_SECRET)%'
    # Multi-Tenant: Service-ID einer ConnectionProviderInterface-Implementierung
    connection_provider: ~
    # Service-ID einer LegalDocumentHandlerInterface-Implementierung;
    # ~ = mitgelieferter CacheLegalDocumentStore
    document_handler: ~
    documents: [imprint, privacyPolicy]   # optional: privacyPolicySocialMedia
    cache_pool: cache.app                  # nur für den Default-Handler
    base_uri: 'https://api.e-recht24.de/v2'
    client_info:                            # Pflichtangaben bei POST /clients
        cms: Symfony
        plugin_name: 'teris-dev/erecht24-bundle'
        author_mail: ~
```

```php
// config/routes/erecht24.php
return static fn (RoutingConfigurator $routes) =>
    $routes->import('@ERecht24Bundle/config/routes.php')->prefix('/erecht24');
```

```twig
{{ erecht24_document('imprint', app.request.locale) }}
{# Multi-Tenant: #}
{{ erecht24_document('privacyPolicy', 'en', site.erecht24ConnectionId) }}
```

## Multi-Tenant-Einbindung (thirty, A1)

1. Anwendung speichert pro Tenant/Site: `apiKey`, `clientId`, `secret`
   (z. B. drei Spalten in den Nutzereinstellungen).
2. Anwendung implementiert `ConnectionProviderInterface` (≈ 20 Zeilen) und
   `LegalDocumentHandlerInterface` (schreibt `html_de`/`html_en` in die Seiten der Site).
3. Beim Speichern des API-Keys in den Einstellungen:
   `$registrar->register($connectionId)` → Push-URL wird über den Router erzeugt,
   Ergebnis landet via `storeRegistration()` in der DB.
4. Beim Entfernen des Keys / Löschen der Site: `$registrar->unregister($connectionId)`.

## Bewusst nicht im Bundle

- Doctrine-Entities/Migrationen (gehören in die Anwendung)
- Admin-UI, Formulare
- HTML-Sanitizing (eRecht24 liefert geprüftes HTML; wer will, filtert im Event)
- `privacyPolicySocialMedia` ist unterstützt, aber standardmäßig nicht aktiv
