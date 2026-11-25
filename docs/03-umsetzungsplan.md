# Umsetzungsplan

Status: Phasen 1–3 umgesetzt (2026-11-25). Offen: E2E-Push-Test mit echtem Key, Packagist, Einsatz in thirty/A1.

## Phase 1 – Grundgerüst
- `composer.json` (Typ `symfony-bundle`, PSR-4, PHP ≥ 8.2, Symfony ^7.4 || ^8.0)
- `ERecht24Bundle` als `AbstractBundle` inkl. Konfigurationsbaum
- Tooling: PHPUnit 11–13, PHPStan (max), GitHub Actions-Matrix (Symfony 7.4 lowest / 8.x)
- Lizenz: MIT

## Phase 2 – Pull
- `ApiClient` (scoped HttpClient, beide Key-Header, Fehler → `ApiException`)
- Modelle, `DocumentType`-Enum, `LegalDocumentHandlerInterface`,
  `CacheLegalDocumentStore` (Default), `Synchronizer`
- `ConfigConnectionProvider` (Single-Modus), `erecht24:sync`
- Tests: `MockHttpClient` mit Fixtures aus der Live-API; Integrationstest-Gruppe
  `@group live` gegen die Demo-Keys (nur GET)

## Phase 3 – Push
- `ClientRegistrar` + Commands `register`, `unregister`, `list`, `test-push`
- `PushController` + Route, Events
- Tests: Controller-Funktionstests (Secret, Typen, ping/pong, 400/403/422)
- **End-to-End** mit eigenem API-Key + Developer-Key über einen öffentlich
  erreichbaren Endpunkt (Tunnel), `erecht24:client:test-push --type=ping|imprint`.
  Mit dem Demo-Key nicht möglich (Client-Limit dort bereits erreicht).

## Phase 4 – Multi-Tenant & Doku
- Beispiel-Provider (Doctrine) in der Doku, Twig-Extension
- `README.md` (Installation, beide Modi, Firewall, Cron/Scheduler, Troubleshooting)
- `CHANGELOG.md`, Packagist-Veröffentlichung

## Phase 5 – Einsatz
1. Demo-Projekt **recht-zeitig** (`/var/www/recht-zeitig`, https://recht-zeitig.labs.teris.de, Single-Modus) auf sym –
   reale Push-Strecke verifizieren, einmalige Abnahme durch eRecht24
2. thirty (Multi-Tenant)
3. A1 (Multi-Tenant)

## Entscheidungen
- Paket `teris-dev/erecht24-bundle`, Namespace `Teris\ERecht24Bundle` (GitHub: teris-dev)
- Lizenz MIT
- Texte werden an einen `LegalDocumentHandlerInterface`-Service der Anwendung übergeben
- Hinweise (`message`): immer Event, optional zusätzlich `MessageHandlerInterface`
- Test- und Demo-Projekt: `recht-zeitig` auf sym (recht-zeitig.labs.teris.de)

## Offen
- Eigener eRecht24-API-Key für den E2E-Push-Test (Demo-Key: Client-Limit erreicht)
- Echter Developer-Key → `ERecht24Bundle::PLUGIN_KEY` ersetzen
