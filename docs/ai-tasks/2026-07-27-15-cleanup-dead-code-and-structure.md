# Paket 15: Aufräumen — toter Code, Datenmodell, Frontend-Struktur

**Priorität:** 4 · **Bereich:** Backend + Frontend · **Etappen:** 8
**Befunde:** F7, G1, G2, G8–G11, G15, G19, G23–G27, I10, I11, J3, J4, J6–J11, J32

```ai-run
complexity:        niedrig
implement_tier:    standard
implement_effort:  medium
review_tier:       standard
review_effort:     medium
blocked_by:        U10
depends_on:        16
```

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

### Etappe 2 — Push-Infrastruktur entscheiden (G2, G15)

- **`PushNotificationService`** (G2) hat keinen Aufrufer und **simuliert** nur
  (`Log::info("Sending push to …")`). Der Kommentar sagt: „In a real scenario, use
  minishlink/web-push or similar."
- Es gibt **keinen Endpunkt** zum Registrieren eines Abonnements — `saveSubscription()` ist
  unerreichbar. Die Tabellen `push_subscriptions` und `notification_preferences` (G15) bleiben
  dauerhaft leer.
- Auch das Frontend hat **keine** Push-Anbindung: kein Service Worker, kein
  `Notification.requestPermission()`, kein `pushManager`.
- `minishlink/web-push` ist **nicht** in `composer.json`; die drei VAPID-Variablen stehen in
  `.env.example`, werden aber nirgends gelesen.
- **Entscheiden:** vollständig entfernen (Service, beide Tabellen per neuer Migration, VAPID-Variablen)
  oder als Roadmap-Element mit Ticketverweis kennzeichnen.
- **Achtung:** `AccountDeletionService` räumt `push_subscriptions` nicht auf — es kaskadiert über
  `users`. Bei Entfernung mitprüfen.
- **Abnahme:** Entscheidung mit Begründung; bei Entfernung keine verwaisten Tabellen oder Variablen.

### Etappe 3 — Frontend-Leichen entfernen (G9, G10, G11)

- **`roleGuard`** (G9) in `core/guards/auth.guards.ts` ist vollständig implementiert und wird von
  **keiner** Route verwendet. Entfernen oder einsetzen.
  **Achtung:** `authGuard` und `portalGuard` werden verwendet — nur `roleGuard` ist tot.
- **`PlaceholderComponent`** (G10): kein Import, keine Route. Entfernen.
- **`AdminUsersComponent` und `PartnerDashboardComponent`** (G11): Platzhalterseiten mit statischem
  Text. `AdminUsersComponent` ist über die Admin-Sidebar verlinkt und hat **kein Backend**;
  `PartnerDashboardComponent` ist über den unerreichbaren Partner-Portalzweig geroutet
  (siehe Paket 06, Etappe 6).
  → Entscheiden: Sidebar-Link entfernen und Seite belassen, oder beides entfernen.
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

### Etappe 8 — Frontend-Struktur (J3, J4, J6, J7, J8, J9, F7, I10, I11)

- **J6:** **Zehn** Feature-Komponenten rufen `ApiClient` direkt auf, statt Feature-Services zu
  nutzen: alle Company-Seiten außer Maßnahmen, `AdminCompaniesComponent`,
  `AdminCompaniesCreateComponent`, `AdminPointsComponent`, `InviteComponent`.
  → Auf Feature-Services vereinheitlichen. **Achtung:** `CompanyTeamsService` existiert bereits,
  wird aber von `CompanyTeamsComponent` nicht genutzt (siehe Paket 12, Etappe 6).
- **J7:** Zwei parallele Navigationsebenen — `AppComponent` rendert einen globalen Header mit
  Portalwechsler und Logout **zusätzlich** zur Shell-Sidebar, die ebenfalls Logout bietet.
  Auflösen.
- **J8:** `/employee/measures` ist **nicht** in der Employee-Sidebar verlinkt — nur per direkter
  URL oder über den QR-Fluss erreichbar. Ergänzen.
- **J9:** Inline-Templates bis **821 Zeilen** (`company-measures.component.ts`),
  695 (`company-surveys`), 626 (`admin-system-measure-templates`). Nur `app.html` ist ausgelagert.
  → Auslagern, mindestens die drei größten.
- **J3:** `LabMarkerReadingResource` und `LabMarkerHistoryEntryResource` rufen
  `app(LabMarkerService::class)->deriveStatus(...)` — ein Service-Locator im Präsentationslayer.
  Status im Controller oder Service berechnen und übergeben.
- **J4:** Dynamische, nicht persistierte Modellattribute zur Datenübergabe:
  `CompanySurveyController::results` setzt sechs davon auf `Survey`,
  `CompanyController::dashboard` setzt `$team->metrics`. Durch DTOs oder Resource-Konstruktoren
  ersetzen.
- **F7:** Doppelte Labormarker-Validierung in `StoreLabMarkerReadingRequest` **und**
  `LabMarkerService::createReading`. Die zweite ist nötig, weil Seeder den Service ohne
  HTTP-Kontext aufrufen — **im Code kommentieren** statt entfernen.
- **I10:** `GET /employee/history?limit=` ist **ungeprüft** — der Wert geht direkt an `->take()`.
  Keine Validierung, keine Obergrenze, anders als beim Lab-Verlauf (`max:100`). Validieren.
- **I11:** `survey_responses.user_id` ist nullable mit `set null`; mehrere `NULL` umgehen den
  Unique-Index `(user_id, survey_id)`. Die Aggregation filtert über `whereHas('user')` und
  schließt verwaiste Antworten aus — **prüfen und dokumentieren**, ob das ausreicht.
- **Abnahme:** Einheitlicher Datenzugriff im Frontend; keine Templates über 200 Zeilen inline;
  `limit` validiert.

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
- [ ] Einheitlicher Datenzugriff im Frontend
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
