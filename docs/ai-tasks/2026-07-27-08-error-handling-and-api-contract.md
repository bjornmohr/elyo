# Paket 08: Fehlerbehandlung und API-Vertrag

**Priorität:** 2 · **Bereich:** Backend + Frontend · **Etappen:** 7
**Befunde:** A10, C1, C2, C5, C6, C12, C14, C-FE, E1, E2, E4, E6, E7, K4

```ai-run
complexity:        hoch
implement_tier:    high
implement_effort:  high
review_tier:       standard
review_effort:     medium
blocked_by:        -
depends_on:        09
```

## Arbeitsregeln

Diese sechs Regeln gelten für jede Etappe. Sie stehen vor dem Inhalt, weil sie ihn überstimmen.

**1. Erst prüfen, dann ändern.** Jede Aussage in diesem Dokument ist ein Befund vom Stand
`56b4a53` (27.07.2026), nicht vom Stand deines Branches. Öffne vor jeder Etappe die genannten
Dateien, Klassen und Methoden und bestätige den beschriebenen Zustand im aktuellen Code.

**2. Befund trifft nicht zu → melden, nicht umdeuten.** Wenn der Code anders aussieht als hier
beschrieben (bereits behoben, verschoben, umbenannt, so nie dagewesen): Etappe abbrechen, den
Ist-Zustand in `docs/ai-results/` festhalten, mit der nächsten Etappe weitermachen. Kein
Ersatzproblem suchen, nichts „sinngemäß“ umsetzen.

**3. Nur benannte Dateien anfassen.** Änderungen außerhalb der in der Etappe genannten Dateien
und ihrer direkten Tests sind out of scope — auch wenn dabei ein echter Fehler auffällt. Solche
Funde gehören nach `docs/ai-results/`, nicht in den Diff.

**4. Nichts löschen ohne ausdrücklichen Auftrag.** Tabellen, Spalten, Migrationen, Klassen,
Endpunkte, Routen, Frontend-Komponenten: löschen nur, wenn die Etappe es wörtlich anordnet.
„Kein Aufrufer gefunden“ ist kein Löschgrund — siehe Entscheidungspunkte.

**5. Abbruch ist ein gültiges Ergebnis.** Bei Unklarheit schlägt abbrechen und melden das Raten.
Ein Paket mit fünf sauberen und drei abgebrochenen Etappen ist verwertbar. Ein Paket mit acht
Etappen, von denen drei geraten sind, ist es nicht.

**6. Abnahme ist Nachweis, nicht Behauptung.** Jede Etappe endet mit dem tatsächlich gelaufenen
Testbefehl und seiner Ausgabe — im Commit oder im Ergebnisbericht. „Passt“ ist keine Abnahme.

### Entscheidungspunkte

Eine Etappe mit Zeile **Björn** wird nicht selbst entschieden. Lage aufbereiten, Optionen mit
Konsequenzen nach `docs/ai-results/` schreiben, Etappe als blockiert markieren, weitermachen.

| Etappe | Entscheidung | Wer |
|---|---|---|
| 2 | Zielformat der Validierungsfehler (E1) | Agent, Begründung in den Commit |


## Goal

Ein einheitliches Fehlerformat, ein Vertrag, der die Implementierung beschreibt, und ein Test,
der beides zusammenhält.

## Context

`bootstrap/app.php::withExceptions()` ist **leer**. Es gibt keinen globalen Handler, keine
Renderer-Registrierung und keine Exception-Zuordnung. Daraus folgen **zwei parallele Formate**:

**Format A** (Laravel-Standard, 17 von 21 Requests): `{"message":…,"errors":{…}}`
**Format B** (Codeformat, 3 Requests + Middleware + Services): `{"error":{"code":…,"message":…}}`

Dazu **zehn Abweichler**, die keinem der beiden folgen — darunter zwei mit abweichendem
Statuscode (`400 {"error":"invalid_transition"}` in `MeasureController::update` und
`AdminPartnerController::update`) und ein 403 mit Zusatzfeldern
(`GET /company/surveys/{id}/results` unter der Anonymitätsschwelle).

Das Frontend wertet in **fünf verschiedenen Mustern** aus: `err.error?.error?.code`,
`err.error?.code`, `err.error?.errors`, `err.error?.message`, `err.status`.

Gegenüber `docs/api/openapi.yaml` (59 Pfade) bestehen **14 dokumentierte Abweichungen**, und es
gibt **keinen automatisierten Vertragstest** — alle wurden manuell festgestellt.

Ein Vorbild für Erosionsschutz existiert bereits:
`tests/Privacy/CompanyAdminRoutePrivacyTest::test_new_company_route_without_request_definition_is_rejected`
bricht, sobald eine neue Company-Route ohne Testdefinition hinzukommt.

## Umsetzung in Etappen

### Etappe 1 — Globaler Exception-Handler (E2, A10)

- Renderer in `withExceptions()` registrieren, der API-Anfragen im **Codeformat** beantwortet.
- `APP_DEBUG` in `.env.example` produktionssicher setzen (Abstimmung mit Paket 04, Etappe 3 —
  falls dort schon geschehen, hier nur verifizieren).
- Sicherstellen, dass bei deaktiviertem Debug keine Klassennamen, Pfade oder SQL-Fragmente
  in der Antwort erscheinen.
- **Die vier Privacy-Exceptions hier nur vor Stacktraces schützen**, nicht fachlich behandeln —
  das ist Paket 02, Etappe 3.
- **Achtung:** `tests/Privacy/CompanyAdminRoutePrivacyTest::test_server_error_payload_is_leak_checked_before_status_failure`
  prüft bereits heute, dass auch ein 500 keine Gesundheitsdaten enthält. Muss grün bleiben.
- **Abnahme:** 500 liefert das Codeformat ohne Stacktrace; Privacy-Suite grün.

### Etappe 2 — Validierungsformat vereinheitlichen (E1)

- Das Trait `FailsWithCodedValidationError` existiert bereits und wird von **drei** Requests
  genutzt (`CheckinRequest` mit eigener Implementierung, `LabMarkerHistoryRequest`,
  `StoreLabMarkerReadingRequest`). Sein Kommentar nennt die Referenz:
  `docs/ai-context/api-contract-rules.md`.
- Entscheiden: alle 21 Requests auf das Codeformat umstellen, oder das Codeformat auf die drei
  beschränken und in `api-contract-rules.md` präzisieren.
- **Empfohlen: vereinheitlichen.** Zwei Formate zwingen jeden Client zu doppelter Auswertung.
- **Achtung:** Das ist eine breite Vertragsänderung. Sie betrifft jeden Frontend-Aufrufer, der
  `err.error?.errors` liest — mindestens `LoginComponent`, `CompanyTeamsComponent`,
  `CompanyInvitationsComponent`, `CompanyMeasuresComponent`, `CompanySurveysComponent`.
  **Etappe 5 muss unmittelbar folgen.**
- **Abnahme:** Ein Format für alle Validierungsfehler; OpenAPI mitgezogen.

### Etappe 3 — Abweichler angleichen (E6, C14)

Die zehn Ausreißer auf das gewählte Format bringen:

| Endpunkt | Heute |
|---|---|
| `POST /employee/checkin` (ohne Firma) | `{"error":"Employee must belong to a company"}` — String statt Objekt, **englisch** |
| `POST /employee/measures/{m}/participate` (404) | `{"message":"Not found"}` |
| `GET /employee/surveys/{id}` (404) | `{"error":"Not found"}` |
| `POST /employee/surveys/{id}/respond` (400) | `{"error":"Invalid questionId: 42"}` |
| `GET /company/dashboard` (Manager ohne Team) | `{"error":"Kein Team zugewiesen. …"}` |
| `POST /partner/login` (401) | `{"error":"invalid_credentials"}` |
| `GET /auth/invite/verify` (404) | `{"valid":false,"error":"…"}` |
| `GET /company/surveys/{id}/results` (403) | 403 mit `minRequired`, `isAboveThreshold`, `suppressionReason` |

- **Vorsicht bei zwei Fällen:**
  - `GET /auth/invite/verify` — die `valid`-Struktur wird vom Frontend ausgewertet
    (`InviteComponent::verifyInvite`). Mitziehen.
  - `GET …/results` (C14) — die Zusatzfelder `minRequired` und `suppressionReason` sind fachlich
    sinnvoll. Ins Codeformat überführen, **ohne** die Information zu verlieren:
    `{"error":{"code":"ANONYMITY_THRESHOLD_NOT_MET","message":…,"details":{"minRequired":10}}}`.
- **E6:** Die zwei englischen Meldungen in deutscher Oberfläche mit übersetzen.
- **Abnahme:** Kein Endpunkt weicht mehr vom gewählten Format ab.

### Etappe 4 — Statuscodes korrigieren (C1, C5, C6)

- **C5, C6:** `PATCH /company/measures/{id}` und `PATCH /admin/partners/{id}` liefern bei
  unzulässigem Statusübergang **400** `{"error":"invalid_transition"}`. Alle anderen
  Konfliktfälle im System verwenden 409 mit Codeformat. Angleichen.
- **C1:** `POST /employee/checkin` antwortet mit **200**, OpenAPI beschreibt 201. Entscheiden,
  welche Seite recht hat — der Endpunkt legt eine Ressource an, 201 ist naheliegend, ändert aber
  den Vertrag für `CheckinComponent`.
- **Abnahme:** Statuscodes stimmen mit OpenAPI überein; Frontend mitgezogen.

### Etappe 5 — Fehlerauswertung im Frontend vereinheitlichen (E4)

- Nach Etappe 2 und 3 gibt es ein Format — die fünf Auswertungsmuster auf eines reduzieren.
- Zentrale Hilfsfunktion oder ein Error-Interceptor, der ein normalisiertes Fehlerobjekt liefert.
- **Achtung:** `EmployeeMeasuresComponent::errorCode()` liest heute bewusst beide Formen
  (`err.error?.error?.code ?? err.error?.code`) — nach der Vereinheitlichung überflüssig.
- **Achtung:** Der 401-Interceptor aus Paket 01, Etappe 5 gehört hierher, falls noch nicht erfolgt.
- **Abnahme:** Ein Auswertungsmuster im gesamten Frontend; Specs grün.

### Etappe 6 — Antwortfelder und Resources (C2, C7, C8, C-FE)

- **C2:** `POST /auth/invite/accept` liefert weder `allowedPortals` noch `activePortal`, obwohl
  OpenAPI sie beschreibt. Das Frontend kompensiert mit einem zusätzlichen `getMe()`-Aufruf
  (`InviteComponent::onSubmit`). Ergänzen und den Zusatzaufruf entfernen.
- **C7, C8:** `GET /admin/companies` liefert rohe Eloquent-Modelle, `GET /admin/partners` das rohe
  Paginator-Objekt. Resources einführen (C8 ist Teil von Paket 06, Etappe 4 — abstimmen).
- **C-FE:** Frontend-Typen an die tatsächlichen Antworten angleichen:
  - `EmployeeMeasure.team` deklariert `{id,name}`, Backend liefert nur `{name}`
  - `SurveyListItem.id` deklariert `string`, Backend liefert `int`
  - `SurveyListItem.status` deklariert `'EXPIRED'`, das nie entsteht
  - `SurveyQuestion.required` heißt im Backend `isRequired`
- **Abnahme:** Frontend-Typen entsprechen den Antworten; kein kompensierender Zusatzaufruf mehr.

### Etappe 7 — Contract-Test einführen (K4, C12)

- Bibliothek wählen und begründen, oder einen schlanken eigenen Abgleich implementieren.
- **Mindestens zwei Prüfungen:**
  - **Routenabgleich:** Jede registrierte Laravel-Route hat eine OpenAPI-Operation und umgekehrt.
    Deckt **C12** ab — die Tags `Cron`, `Webhooks`, `Wearables` und das Security-Scheme
    `CronSecret` existieren in OpenAPI, aber ohne Route.
  - **Antwortabgleich** für eine repräsentative Auswahl.
- **Verbleibende Abweichungen in eine explizite, kommentierte Allowlist** mit Befund-ID — sonst
  ist der Test von Anfang an rot und wird ignoriert. Jeder behobene Befund entfernt einen Eintrag.
- Neue Suite in `phpunit.xml` registrieren und in die CI aufnehmen (Paket 09).
- **Abnahme:** Test läuft; Allowlist ist kommentiert; die Routenprüfung hat keine unbekannten
  Lücken gefunden — oder sie sind gemeldet.

### Nicht in diesem Paket (E7 — Locale)

`APP_LOCALE=en` bei durchgängig deutscher Oberfläche: Laravels Standardvalidierungsmeldungen
kommen englisch. Es gibt keine `lang/`-Dateien und keine i18n im Frontend. Das ist eine
**Produktentscheidung** (nur deutsch, oder mehrsprachig) und gehört in Paket 16.

## Out of Scope

- Fachliche Behandlung der Privacy-Exceptions (Paket 02, Etappe 3)
- Reporting-Pending-Vertrag C3/C4 (Paket 10)
- Nicht setzbare Vorlagenfelder C9 (Paket 14)
- Partner-Security-Scheme C11 (Paket 06)

## Hard constraints

- `tests/Privacy/*` muss nach jeder Etappe grün bleiben — insbesondere der Leak-Test auf
  Fehlerantworten
- Die generischen Meldungen bei ungültigen Zugangsdaten bleiben unverändert
- Etappe 5 folgt unmittelbar auf Etappe 2 und 3, sonst bricht das Frontend
- Kein `migrate:fresh`

## Review-Checkliste

- [ ] Ein Fehlerformat für die gesamte API
- [ ] Kein Stacktrace bei deaktiviertem Debug
- [ ] Alle zehn Abweichler angeglichen, ohne fachliche Information zu verlieren
- [ ] Statuscodes stimmen mit OpenAPI überein
- [ ] Ein Auswertungsmuster im Frontend
- [ ] Frontend-Typen entsprechen den Antworten
- [ ] Contract-Test läuft, Allowlist ist kommentiert und mit Befund-IDs versehen
- [ ] Privacy-Suite grün
- [ ] Kapitel 6, 13.1 und 14 der Dokumentation aktualisiert

## Expected output

- Gewähltes Fehlerformat mit Begründung
- Geänderte Dateien je Etappe
- Inhalt der Contract-Test-Allowlist
- Ob die Routenprüfung neue Lücken gefunden hat
- Neue Tests je Etappe
- Welche Vertragsänderungen Clients betreffen
