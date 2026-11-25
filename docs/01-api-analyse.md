# eRecht24 Rechtstexte-API v2 – Analyse

Quelle: <https://api-docs.e-recht24.de/> → OpenAPI-Spezifikation
`https://api.e-recht24.de/v2openapi.json` (Stand der Auswertung: 2026-11-25).
Gegen die Live-API mit den Demo-Keys geprüft (nur lesende Aufrufe).

## 1. Grundlagen

| Punkt | Wert |
|---|---|
| Basis-URL | `https://api.e-recht24.de/v2` |
| Authentifizierung | zwei Header: `eRecht24-api-key` **und** `eRecht24-plugin-key` (alternativ Query, Header hat Vorrang) |
| API-Key | Kunden-Schlüssel, pro **Projekt** im eRecht24 Projekt-Manager erzeugt, sha256-Hex (64 Zeichen) |
| Plugin-/Developer-Key | identifiziert das Plugin (= unser Bundle), wird von eRecht24 nach Unterzeichnung der API-AGB ausgegeben |
| Demo-API-Key | `e81cbf18a5239377aa4972773d34cc2b81ebc672879581bce29a0a4c414bf117` |
| Demo-Plugin-Key | `3jh4uhn8u69i97kj9timk466748996ikhkjhlk67plli08lhkijgh8z4363gr53v` |
| Fehlerformat | JSON `{message, message_de, faq_link, error_code, debug?}` |

## 2. Endpunkte

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/imprint` | Impressum (`html_de`, `html_en`, `created`, `modified`, `pushed`, `warnings`) |
| GET | `/privacyPolicy` | Datenschutzerklärung (zusätzlich `dsgalt`) |
| GET | `/privacyPolicySocialMedia` | Datenschutzerklärung Social Media (nicht im Scope, s. u.) |
| GET | `/message` | Hinweis-Nachricht für das Admin-Interface (200 oder 204) |
| GET | `/clients` | registrierte Push-Clients dieses API-Keys |
| POST | `/clients` | Push-Client registrieren → `client_id` + `secret` |
| PUT | `/clients/{client_id}` | Client ändern → **neues** `secret` |
| DELETE | `/clients/{client_id}` | Client löschen (bei Deinstallation Pflicht) |
| POST | `/clients/{client_id}/testPush?type=…` | Test-Push an einen Client auslösen |
| POST | `/push/{type}` | Push an alle Clients des API-Keys auslösen (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`, `all`) |

Ein Dokument enthält **beide Sprachen in einer Antwort** (`html_de`, `html_en`).
Für Impressum + Datenschutz DE/EN reichen also zwei Pull-Requests.

## 3. Wie wird der Push-Endpunkt bei eRecht24 registriert?

**Nicht manuell im eRecht24-Portal**, sondern **durch die Anwendung selbst** per API:

```
POST https://api.e-recht24.de/v2/clients
eRecht24-api-key:    <API-Key des Kunden-Projekts>
eRecht24-plugin-key: <unser Developer-Key>
Content-Type: application/json

{
  "push_method": "POST",
  "push_uri":    "https://kunde.de/erecht24/push/default",
  "cms":         "Symfony",
  "cms_version": "7.4.0",
  "plugin_name": "<vendor>/erecht24-bundle",
  "author_mail": "…"
}

→ 200 { "client_id": 4592, "secret": "<128 Zeichen>" }
```

Ablauf danach:

```
Kunde klickt im eRecht24 Projekt-Manager „Synchronisieren"
        │
        ▼
eRecht24 ──POST push_uri {erecht24_secret, erecht24_type}──▶ unser Push-Endpunkt
        (bei push_method GET: als Query-Parameter an push_uri angehängt)
                                                             │ 1. secret prüfen (sonst 401/403)
                                                             │ 2. type prüfen   (sonst 400)
                                                             │ 3. ping → 200 {"message":"pong"}
                                                             │ 4. Dokument per GET /imprint usw. ziehen
                                                             │ 5. 200 bei Erfolg, 400 bei Fehlschlag,
                                                             │    422 wenn Dokument lokal gepflegt wird
```

Der Push selbst enthält **keine Inhalte**, nur „es gibt Neues vom Typ X". Den Text
holt der Client anschließend mit dem API-Key per Pull. `client_id` und `secret`
müssen dauerhaft gespeichert werden; ein `PUT` erzeugt ein neues Secret.

## 4. Mehrere Push-Endpunkte pro Projekt?

**Ja – aber maximal 3 Clients pro Projekt / API-Key.**

- `POST /clients` liefert `403 "A maximum of 3 clients may be created per project."`
- `409` wenn bereits ein Client mit **derselben URL** im Projekt existiert → jede
  Webseite braucht eine eigene `push_uri`.
- Ein Push aus dem Projekt-Manager (bzw. `POST /push/{type}`) geht an **alle**
  registrierten Clients des Projekts.
- Live geprüft: Der Demo-Key hat bereits genau 3 Clients (öffentliche Testumgebung) –
  mit ihm lässt sich also **keine eigene Registrierung testen**.

**Beispiel „3 Webseiten, gleiches Impressum“:** ein eRecht24-Projekt, ein API-Key,
drei Clients mit drei unterschiedlichen `push_uri` → funktioniert genau bis zur Grenze.

**Mehr als 3 Webseiten mit demselben Text:**

1. **Pull reicht immer.** Pull (`GET /imprint` …) braucht nur den API-Key, keine
   Registrierung und unterliegt keiner Client-Grenze. Weitere Seiten
   synchronisieren zeitgesteuert (Cron / Symfony Scheduler, z. B. täglich).
2. Laufen die Seiten in **einer** Anwendung (Multi-Tenant), genügt ohnehin ein
   einziger Client für das Projekt: ein Push aktualisiert den gespeicherten Text,
   den alle Seiten dieses Projekts lesen.
3. Laut Fehlermeldung kann der eRecht24-Support die Grenze im Einzelfall anheben.

## 5. Push-Typen

`ping`, `message`, `imprint`, `privacyPolicy`, `privacyPolicySocialMedia`.

- `ping` → zwingend `200` + `{"message":"pong"}` (sonst Test-Push-Fehler 498)
- `message` → `GET /message` ziehen und im Admin-Bereich anzeigen
  (`message`/`message_de`, optional Button `call2action`/`call2action_de` → `link`)
- Dokumenttypen → Pull wie oben

## 6. Erkenntnisse aus dem offiziellen SDK

`erecht24/rechtstexte-sdk` (1.0.8, PHP ≥ 7.1, ext-curl): eigenes Curl-Handling,
keine Verarbeitung eingehender Pushes, keine Symfony-Integration. Für ein schlankes,
Symfony-natives Bundle bringt es keinen Mehrwert – wir nutzen direkt
`symfony/http-client`. Die Konstanten (`erecht24_secret`, `erecht24_type`,
Typnamen) stimmen mit der Spezifikation überein.

## 7. Unklarheiten, die im Test geklärt werden müssen

- Kodierung des POST-Push-Bodys (JSON oder Formular) ist nicht spezifiziert →
  Bundle liest beides (`Request::getPayload()`), wird per `testPush` verifiziert.
- Ob die Client-Grenze pro API-Key oder pro Projekt gilt (Doku nennt beides).
  Praktisch identisch, solange ein Projekt einen API-Key hat.
- Antwort-Code bei falschem Secret ist nicht festgelegt („a suited error status
  code“) → wir antworten `403`.
