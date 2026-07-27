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

### Etappe 6 — Passwort-Zurücksetzung entscheiden (H4)

- **Variante A:** Umsetzen — Migration für `password_reset_tokens` (neue Datei im
  Identity-Verzeichnis), zwei Routen, Mailable (Abhängigkeit zu Paket 07), Frontend-Formular.
- **Variante B:** Broker-Konfiguration aus `config/auth.php` entfernen und dokumentieren, wie ein
  ausgesperrter Nutzer administrativ Zugang erhält.
- Bei Variante A: Die Antwort auf „Passwort vergessen" muss **unabhängig von der Existenz der
  E-Mail** identisch sein. Das Repository verfolgt dieses Prinzip bereits bei Login und
  Invite-Verify konsequent.
- Bei Variante B: Nebenbefund U12 wird dringlich — `InviteAcceptanceService::accept()` ignoriert
  bei bestehendem Nutzer Name und Passwort, der Weg über eine neue Einladung funktioniert also nicht.
- **Abnahme:** Entscheidung dokumentiert; bei A ein grüner End-to-End-Test, bei B keine
  verwaiste Konfiguration mehr.

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
