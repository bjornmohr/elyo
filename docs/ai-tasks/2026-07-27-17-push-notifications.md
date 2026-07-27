# Paket 17: Push-Benachrichtigungen bauen

**Priorität:** 3 · **Bereich:** Backend + Frontend + Infra · **Etappen:** 9
**Befunde:** G2, G15 · **Herkunft:** Entscheidung vom 27.07.2026 zu Etappe 15.2

```ai-run
complexity:        hoch
implement_tier:    high
implement_effort:  high
review_tier:       high
review_effort:     high
blocked_by:        P1, P2, P3
depends_on:        04
```

## Arbeitsregeln

Diese sechs Regeln gelten für jede Etappe. Sie stehen vor dem Inhalt, weil sie ihn überstimmen.

**1. Erst prüfen, dann ändern.** Jede Aussage in diesem Dokument ist ein Befund vom Stand
`56b4a53` (27.07.2026), nicht vom Stand deines Branches. Öffne vor jeder Etappe die genannten
Dateien, Klassen und Methoden und bestätige den beschriebenen Zustand im aktuellen Code.

**2. Befund trifft nicht zu → melden, nicht umdeuten.** Wenn der Code anders aussieht als hier
beschrieben (bereits behoben, verschoben, umbenannt, so nie dagewesen): Etappe abbrechen, den
Ist-Zustand in `docs/ai-results/` festhalten, mit der nächsten Etappe weitermachen. Kein
Ersatzproblem suchen, nichts „sinngemäß" umsetzen.

**3. Nur benannte Dateien anfassen.** Änderungen außerhalb der in der Etappe genannten Dateien
und ihrer direkten Tests sind out of scope — auch wenn dabei ein echter Fehler auffällt. Solche
Funde gehören nach `docs/ai-results/`, nicht in den Diff.

**4. Nichts löschen ohne ausdrücklichen Auftrag.** Tabellen, Spalten, Migrationen, Klassen,
Endpunkte, Routen, Frontend-Komponenten: löschen nur, wenn die Etappe es wörtlich anordnet.
„Kein Aufrufer gefunden" ist kein Löschgrund — siehe Entscheidungspunkte.

**5. Abbruch ist ein gültiges Ergebnis.** Bei Unklarheit schlägt abbrechen und melden das Raten.
Ein Paket mit fünf sauberen und drei abgebrochenen Etappen ist verwertbar. Ein Paket mit acht
Etappen, von denen drei geraten sind, ist es nicht.

**6. Abnahme ist Nachweis, nicht Behauptung.** Jede Etappe endet mit dem tatsächlich gelaufenen
Testbefehl und seiner Ausgabe — im Commit oder im Ergebnisbericht. „Passt" ist keine Abnahme.

### Entscheidungspunkte

Eine Etappe mit Zeile **Björn** wird nicht selbst entschieden. Lage aufbereiten, Optionen mit
Konsequenzen nach `docs/ai-results/` schreiben, Etappe als blockiert markieren, weitermachen.

| Etappe | Entscheidung | Wer |
|---|---|---|
| 1 | **P1** — Einwilligung und Drittlandtransfer: darf Web Push mit Google/Apple/Mozilla als Zustelldienst überhaupt eingesetzt werden? | **Björn** + Datenschutz — blockiert das gesamte Paket |
| 1 | **P2** — Welche der drei vorgesehenen Benachrichtigungsarten werden gebaut? | **Björn** — Etappe blockiert |
| 7 | **P3** — Zu welcher Uhrzeit in welcher Zeitzone läuft die Check-in-Erinnerung? | **Björn** — hängt an U13 |

**Etappe 1 ist eine Sperre, kein Vorschlag.** Ohne P1 wird in diesem Paket keine Zeile Code
geschrieben. Web Push für eine Gesundheitsanwendung ist ein datenschutzrechtlicher Vorgang,
kein Feature-Toggle — die Begründung steht unter „Datenschutz" im Context.

## Goal

Aus der vorhandenen Attrappe eine echte Push-Zustellung machen: Registrierung, Versand,
Einstellungen, Löschung — mit Einwilligung, ohne Gesundheitsdaten im Payload.

**Dies ist kein Befundpaket.** Die Pakete 01–16 beheben dokumentierte Mängel. Dieses baut ein
Feature. Es ist entstanden, weil die Entscheidung zu Etappe 15.2 „Push wird gebaut" lautete und
der vorhandene Code dafür nichts hergibt.

## Context

### Was heute existiert

| Element | Datei | Zustand |
|---|---|---|
| `PushNotificationService` | `apps/api-laravel/app/Services/PushNotificationService.php` | 48 Zeilen. `sendToUser()` schreibt nur `Log::info("Sending push to …")` — kein Versand. `saveSubscription()` schreibt **echt** in die Datenbank, ist aber von nichts aufrufbar. |
| `PushSubscription` | `apps/api-laravel/app/Models/PushSubscription.php` | Modell vorhanden, Relation `User::pushSubscriptions()` (`User.php:83`) vorhanden |
| `NotificationPreference` | `apps/api-laravel/app/Models/NotificationPreference.php` | Modell vorhanden, Relation `User::notificationPreference()` (`User.php:93`) vorhanden |
| `push_subscriptions` | `database/migrations/identity/2024_01_01_000001_create_identity_tables.php:347` | Tabelle in der Identity-Baseline, dauerhaft leer |
| `notification_preferences` | dieselbe Datei, `:358` | dito. Spalten: `checkin_reminder`, `checkin_reminder_time` (Default `'09:00'`), `weekly_summary`, `partner_updates` |
| VAPID-Variablen | `.env.example:84–86` (**Repo-Root**, nicht `apps/api-laravel/`) | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` |

### Was fehlt — vollständig

- **Kein Endpunkt.** Weder Route noch Controller noch FormRequest noch Resource. `saveSubscription()`
  ist von außen nicht erreichbar.
- **Keine Bibliothek.** `minishlink/web-push` steht **nicht** in `composer.json`. Der Kommentar im
  Service verweist darauf („In a real scenario, use minishlink/web-push or similar").
- **Keine Konfiguration.** Es gibt **keine** `config/`-Datei, die die drei VAPID-Variablen liest.
  Ein `env()`-Aufruf außerhalb von `config/` liefert bei gecachter Konfiguration `null` —
  das ist im Repo an anderer Stelle bereits als Fehlerklasse dokumentiert.
- **Kein Frontend.** `@angular/service-worker` ist **nicht** in `apps/web-angular/package.json`.
  Kein `provideServiceWorker` in `app.config.ts`, kein `Notification.requestPermission()`,
  kein `pushManager`, keine `ngsw-config.json`.
- **Kein Test.** Weder Feature- noch Unit-Test berührt Push.

### Infrastruktur, die es ebenfalls noch nicht gibt

Das ist der Teil, der leicht übersehen wird:

- **Kein Queue-Worker.** `docker-compose.yml` hat genau einen Worker-Dienst, `reporting-worker`
  (`:215`) — mit `profiles: ["future"]` und `command: ["sleep", "infinity"]`. Er tut nichts.
  `QUEUE_CONNECTION` ist per Default `database`. Ohne laufenden Worker bleiben Jobs liegen.
- **Kein aktiver Scheduler.** `routes/console.php` enthält die Retention-Aufgabe **auskommentiert**,
  mit der Begründung, dass dafür erst eine eigene Maintenance-Runtime verdrahtet werden muss.
  Eine Check-in-Erinnerung braucht genau diesen Scheduler.
- **Kein Mail- oder Notification-Code im Projekt.** Es gibt weder `app/Mail/` noch
  `app/Notifications/`, und keinen einzigen `Mail::`- oder `Notification::`-Aufruf. Push wäre der
  **erste** ausgehende Benachrichtigungskanal überhaupt. Es gibt kein Muster zum Nachahmen.
  (`mailpit` läuft in `docker-compose.yml:316` und `MAIL_*` ist in `.env.example:70–75` gesetzt —
  vorbereitet, aber ungenutzt.)

### Zwei Schemafehler, die vor dem Bauen weg müssen

**Schlüsseltyp-Widerspruch.** Die Migration legt `push_subscriptions` mit `$table->id()` an —
autoinkrementierender Bigint — und `user_id` als `unsignedBigInteger`. Die `users`-Tabelle ist
ebenfalls Bigint (`$table->id()`), das `User`-Modell deklariert **keinen** abweichenden
Schlüsseltyp. Beide Push-Modelle behaupten aber das Gegenteil:

```php
// PushSubscription.php
protected $keyType = 'string';
public $incrementing = false;
protected $fillable = ['id', 'user_id', 'endpoint', 'p256dh', 'auth'];

// NotificationPreference.php
protected $primaryKey = 'user_id';
protected $keyType = 'string';
public $incrementing = false;
```

Das sind Überbleibsel einer geplanten ULID-Umstellung, die für die Identity-Domäne nie
stattgefunden hat. Weil die Tabellen leer sind, ist der Widerspruch nie aufgefallen. `'id'` im
`$fillable` einer autoinkrementierenden Tabelle ist zusätzlich eine Mass-Assignment-Lücke.

**`endpoint` ist zu kurz.** `$table->string('endpoint')->unique()` ergibt `varchar(255)`.
Endpunkt-URLs von FCM überschreiten das regelmäßig. Ein Abo mit langer URL schlägt beim Anlegen
fehl oder wird abgeschnitten — im zweiten Fall zeigt der Unique-Index auf einen Torso.

### Datenschutz — warum Etappe 1 eine Sperre ist

Web Push funktioniert nur über den Push-Dienst des Browserherstellers: Google (FCM), Mozilla,
Apple. Daraus folgt:

- **Der Zustelldienst ist ein Dritter im Drittland.** Jede Zustellung läuft über dessen Server.
  Das braucht eine Rechtsgrundlage, einen AV-Vertrag und eine Transfer-Folgenabschätzung.
- **Der Payload ist verschlüsselt, die Metadaten nicht.** Inhalt ist über `p256dh`/`auth`
  Ende-zu-Ende geschützt. Zeitpunkt, Häufigkeit und die Existenz des Abos sieht der Dienst.
  Eine tägliche Erinnerung um 09:00 verrät dem Push-Dienst, dass diese Person eine
  Gesundheitsanwendung nutzt — auch ohne den Inhalt zu kennen.
- **Der Endpunkt ist ein Gerätebezeichner.** `push_subscriptions.endpoint` ist ein
  personenbezogenes Datum und gehört in die Retention-Matrix und in die Löschung.
- **ADR-002** ist eine DSFA-Vorprüfung. Ein neuer Datenfluss zu einem Drittanbieter fällt in
  ihren Gegenstandsbereich.

Deshalb: **P1 zuerst, alles andere danach.**

## Umsetzung in Etappen

**Neun Etappen — als drei Pull Requests fahren:** 1–3 (Grundlagen), 4–6 (Backend), 7–9 (Auslieferung).

### Etappe 1 — Datenschutzrahmen und Umfang festlegen (P1, P2)

**Gesperrt.** Diese Etappe schreibt keinen Code, sondern Entscheidungsgrundlagen.

**Dateien:** `docs/ai-results/`, später `docs/adr-documents/`, `docs/privacy/`.

- **P1 aufbereiten:** Welcher Zustelldienst je Browser, welche Daten dorthin fließen (Endpunkt,
  Zeitpunkt, verschlüsselter Payload), welche Rechtsgrundlage in Frage kommt, ob ADR-002 eine
  Ergänzung braucht. Bezug auf `docs/adr-documents/ADR-002-DSFA-Vorpruefung-Scope-Methodik-Blocker-Steuerung.md` herstellen.
- **P2 aufbereiten:** Die Tabelle sieht drei Arten vor — `checkin_reminder`, `weekly_summary`,
  `partner_updates`. Je Art beschreiben: Auslöser, Inhalt, Häufigkeit, ob dafür überhaupt Daten
  vorliegen. **Achtung:** `partner_updates` hängt am Partner-Subsystem, dessen Lebenszyklus in
  **Paket 06 Etappe 2 noch offen** ist. Ohne diese Entscheidung ist die Art nicht baubar.
- **Achtung:** Kein Vorschlag „bauen wir erst mal alle drei". Jede gebaute Art ist ein eigener
  Datenfluss mit eigener Einwilligung.
- **Abnahme:** Ein Dokument in `docs/ai-results/` mit beiden Aufbereitungen, ohne Empfehlung
  zugunsten einer Variante. Kein Produktionscode geändert.

### Etappe 2 — Schema begradigen (Schlüsseltyp, Endpunktlänge)

**Dateien:** neue Migration unter `apps/api-laravel/database/migrations/identity/`,
`app/Models/PushSubscription.php`, `app/Models/NotificationPreference.php`, zugehörige Tests.

- Die Modell-Deklarationen `$keyType = 'string'` und `$incrementing = false` entfernen — die
  Tabellen sind Bigint. `'id'` aus dem `$fillable` von `PushSubscription` entfernen.
- `notification_preferences`: `$primaryKey = 'user_id'` **bleibt** (die Tabelle hat wirklich
  `user_id` als Primärschlüssel), nur `$keyType`/`$incrementing` korrigieren.
- `push_subscriptions.endpoint` auf `text` erweitern. **Achtung:** Der Unique-Index muss dabei
  mitwandern; PostgreSQL indiziert `text` problemlos, aber der bestehende Index ist neu
  anzulegen, nicht zu ändern.
- **Achtung:** `2024_01_01_000001_create_identity_tables.php` ist eine **reviewte Baseline**.
  Sie wird nicht editiert. Alles als neue Migration.
- **Achtung:** Die Tabellen sind leer — die Migration braucht trotzdem ein funktionierendes
  `down()`.
- **Abnahme:** `php artisan elyo:migrate-fresh` läuft durch; ein Test legt ein Abo mit einer
  400 Zeichen langen Endpunkt-URL an und liest es zurück; Modell und Schema stimmen überein.

### Etappe 3 — Konfiguration und Bibliothek

**Dateien:** `apps/api-laravel/composer.json`, neue `apps/api-laravel/config/push.php`,
`.env.example` (Repo-Root), `docs/deployment/`.

- `minishlink/web-push` in `composer.json` aufnehmen und die Version festschreiben.
- `config/push.php` anlegen, die die drei VAPID-Variablen liest. **Achtung:** `env()` gehört
  ausschließlich in `config/` — bei gecachter Konfiguration ist es sonst `null`. Das ist der
  Grund, warum die Variablen heute wirkungslos sind.
- Einen Weg zum Erzeugen des Schlüsselpaars dokumentieren (Artisan-Befehl oder
  `web-push generate-vapid-keys`), und in `docs/deployment/` festhalten, dass ein **Wechsel des
  VAPID-Schlüssels alle bestehenden Abos ungültig macht**. Das gehört in dieselbe Liste wie die
  Rotation von `MAPPING_HMAC_KEY` und `APP_KEY` (Paket 04, Etappe 8).
- **Achtung:** `VAPID_PRIVATE_KEY` ist ein Geheimnis. Nicht in `.env.example` befüllen, nicht ins
  Log, nicht in eine Fehlermeldung.
- **Abnahme:** `config('push.vapid.public_key')` liefert bei gecachter Konfiguration den Wert;
  ein Test belegt das mit `config:cache`; die Rotationsfolge steht in `docs/deployment/`.

### Etappe 4 — Registrierung und Abmeldung (Endpunkte)

**Dateien:** neuer Controller unter `apps/api-laravel/app/Http/Controllers/Employee/`,
FormRequests unter `app/Http/Requests/Employee/`, `routes/api/employee.php`,
`app/Services/PushNotificationService.php`, Tests unter `tests/Feature/Employee/`.

- `POST /employee/push/subscriptions` — Abo anlegen oder aktualisieren.
- `DELETE /employee/push/subscriptions` — Abo entfernen (Endpunkt im Rumpf, nicht in der URL).
- `GET`/`PUT /employee/notification-preferences` — Einstellungen lesen und schreiben.
- Einbau nach dem Muster von `routes/api/employee.php`: innerhalb
  `Route::middleware('auth:sanctum')` und `->middleware('role:EMPLOYEE')->prefix('employee')`.
- Validierung: `endpoint` als URL mit Längenobergrenze, `keys.p256dh` und `keys.auth` als
  Base64URL mit erwarteter Länge. **Achtung:** `saveSubscription()` nutzt heute
  `updateOrCreate(['endpoint' => …])` **ohne** `user_id` in der Suchbedingung — wer den Endpunkt
  eines fremden Abos schickt, überschreibt dessen `user_id` und übernimmt das Gerät. Das ist beim
  Bau des Endpunkts zu beheben, nicht zu übernehmen.
- **Achtung:** Nur die eigenen Abos. Kein Admin- oder Company-Pfad auf fremde Abos.
- **Abnahme:** Tests für Anlegen, doppeltes Anlegen desselben Endpunkts, Abmelden, Zugriff auf
  ein fremdes Abo (muss scheitern), fehlende Authentifizierung, ungültige Schlüssel.

### Etappe 5 — Versand echt implementieren

**Dateien:** `app/Services/PushNotificationService.php`, neue Payload-Klasse, Tests.

- `sendToUser()` von `Log::info` auf echten Versand über `minishlink/web-push` umstellen.
- **Fehlerbehandlung nach Statuscode:** `404` und `410` bedeuten, dass das Abo beim Zustelldienst
  nicht mehr existiert — solche Abos werden **gelöscht**, nicht erneut versucht. `429` und `5xx`
  sind wiederholbar. Alles andere wird protokolliert.
- **Achtung:** Kein Endpunkt und kein Schlüsselmaterial ins Log. Der bestehende Code loggt heute
  `"Sending push to {$sub->endpoint}"` — genau das darf nicht bleiben.
- **Achtung:** Der Payload enthält **keine Gesundheitsdaten**. Kein Check-in-Wert, kein
  Laborwert, keine Anamnese, kein Umfrageergebnis. Erlaubt ist eine neutrale Aufforderung plus
  ein Ziel-Pfad in der Anwendung. Diese Regel gehört als Kommentar an die Payload-Klasse.
- **Abnahme:** Unit-Tests mit einem Doppel des Versandclients für Erfolg, `410` (Abo wird
  gelöscht), `429` (bleibt bestehen); ein Test stellt sicher, dass der Payload keine Felder
  außerhalb der Positivliste enthält.

### Etappe 6 — Asynchroner Versand und Worker

**Dateien:** neuer Job unter `apps/api-laravel/app/Jobs/`, `docker-compose.yml`,
`.env.example`, Tests.

- Versand in einen Queue-Job auslagern. Ein Nutzer kann mehrere Geräte haben; ein Rundruf an
  viele Nutzer darf keinen Request blockieren.
- **Worker-Dienst in `docker-compose.yml` ergänzen.** Heute existiert nur `reporting-worker`
  (`:215`) mit `profiles: ["future"]` und `sleep infinity`. **Achtung:** Der neue Dienst braucht
  ein Runtime-Profil und darf nur die Verbindungen bekommen, die er wirklich benötigt — die
  Identity-Domäne. Kein `ELYO_RUNTIME: full` aus Bequemlichkeit.
- **Achtung:** Paket 04 fasst `docker-compose.yml` ebenfalls an. Wenn 04 noch nicht gelaufen ist,
  wird es hier Konflikte geben — das ist der Grund für `depends_on: 04`.
- Wiederholversuche mit wachsendem Abstand, Obergrenze, Ablage in `failed_jobs`.
- **Abnahme:** Job läuft über die Queue; ein Test mit `Queue::fake()` belegt die Einreihung; der
  Worker-Dienst startet und arbeitet einen Job ab; die Deptrac-Schichten bleiben grün.

### Etappe 7 — Check-in-Erinnerung planen (P3)

**Gesperrt bis P3 entschieden ist.**

**Dateien:** `apps/api-laravel/routes/console.php`, neues Console-Command, Tests.

- `notification_preferences.checkin_reminder_time` ist ein **String** mit Default `'09:00'` und
  ohne Zeitzone. `config/app.php:68` setzt `'timezone' => 'UTC'`.
- **P3:** Ist `'09:00'` UTC oder Ortszeit? Bei UTC bekommt ein Nutzer in Deutschland die
  Erinnerung im Sommer um 11:00. Für Ortszeit fehlt eine Zeitzone am Nutzer — die Spalte gibt es
  nicht.
- **Achtung:** Das ist dieselbe Frage wie **U13** (Zeitzonenabhängigkeit des `period_key`,
  Paket 13). Beide zusammen entscheiden, sonst driften Check-in-Tag und Erinnerungszeitpunkt
  auseinander.
- **Achtung:** `routes/console.php` sagt ausdrücklich, dass geplante Aufgaben erst laufen sollen,
  wenn eine eigene Maintenance-Runtime verdrahtet ist. Diese Etappe darf die auskommentierte
  Retention-Aufgabe **nicht** nebenbei aktivieren.
- **Abnahme:** Erst nach P3. Dann: Command mit `--dry-run`, Test über mehrere Zeitzonen,
  Schedule-Eintrag ohne Aktivierung fremder Aufgaben.

### Etappe 8 — Frontend: Service Worker, Einwilligung, Einstellungen

**Dateien:** `apps/web-angular/package.json`, `angular.json`, `ngsw-config.json`,
`src/app/app.config.ts`, neuer Push-Service unter `src/app/core/services/`, eine
Einstellungsseite unter `src/app/features/employee/`, Specs.

- `@angular/service-worker` aufnehmen und `provideServiceWorker` in `app.config.ts` ergänzen.
  **Achtung:** Die Anwendung ist **zoneless** (`provideZonelessChangeDetection()`); Rückmeldungen
  aus dem Service Worker müssen über Signals in die Änderungserkennung gelangen.
- **Achtung:** U7 ist entschieden — die Anwendung bleibt eine SPA, SSR wird in Paket 03 entfernt.
  Ein Service Worker darf nicht am serverseitigen Rendern hängen. Wenn Paket 03 noch nicht
  gelaufen ist, existieren `main.server.ts` und `server.ts` noch — nicht daran andocken.
- Berechtigungsdialog: **nicht** beim Start anfragen. Erst nach einer bewussten Handlung in der
  Einstellungsseite. Ein abgelehnter Zugriff wird nicht erneut erfragt.
- Einstellungsseite: die in P2 beschlossenen Arten schalten, Geräteliste anzeigen, einzelne
  Geräte abmelden.
- **Achtung:** Kein `ApiClient`-Direktaufruf in der Komponente — Feature-Service verwenden
  (Paket 15, Etappe 8 vereinheitlicht genau das).
- **Abnahme:** Angular-Build und Specs grün; Spec für „Berechtigung abgelehnt", „Berechtigung
  erteilt", „Abo abmelden"; kein automatischer Berechtigungsdialog beim Laden.

### Etappe 9 — Löschung, Aufbewahrung, Protokollierung

**Dateien:** `app/Services/Privacy/AccountDeletionService.php`,
`docs/further_docs/retention-matrix.md`, `app/Console/Commands/EnforceRetention.php`,
`app/Services/Privacy/` (Audit), Tests unter `tests/Feature/Privacy/`.

- **Löschung:** `AccountDeletionService` räumt heute ausdrücklich `sessions` (`:172`),
  `notifications` (`:175`), `personal_access_tokens` (`:179`) und `invite_tokens` (`:183`) —
  `push_subscriptions` und `notification_preferences` **nicht**. Sie verschwinden nur über die
  Fremdschlüssel-Kaskade. Ausdrücklich aufnehmen, damit die Zählung im Löschbericht stimmt.
- **Aufbewahrung:** `docs/further-docs/retention-matrix.md` nennt Push nicht. **Achtung:** Paket 04
  Etappe 8 legt `further-docs` nach `further_docs` zusammen — je nach Reihenfolge liegt die Datei
  schon dort. Aufnehmen:
  Ein Abo, das seit N Monaten nicht mehr zustellbar war, wird entfernt.
- **Protokollierung:** `AuditLoggerContract` kennt heute nur `logMappingOperation`,
  `logProvisioningBackfill` und `logAccountDeletion`; `PurposeCode` kennt `PROVISIONING`,
  `HEALTH_SELF_READ`, `HEALTH_SELF_WRITE`, `REVOCATION`, `REPORTING`, `DSR` — **nichts** für
  Benachrichtigungen. **Achtung:** Ob Abo-Änderungen ins Audit gehören, ist eine Frage an P1.
  Ohne diese Antwort hier **nichts** ins Audit schreiben — die Audit-Datenbank ist
  append-only, ein Fehleintrag bleibt drin.
- **Abnahme:** Test, dass eine Kontolöschung Abos und Einstellungen zählt und entfernt;
  Retention-Matrix ergänzt; Audit unverändert, solange P1 offen ist.

## Out of Scope

- Alles aus den Paketen 01–16 — dieses Paket behebt keine Befunde außer G2 und G15
- E-Mail-Benachrichtigungen (es gibt keinerlei Mail-Code; das wäre ein eigenes Paket)
- In-App-Benachrichtigungen und die `notifications`-Tabelle
- `partner_updates`, solange Paket 06 Etappe 2 offen ist
- Aktivierung der auskommentierten Retention-Aufgabe in `routes/console.php`
- Der Umbau von `docker-compose.yml` über den einen Worker-Dienst hinaus — das ist Paket 04

## Hard constraints

- **P1 blockiert alles.** Ohne die Datenschutzentscheidung wird ab Etappe 2 nichts umgesetzt
- **Keine Gesundheitsdaten im Payload** — weder Werte noch Bezeichnungen, aus denen sich welche
  ableiten lassen
- **Kein Endpunkt und kein Schlüsselmaterial im Log**, in Fehlermeldungen oder in Ausnahmen
- Die Identity-Baseline `2024_01_01_000001_create_identity_tables.php` wird **nicht** editiert
- Kein `migrate:fresh` außerhalb von `php artisan elyo:migrate-fresh`
- ADR-001 / ADR-003 bleiben bindend: Push-Daten liegen in der Identity-Domäne, kein Zugriff auf
  die Health-Domäne aus dem Versandpfad
- Der neue Worker bekommt das engste passende Runtime-Profil, nicht `full`
- Deptrac und die Privacy-Suite müssen nach jeder Etappe grün bleiben

## Review-Checkliste

- [ ] P1 liegt schriftlich vor, bevor Etappe 2 beginnt
- [ ] Kein Payload-Feld außerhalb der Positivliste; ein Test belegt es
- [ ] Kein `endpoint`, kein `p256dh`, kein `auth` in Log oder Fehlermeldung
- [ ] `updateOrCreate` sucht über `user_id` **und** `endpoint`, nicht nur über `endpoint`
- [ ] Modell-Schlüsseltypen stimmen mit dem Schema überein; `'id'` nicht mehr im `$fillable`
- [ ] `endpoint` fasst eine 400 Zeichen lange URL
- [ ] `config/push.php` funktioniert mit gecachter Konfiguration
- [ ] `410`/`404` löscht das Abo, `429`/`5xx` nicht
- [ ] Worker-Dienst läuft nicht als `ELYO_RUNTIME: full`
- [ ] Kein Berechtigungsdialog beim Laden der Anwendung
- [ ] `AccountDeletionService` zählt und entfernt Abos und Einstellungen
- [ ] Retention-Matrix nennt `push_subscriptions`
- [ ] Audit unverändert, solange P1 dazu nichts sagt
- [ ] Baseline nicht editiert; neue Migration mit funktionierendem `down()`
- [ ] Deptrac, Privacy-Suite, Angular-Build grün
- [ ] Doku: Kapitel 4.12, 6.2, 7.1, 8, 9 und 12 aktualisiert

## Expected output

- Geänderte und neue Dateien je Etappe, mit Commit und Befund- bzw. Entscheidungsbezug
- Die P1- und P2-Aufbereitung als eigenes Dokument in `docs/ai-results/`
- Welche Benachrichtigungsarten gebaut wurden und welche bewusst nicht
- Ob eine Migration nötig war und in welchem Domänenverzeichnis
- Der VAPID-Rotationsablauf, wie er in `docs/deployment/` gelandet ist
- Neue Tests und Specs mit dem tatsächlich gelaufenen Testbefehl
- Was bewusst nicht umgesetzt wurde und warum
