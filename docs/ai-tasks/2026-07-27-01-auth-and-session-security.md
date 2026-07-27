# Paket 01: Authentifizierung und Sitzungssicherheit

**Priorität:** 1 · **Bereich:** Backend + Frontend · **Etappen:** 6
**Befunde:** A1, A2, A6, A12, D5, E5, H4

```ai-run
complexity:        hoch
implement_tier:    high
implement_effort:  high
review_tier:       standard
review_effort:     medium
blocked_by:        -
depends_on:        -
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

**Entschieden am 27.07.2026:** Die Passwort-Zurücksetzung (Etappe 6) wird **fertig gebaut**,
nicht entfernt. Siehe `2026-07-27-entscheidungen.md`.

Eine Etappe mit Zeile **Björn** wird nicht selbst entschieden. Lage aufbereiten, Optionen mit
Konsequenzen nach `docs/ai-results/` schreiben, Etappe als blockiert markieren, weitermachen.

| Etappe | Entscheidung | Wer |
|---|---|---|
| 1 | Konkrete Limits und Zeitfenster für das Rate-Limiting | Agent, Begründung in den Commit |


## Goal

Die Anmelde- und Sitzungsschicht so härten, dass ein gestohlenes oder erratenes Token begrenzten
Schaden anrichtet und ein Nutzer bei abgelaufener Sitzung nicht in einer toten Oberfläche landet.

## Context

Vier Eigenschaften wirken zusammen und verstärken sich gegenseitig:

- **Kein Rate-Limiting.** Keine `throttle`-Middleware, kein `RateLimiter::for()` im gesamten
  Repository. Betroffen: `POST /auth/login`, `GET /auth/invite/verify`, `POST /auth/invite/accept`,
  `POST /partner/register`, `POST /partner/login`.
- **Tokens ohne Ablauf und ohne Abilities.** `config/sanctum.php` unverändert, `expiration = null`.
  `logout` löscht nur das aktuelle Token.
- **Token im `localStorage`** (`AuthStore`), damit XSS-exponiert.
- **Kein 401-Handling im Frontend.** Es gibt nur den `authInterceptor` (Token setzen), keinen
  Error-Interceptor. Läuft ein Token ab, scheitern alle Requests still.

Dazu zwei kleinere Befunde: `InviteComponent` hat keinen Passwort-Bestätigungsvalidator (die
Prüfung erfolgt allein serverseitig über `confirmed`), und `PortalMiddleware` ruft `canUsePortal()`
ohne Typprüfung auf — ein `Partner`-Prinzipal hätte diese Methode nicht.

Ungelöst ist außerdem, dass es **keine Passwort-Zurücksetzung** gibt: `config/auth.php` definiert
den Broker `passwords.users` mit der Tabelle `password_reset_tokens`, **die in keiner Migration
existiert**, und es gibt keine Route.

## Umsetzung in Etappen

Jede Etappe ist ein eigener Commit mit eigenem Test.

### Etappe 1 — Rate-Limiting (A1)

- Named Rate Limiter definieren (`auth` streng, `public` locker) in `bootstrap/app.php` oder einem
  Provider.
- Auf die fünf öffentlichen Auth-Routen anwenden.
- Für `GET /api/health` entscheiden — das Frontend ruft ihn bei jedem Start auf. Entscheidung
  im Code kommentieren.
- Antwort im Codeformat: `{"error":{"code":"TOO_MANY_REQUESTS",…}}`.
- **Achtung:** nginx setzt derzeit **keine** Forwarded-Header (`infra/docker/nginx/default.conf`).
  Prüfen, welche IP der Limiter sieht.
- **Abnahme:** Test belegt, dass das Limit greift und nach Ablauf zurückgesetzt wird; die
  Testsuite läuft weiterhin durch (Limiter im Testkontext deaktiviert oder hochgesetzt).

### Etappe 2 — Tokenablauf (A2)

- `SANCTUM_TOKEN_EXPIRATION` als ENV-Variable mit dokumentiertem Default einführen.
- `personal_access_tokens.expires_at` existiert bereits und ist indiziert.
- `sanctum:prune-expired` erwähnen; der Scheduler ist deaktiviert (`routes/console.php`).
- **Achtung:** Die Employee-Runtime hat auf `personal_access_tokens` nur `SELECT` plus
  `UPDATE (last_used_at, updated_at)`. Werden weitere Spalten geschrieben, ist der Grant per
  **neuer Migration** zu erweitern (Vorbild: `identity/2026_07_26_000001_*`).
- **Abnahme:** Abgelaufenes Token wird abgewiesen; gültiges funktioniert; Auswirkung auf bestehende
  Tokens im Commit-Text benannt.

### Etappe 3 — Token-Abilities (A2)

- Entscheiden, ob Abilities je Portal vergeben werden. Falls ja: Ability-Namen festlegen.
- **Abilities sind eine zusätzliche Schranke, kein Ersatz** für `RoleMiddleware`/`PortalMiddleware`.
  Die bestehende Rollenprüfung bleibt maßgeblich.
- Falls verworfen: Entscheidung im Commit-Text begründen und Etappe überspringen.
- **Abnahme:** Test je Portal, dass ein fremdes Ability abgewiesen wird — oder dokumentierte
  Verwerfung.

### Etappe 4 — `PortalMiddleware` absichern (D5)

- Typprüfung auf `App\Models\User` ergänzen, analog zu `RoleMiddleware`, das das bereits tut:
  ```php
  if (! $user instanceof User) { return response()->json([...], 403); }
  ```
- Praktisch heute nicht erreichbar, weil Company-Routen zuvor `auth:sanctum` mit Provider `users`
  durchlaufen — die Absicherung ist dennoch billig und verhindert eine künftige Lücke.
- **Abnahme:** Test mit einem Partner-Prinzipal auf einer Company-Route liefert 403 statt eines
  Fehlers.

### Etappe 5 — 401-Behandlung im Frontend (E5)

- Error-Interceptor ergänzen (`app.config.ts`, `withInterceptors`).
- Bei 401: `AuthStore.clear()` und Navigation auf `/auth/login` mit `returnUrl`.
- **Achtung:** Der bestehende `returnUrl`-Mechanismus in `LoginComponent::redirectAfterAuth()`
  prüft bereits per `isAllowedPortalUrl()` gegen Open Redirect — diesen Schutz nutzen, nicht umgehen.
- **Abnahme:** Spec belegt, dass ein 401 zum Logout und zur Weiterleitung führt.

### Etappe 6 — Passwort-Zurücksetzung umsetzen (H4)

**Entschieden am 27.07.2026: fertig bauen.** Die Variante „Broker-Konfiguration entfernen" ist
vom Tisch. Es gibt hier nichts mehr zu entscheiden.

> **Diese Etappe setzt Paket 07, Etappe 1 voraus.**
> Im gesamten Repository existiert **keine Mailable-Klasse, keine Notification und kein
> `Mail::`-Aufruf** — Paket 07 Etappe 1 („Mailversand einführen", H3) baut diese Grundlage erst.
> Ohne sie ist eine Passwort-Zurücksetzung nicht auslieferbar.
>
> **Empfohlenes Vorgehen:** Etappen 1–5 dieses Pakets jetzt fahren
> (`scripts/run-ai-task.sh 01 --stage N`), Etappe 6 nach Paket 07 nachziehen. Wer Etappe 6
> vorzieht, muss den Mailversand hier mitbauen — das gehört dann fachlich in Paket 07 und
> verletzt Arbeitsregel 3.

**Dateien:** neue Migration unter `apps/api-laravel/database/migrations/identity/`,
`app/Http/Controllers/`, `routes/api/identity.php`, `config/auth.php`, Mailable aus Paket 07,
`apps/web-angular/src/app/features/auth/`, Tests.

- Migration für `password_reset_tokens` als **neue Datei** im Identity-Verzeichnis. **Achtung:**
  `config/auth.php` konfiguriert bereits einen Broker, dem die Tabelle fehlt — deshalb ist der
  Name durch die Konfiguration vorgegeben, nicht frei wählbar.
- Zwei Routen: Anforderung und Einlösung. Beide über den Rate-Limiter aus **Etappe 1** dieses
  Pakets — eine unbegrenzte Anforderung ist ein Mailversand-Verstärker.
- **Achtung:** Die Antwort auf „Passwort vergessen" muss **unabhängig von der Existenz der
  E-Mail-Adresse** identisch sein — gleicher Statuscode, gleicher Rumpf, keine messbare
  Laufzeitdifferenz. Das Repository verfolgt dieses Prinzip bei Login und Invite-Verify bereits
  konsequent; hier nicht davon abweichen.
- Token einmalig verwendbar, mit Ablauf. Nach erfolgreicher Zurücksetzung **alle bestehenden
  Sanctum-Tokens des Nutzers widerrufen** — sonst behält ein Angreifer seine Sitzung.
- **Achtung:** Der Rohtoken geht ausschließlich in die Mail, nie in eine API-Antwort. Das ist
  derselbe Fehler, den Paket 07 Etappe 2 (A7) für Einladungen behebt.
- **Abnahme:** End-to-End-Test von Anforderung bis Anmeldung mit dem neuen Passwort; Test, dass
  eine unbekannte Adresse dieselbe Antwort erhält; Test, dass ein Token nur einmal wirkt; Test,
  dass alte Tokens nach der Zurücksetzung ungültig sind.

### Nicht in diesem Paket (A6 — Token im `localStorage`)

Die Umstellung auf ein `HttpOnly`-Cookie wäre ein Architekturwechsel: Sanctum müsste im
SPA-Modus mit CSRF-Schutz betrieben werden, `authInterceptor` entfiele, und die Domänen-/
Cookie-Konfiguration hinge an der Deployment-Topologie, die noch offen ist (Paket 03).

**Diese Etappe wird bewusst zurückgestellt** und in Paket 16 als Architekturentscheidung
aufgenommen. Im Handoff ist zu vermerken, dass A6 offen bleibt.

## Out of Scope

- Refresh-Token-Mechanismus
- „Überall abmelden"
- Verteilter Rate-Limit-Speicher (Redis) — Cache-Umstellung ist Paket 13, Etappe 5
- Vereinheitlichung aller Fehlerformate (Paket 08)

## Hard constraints

- Die generischen Fehlermeldungen bei ungültigen Zugangsdaten **bleiben unverändert** — sie
  verhindern Nutzerenumeration und sind an drei Stellen bewusst identisch
  (`AuthController::login`, `PartnerAuthController::login`, `InviteController::verify`)
- Kein `migrate:fresh`; Schemaänderungen nur als neue Migration
- Die Privacy-Suite muss grün bleiben

## Review-Checkliste

- [ ] Jede Etappe ist ein eigener Commit mit Befund-ID im Commit-Text
- [ ] Rate-Limit-Werte sind begründet, nicht geraten
- [ ] Entscheidung zu `/api/health` ist im Code kommentiert
- [ ] Auswirkung des Tokenablaufs auf bestehende Tokens ist benannt
- [ ] Falls ein Grant erweitert wurde: als neue Migration, nicht in der Baseline
- [ ] Abilities: umgesetzt oder begründet verworfen
- [ ] 401-Interceptor umgeht den Open-Redirect-Schutz nicht
- [ ] Passwort-Reset: Variante gewählt und begründet, keine verwaiste Konfiguration
- [ ] A6 (`localStorage`) ist als offen an Paket 16 übergeben
- [ ] Kapitel 4.2 und 13 der Dokumentation aktualisiert

## Expected output

- Geänderte Dateien je Etappe
- Gewählte Rate-Limits und Ablaufzeit mit Begründung
- Entscheidungen zu Abilities und Passwort-Reset
- Neue Tests je Etappe
- Offen gebliebene Befunde mit Begründung
