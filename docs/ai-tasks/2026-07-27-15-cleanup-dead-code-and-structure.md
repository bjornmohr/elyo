# Paket 15: Aufräumen — toter Code, Datenmodell, Frontend-Struktur

**Priorität:** 4 · **Bereich:** Backend + Frontend · **Etappen:** 12
**Befunde:** F7, G1, G2, G8–G11, G15, G19, G23–G27, I10, I11, J3, J4, J6–J11, J32

```ai-run
complexity:        niedrig
implement_tier:    standard
implement_effort:  medium
review_tier:       standard
review_effort:     medium
blocked_by:        -
depends_on:        16
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

**U10 entschieden am 27.07.2026:** Die verwaisten ENV-Variablen **bleiben** in `.env.example`
und bekommen je einen Kommentar mit Grund (geplant / Altlast / extern gesetzt). Nichts entfernen.
Etappe 7 ist damit nicht mehr blockiert, sondern eine Dokumentationsaufgabe.

**Entschieden am 27.07.2026:** Push wird **gebaut** — als eigenes Paket 17, nicht hier.
Etappe 2 entfernt daher nichts. Siehe `2026-07-27-entscheidungen.md`.

Eine Etappe mit Zeile **Björn** wird nicht selbst entschieden. Lage aufbereiten, Optionen mit
Konsequenzen nach `docs/ai-results/` schreiben, Etappe als blockiert markieren, weitermachen.

| Etappe | Entscheidung | Wer |
|---|---|---|
| 3 | `AdminUsersComponent`: Sidebar-Link entfernen oder Seite bauen (G11) | **Björn** — nur dieser Punkt |


## Goal

Alles entfernen oder anbinden, was nachweislich unerreichbar ist — nachdem Paket 16 die
Entscheidungen dazu getroffen hat.

**Paket 16 zuerst.** Dort wird entschieden, was entfernt und was angebunden wird. Wer dieses Paket
vorzieht, muss die betroffenen Etappen überspringen.

## Context

Kapitel 14 G listet 27 Elemente ohne Produktivaufrufer. Ein Teil davon ist bereits in anderen
Paketen zugeordnet:

| Bereits zugeordnet | Paket |
|---|---|
| G3 `WearableService`, G6 `historyForMarker`, G14 `health_documents` | 02 |
| G4 Kontolöschung, G5 `decryptUserId`, G12 Not-Implemented-Guards, G22 `subject_ref` | 05 |
| G7 `InviteToken::isPending/isExpired` | 07 |
| G13 Zuweisungsdomäne, G18 `FREQUENCY_*` | 14 |
| G16 `user_points.level`, G21 `last_used_at` | 13 |
| G17 `SurveyStatus::CLOSED`, G20 `is_anonymous` | 11 |

**Dieses Paket behandelt den Rest.**

## Umsetzung in Etappen

**Zwölf Etappen — als drei Pull Requests fahren:** 1–4 (Entfernen), 5–7 (Datenmodell und Konfiguration), 8–12 (Struktur). Ein PR über alle zwölf ist nicht reviewbar.

### Etappe 1 — Eindeutig toter Backend-Code entfernen (G1, G8)

Ohne Entscheidungsbedarf — kein Aufrufer, keine fachliche Bedeutung:

- **`App\Services\StorageService`** (G1): `store()` und `delete()`, kein Produktivaufrufer.
  `HealthDocumentService` implementiert die Speicherung selbst. **Achtung:** Nach Paket 02,
  Etappe 1 (eigene Disk) wäre ein gemeinsamer Speicherdienst denkbar — dann dort neu bauen,
  nicht diesen wiederbeleben.
- **`User::isPlatformUser()` und `::isCompanyUser()`** (G8): kein Produktivaufrufer.
  **Achtung:** `isEmployee()`, `isInternalElyoCompany()`, `canUseCompanyPortal()`,
  `canUseEmployeePortal()` **werden** verwendet — nicht mitentfernen.
- **Abnahme:** Suite grün; Deptrac grün.

### Etappe 2 — Push-Infrastruktur als geplant kennzeichnen (G2, G15)

**Entschieden: Push wird gebaut.** Diese Etappe entfernt nichts. Sie markiert nur den
vorhandenen Attrappen-Code als geplant, damit ihn niemand später für tot hält.

**Dateien:** `apps/api-laravel/app/Services/PushNotificationService.php`, `.env.example`, `docs/ai-context/`.

- `PushNotificationService` simuliert bisher nur (`Log::info("Sending push to …")`).
  Klassenkommentar ergänzen: Attrappe, wird in Paket 17 ersetzt, Verweis auf das Ticket.
- Die Tabellen `push_subscriptions` und `notification_preferences` (G15) **bleiben**.
  Kommentar in der Migration oder im Modell mit demselben Verweis.
- Die drei VAPID-Variablen in `.env.example` **bleiben** und werden von der Liste der
  verwaisten Variablen in Etappe 7 ausgenommen.
- **Achtung:** `AccountDeletionService` räumt `push_subscriptions` nicht auf, sondern verlässt
  sich auf die Kaskade über `users`. Das ist ein echter Befund für Paket 17 — hier nur
  in `docs/ai-results/` festhalten, nicht beheben.
- **Abnahme:** Kein Code entfernt; jede der drei Stellen trägt einen Verweis auf Paket 17;
  Etappe 7 schließt die VAPID-Variablen aus.

### Etappe 3 — Frontend-Leichen entfernen (G9, G10, G11)

- **`roleGuard`** (G9) in `core/guards/auth.guards.ts` ist vollständig implementiert und wird von
  **keiner** Route verwendet. Entfernen oder einsetzen.
  **Achtung:** `authGuard` und `portalGuard` werden verwendet — nur `roleGuard` ist tot.
- **`PlaceholderComponent`** (G10): kein Import, keine Route. Entfernen.
- **`PartnerDashboardComponent`** (G11): **bleibt.** U1 ist entschieden — das Partner-Portal ist
  geplant. Mit Ticketverweis kommentieren, nicht entfernen.
- **`AdminUsersComponent`** (G11): Platzhalterseite mit statischem Text, über die Admin-Sidebar
  verlinkt, **kein Backend**. Ein Klick führt ins Leere. **Offen:** Sidebar-Link entfernen und
  die Seite belassen, oder die Seite bauen. Bis dahin diesen Punkt überspringen.
- **Abnahme:** Keine unerreichbare Komponente mehr; Angular-Build und Specs grün.

### Etappe 4 — Ungenutzte Spalten und Enums (G19, CheckinFrequency, Lab-Gruppe)

- **`companies.status`** (G19): wird in `AdminCompanyController::update` validiert
  (`in:active,inactive,suspended`), aber **nirgends ausgewertet** — weder beim Login noch sonst wo.
  Ein „suspended" Unternehmen verhält sich wie ein aktives.
  → Auswerten (dann fachlich definieren: was bedeutet es für bestehende Sitzungen?) oder entfernen.
- **`App\Enums\CheckinFrequency`** (`DAILY`, `WEEKLY`): das **gesamte Enum** wird von nichts
  verwendet. Der Check-in ist fest täglich (`WellbeingService::getPeriodKey()`).
  → Entfernen oder eine konfigurierbare Frequenz bauen.
- **Lab-Markergruppe `sonstige`**: im Enum `lab_markers.marker_group` erlaubt, vom
  `LabMarkerCatalogSeeder` aber **unbesetzt**. Belassen (Erweiterungsreserve) oder entfernen.
  **Achtung:** Eine Enum-Änderung in PostgreSQL erfordert eine neue Migration und ist bei
  vorhandenen Daten aufwendig.
- **Abnahme:** Je Element eine Entscheidung; keine stillschweigende Beibehaltung.

### Etappe 5 — Framework-Tabellen und Dateireste (G23, G25, G26, G27)

- **G23:** `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `notifications`
  sind konfiguriert, aber dauerhaft leer. Sie sind Framework-Standard — **belassen ist vertretbar**,
  aber die Entscheidung gehört dokumentiert.
  **Achtung:** Falls Paket 13, Etappe 4 den Cache auf Redis umstellt, werden `cache` und
  `cache_locks` endgültig obsolet.
  **Achtung:** `AccountDeletionService::deleteIdentityData()` räumt `sessions` und `notifications`
  aktiv auf, obwohl beide nie befüllt werden — bei Entfernung mitziehen.
- **G25:** Das `inspire`-Kommando in `routes/console.php` ist ein Skeleton-Rest. Entfernen.
- **G26:** `apps/web-angular/tailwind.config.js.bak` und `postcss.config.js.bak` sind inaktive
  Reste; aktiv ist `.postcssrc.json`. Entfernen.
- **G27:** `infra/scripts/` ist leer. Entfernen oder mit Inhalt füllen.
- **Abnahme:** Keine `.bak`-Dateien, kein leeres Verzeichnis, keine Skeleton-Reste.

### Etappe 6 — Modellkonventionen (J10, J11, J32)

- **J10:** Casts werden uneinheitlich deklariert — teils als Property `protected $casts`, teils
  als Methode `protected function casts(): array`. `User` und `SubjectMapping` nutzen die Methode,
  alle anderen die Property. Beides funktioniert in Laravel 13. Vereinheitlichen.
- **J11:** Schlüsseltyp-Widersprüche:
  - `NotificationPreference` deklariert `$keyType = 'string'`, die Spalte `user_id` ist
    `unsignedBigInteger`
  - `PushSubscription` deklariert `$keyType = 'string'`, `$incrementing = false` und führt `id`
    in `$fillable` — die Migration erzeugt aber `$table->id()` (bigserial)
  Beide sind folgenlos, weil nichts schreibt. **Falls Etappe 2 die Push-Infrastruktur entfernt,
  entfällt J11 teilweise.**
- **J32:** Fehlende Fremdschlüssel:
  - `partners.reviewed_by_id` — zeigt fachlich auf `users.id`, hat aber **keinen FK und keine
    Relation**. Ein gelöschter Admin hinterlässt eine tote Referenz.
  - `measures.created_by` — als einzige Nutzerreferenz dieser Domäne **ohne** FK.
  → Ergänzen (neue Migration, `nullOnDelete` analog zu den übrigen).
- **Abnahme:** Einheitliche Cast-Deklaration; keine Typwidersprüche; keine toten Referenzen.

### Etappe 7 — Verwaiste ENV-Variablen (G24)

- **Blockiert durch offene Frage U10** (Paket 16).
- 16 Variablen stehen in `.env.example`, werden aber von **keiner** `config/*.php` und keinem
  Codepfad gelesen:
  `ENCRYPTION_KEY`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `OAUTH_HMAC_SECRET`,
  `CRON_SECRET`, `BLOB_READ_WRITE_TOKEN`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`,
  `VAPID_SUBJECT`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`,
  `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT`, `MEMCACHED_HOST`, `VITE_APP_NAME`.
- **Besonders irreführend:** `ENCRYPTION_KEY` (Verwechslungsgefahr mit `APP_KEY` und
  `MAPPING_ENCRYPTION_KEY`) und `CRON_SECRET` (korrespondiert mit dem OpenAPI-Scheme `CronSecret`,
  für das keine Route existiert).
- **Achtung:** `NG_APP_API_URL` gehört ebenfalls dazu, wird aber in Paket 03, Etappe 2 behandelt.
- **Achtung:** Die AWS-Variablen stammen aus dem Laravel-Skeleton. Falls Paket 02, Etappe 1 eine
  S3-kompatible Disk einführt, werden sie relevant — **Reihenfolge beachten**.
- **Abnahme:** Nur noch gelesene Variablen in `.env.example`, oder verbleibende sind als Roadmap
  kommentiert.

### Etappe 8 — Frontend-Datenzugriff auf Feature-Services vereinheitlichen (J6)

**Dateien:** `apps/web-angular/src/app/features/company/**`, `apps/web-angular/src/app/features/admin/**`,
`apps/web-angular/src/app/features/auth/invite/**` sowie die zugehörigen `*.service.ts` und `*.spec.ts`.

**Zehn** Feature-Komponenten rufen `ApiClient` direkt auf, statt Feature-Services zu nutzen:
alle Company-Seiten außer Maßnahmen, `AdminCompaniesComponent`, `AdminCompaniesCreateComponent`,
`AdminPointsComponent`, `InviteComponent`.

- Je Komponente einen Feature-Service verwenden; fehlende Services anlegen
- **Achtung:** `CompanyTeamsService` existiert bereits, wird aber von `CompanyTeamsComponent`
  nicht genutzt. Wenn Paket 12 Etappe 6 schon gelaufen ist, ist das dort erledigt — dann hier
  nicht erneut anfassen, sondern nur prüfen.
- **Achtung:** Kein Verhaltenswechsel. Gleiche Endpunkte, gleiche Parameter, gleiches
  Fehlerverhalten. Reine Umleitung des Aufrufwegs.
- **Abnahme:** `grep -rn 'ApiClient' apps/web-angular/src/app/features/` liefert nur noch Treffer in
  `*.service.ts`; Angular-Build und Specs grün.

### Etappe 9 — Doppelte Navigation auflösen und `/employee/measures` verlinken (J7, J8)

**Dateien:** `apps/web-angular/src/app/app.ts`, `apps/web-angular/src/app/app.html`, die Shell-Komponenten,
`apps/web-angular/src/app/features/employee/**` (nur die Sidebar-Definition).

- **J7:** `AppComponent` rendert einen globalen Header mit Portalwechsler und Logout
  **zusätzlich** zur Shell-Sidebar, die ebenfalls Logout bietet. Eine der beiden Ebenen entfernen.
  **Achtung:** Der Portalwechsler hängt an `canUsePortal()` — beim Verschieben die Sichtbarkeits-
  logik mitnehmen, sonst sehen Mehrportal-Nutzer den Wechsler nicht mehr.
- **J8:** `/employee/measures` ist **nicht** in der Employee-Sidebar verlinkt — nur per direkter
  URL oder über den QR-Fluss erreichbar. Ergänzen.
- **Abnahme:** Genau ein Logout-Element und ein Portalwechsler im DOM; Spec deckt beide Portale
  ab; `/employee/measures` ist über die Sidebar erreichbar.

### Etappe 10 — Inline-Templates auslagern (J9)

**Dateien:** `company-measures.component.ts` (821 Zeilen), `company-surveys.component.ts` (695),
`admin-system-measure-templates.component.ts` (626) plus die neuen `.html`-Dateien.

Nur `app.html` ist bisher ausgelagert. Mindestens diese drei umstellen.

- **Achtung:** Reines Verschieben. Kein Umbau der Templates in derselben Etappe — sonst ist der
  Diff nicht mehr als Verschiebung lesbar und das Review verliert seinen Wert.
- **Abnahme:** Keine der drei Komponenten hat noch ein `template:`; Angular-Build und Specs grün;
  der Diff zeigt Verschiebung, keine inhaltliche Änderung.

### Etappe 11 — Präsentationslayer entkoppeln (J3, J4, F7)

**Dateien:** `apps/api-laravel/app/Http/Resources/Employee/LabMarkerReadingResource.php`,
`.../Employee/LabMarkerHistoryEntryResource.php`, `apps/api-laravel/app/Http/Controllers/**/CompanySurveyController.php`,
`CompanyController.php`, `apps/api-laravel/app/Services/Health/LabMarkerService.php`,
`apps/api-laravel/app/Http/Requests/Employee/StoreLabMarkerReadingRequest.php`.

- **J3:** `LabMarkerReadingResource` und `LabMarkerHistoryEntryResource` rufen
  `app(LabMarkerService::class)->deriveStatus(...)` — ein Service-Locator im Präsentationslayer.
  Status im Controller oder Service berechnen und übergeben.
- **J4:** Dynamische, nicht persistierte Modellattribute zur Datenübergabe:
  `CompanySurveyController::results` setzt sechs davon auf `Survey`,
  `CompanyController::dashboard` setzt `$team->metrics`. Durch DTOs oder
  Resource-Konstruktoren ersetzen.
- **F7:** Doppelte Labormarker-Validierung in `StoreLabMarkerReadingRequest` **und**
  `LabMarkerService::createReading`. Die zweite ist nötig, weil Seeder den Service ohne
  HTTP-Kontext aufrufen — **im Code kommentieren, nicht entfernen.**
- **Achtung:** Das API-Antwortformat bleibt Byte-für-Byte gleich. Es ist ein interner Umbau.
  Wenn ein Contract-Test anschlägt, war der Umbau falsch — nicht den Test anpassen.
- **Abnahme:** Kein `app(` mehr in `apps/api-laravel/app/Http/Resources/`; Contract-Tests unverändert grün;
  der Kommentar zur doppelten Validierung nennt den Seeder-Grund.

### Etappe 12 — Eingabevalidierung und verwaiste Antworten (I10, I11)

**Dateien:** `apps/api-laravel/app/Http/Controllers/**` (Employee-History-Endpunkt), zugehöriger Request,
`apps/api-laravel/app/Models/SurveyResponse.php`, die Aggregationsstelle, Tests.

- **I10:** `GET /employee/history?limit=` ist **ungeprüft** — der Wert geht direkt an `->take()`.
  Keine Validierung, keine Obergrenze, anders als beim Lab-Verlauf (`max:100`). Validieren,
  Obergrenze analog zum Lab-Verlauf.
- **I11:** `survey_responses.user_id` ist nullable mit `set null`; mehrere `NULL` umgehen den
  Unique-Index `(user_id, survey_id)`. Die Aggregation filtert über `whereHas('user')` und
  schließt verwaiste Antworten aus. **Prüfen und dokumentieren**, ob das ausreicht — kein
  Schemaeingriff in dieser Etappe. Wenn es nicht ausreicht: Befund nach `docs/ai-results/`,
  nicht spontan eine Migration schreiben.
- **Abnahme:** Test für `limit=0`, `limit=-1`, `limit=99999` und fehlendes `limit`; das
  Verhalten bei verwaisten Antworten ist mit einem Test belegt und im Ergebnisbericht bewertet.

## Out of Scope

- Alles, was in den Paketen 02, 05, 07, 11, 13, 14 bereits zugeordnet ist
- Entscheidungen selbst — die trifft Paket 16

## Hard constraints

- **Nichts entfernen, bevor Paket 16 die zugehörige Entscheidung getroffen hat**
- Deptrac und die Boundary-Suite müssen nach jeder Etappe grün bleiben
- Kein `migrate:fresh`; Tabellenentfernungen nur als neue Migration mit funktionierendem `down()`
- Bei Entfernung einer Tabelle prüfen, ob `AccountDeletionService` oder `EnforceRetention` sie
  referenzieren
- Angular-Build und Specs müssen nach jeder Etappe grün bleiben

## Review-Checkliste

- [ ] Paket 16 wurde zuerst durchgeführt, oder die betroffenen Etappen wurden übersprungen
- [ ] Kein Element wurde ohne dokumentierte Entscheidung entfernt
- [ ] `AccountDeletionService` und `EnforceRetention` referenzieren keine entfernte Tabelle
- [ ] Fehlende Fremdschlüssel ergänzt
- [ ] Einheitliche Cast-Deklaration
- [ ] Nur noch gelesene ENV-Variablen, oder Roadmap-Kommentar
- [ ] Einheitlicher Datenzugriff im Frontend; kein `ApiClient` mehr in Komponenten
- [ ] Genau ein Logout-Element und ein Portalwechsler
- [ ] Templates ausgelagert, Diff ist reine Verschiebung
- [ ] Kein `app(` in `apps/api-laravel/app/Http/Resources/`; API-Antwortformat unverändert
- [ ] `limit` validiert, mit Test für Grenzfälle
- [ ] `/employee/measures` ist verlinkt
- [ ] Doppelte Lab-Validierung ist kommentiert, nicht entfernt
- [ ] Deptrac, Boundary-Suite, Angular-Build grün
- [ ] Kapitel 2, 4.11, 5 und 15 der Dokumentation aktualisiert

## Expected output

- Geänderte und entfernte Dateien je Etappe
- Liste der entfernten Elemente mit Befund-ID und Entscheidungsverweis
- Ob Migrationen nötig waren
- Neue Tests und Specs
- Was bewusst nicht entfernt wurde und warum
