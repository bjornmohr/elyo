# Paket 07: Einladung und Onboarding

**Priorität:** 2 · **Bereich:** Backend + Frontend · **Etappen:** 5
**Befunde:** A7, D10, G7, H3, I1, U12

```ai-run
complexity:        hoch
implement_tier:    standard
implement_effort:  high
review_tier:       standard
review_effort:     medium
blocked_by:        U12
depends_on:        08
```

## Goal

Den einzigen Weg in das System — die Einladung — sicher, nachvollziehbar und ohne
Token-Auslieferung über die API gestalten.

## Context

Es gibt **keine Selbstregistrierung**. Der einzige Weg zu einem Konto ist eine Einladung, erzeugt
an zwei Stellen (`CompanyInvitationController::storeInvitation`,
`AdminCompanyController::inviteCompanyAdmin`). Beide erzeugen `Str::random(64)`, speichern nur den
SHA-256-Hash — **und geben den Rohtoken im JSON-Response zurück**, markiert `// DEV only`. Das
Frontend zeigt ihn an.

Es existiert **keine Mailable-Klasse, keine Notification und kein `Mail::`-Aufruf** im gesamten
Repository. Mailpit läuft im Compose-Stack (UI 8025, SMTP 1025), wird aber nie angesprochen.

Der Annahmepfad (`InviteAcceptanceService::accept()`) ist fachlich dicht — Company-Konflikt,
Team-Konflikt, Team-Zugehörigkeitsprüfung, idempotente Subject-Provisionierung mit Retry-Kompensation.
Zwei Lücken bleiben:

- **Parallele Annahme desselben Tokens ist nicht abgesichert.** `invite_tokens` hat keinen
  Unique-Index auf `(token_hash, status)`, und die Statusprüfung erfolgt ohne Sperre. Der zweite
  Aufruf scheiterte am Unique-Index auf `users.email` — mit einem ungefangenen 500.
- **Bei bestehendem Nutzer werden Name und Passwort ignoriert** (offene Frage U12). Das Verhalten
  ist implementiert, aber nicht kommentiert — und es ist der Grund, warum eine neue Einladung kein
  Ersatz für eine Passwort-Zurücksetzung ist.

## Umsetzung in Etappen

### Etappe 1 — Mailversand einführen (H3)

- Mailable oder Notification für die Einladung. **Der Rohtoken geht ausschließlich in die Mail.**
- Sprache: Die Oberfläche ist durchgängig deutsch, `APP_LOCALE` steht aber auf `en` (Befund E7,
  Paket 08). Für die Mailtexte hier eine bewusste Entscheidung treffen und benennen.
- Test mit `Mail::fake()`: genau eine Mail an die richtige Adresse.
- **Abnahme:** Einladung erzeugt eine Mail; in Mailpit nachvollziehbar.

### Etappe 2 — Rohtoken aus der API entfernen (A7)

- `invite_token` aus **beiden** Antworten entfernen:
  - `CompanyInvitationController::storeInvitation`
  - `AdminCompanyController::inviteCompanyAdmin`
- Das ist eine **bewusste Vertragsänderung** — `docs/api/openapi.yaml` mitziehen.
- Frontend anpassen: `CompanyInvitationsComponent::lastToken` und
  `AdminCompaniesCreateComponent::inviteToken` entfernen, stattdessen „Einladung versendet".
- **Achtung:** `AdminCompaniesCreateComponent` führt zwei Requests nacheinander aus
  (Firma anlegen, dann Admin einladen) ohne Kompensation. Schlägt der zweite fehl, bleibt eine
  Firma **ohne Administrator** — und es gibt keinen Weg über die Oberfläche, das nachzuholen
  (Befund I6). Diese Etappe ist der richtige Zeitpunkt, das mitzulösen: entweder ein kombinierter
  Endpunkt oder eine nachträgliche Einladungsmöglichkeit in der Firmenliste.
- **Abnahme:** Kein Rohtoken mehr in einer HTTP-Antwort, in keinem Log und in keiner UI;
  Firmenanlage ohne Administrator ist nicht mehr als Sackgasse möglich.

### Etappe 3 — Parallele Annahme absichern (I1)

- Sperre oder Unique-Constraint einführen. Optionen:
  - `SELECT … FOR UPDATE` auf die Invite-Zeile innerhalb der bestehenden Transaktion
  - Partieller Unique-Index — Vorbild existiert bereits im Repository:
    `measure_checkin_tokens_one_active_per_measure ON (measure_id) WHERE revoked_at IS NULL`
- **Achtung:** Der Migrationskommentar dort warnt ausdrücklich, dass Blueprints
  `unique()->whereNull()` ein **stiller No-op** ist und einen *vollen* Unique-Index erzeugt.
  Partielle Indizes nur per `DB::statement()`.
- Den Fehlerfall sauber abfangen: doppelte Annahme liefert eine definierte Antwort, keinen 500.
- **Abnahme:** Test mit zwei parallelen Annahmen desselben Tokens: einer gelingt, einer erhält
  eine definierte Fehlerantwort.

### Etappe 4 — Model Binding vereinheitlichen (D10)

- `DELETE /company/invitations/{invite}` nutzt als eine von wenigen Company-Routen implizites
  Route Model Binding — das lädt **ohne** Company-Filter, die Prüfung erfolgt erst danach im
  Controller.
- Das ist funktional korrekt, weicht aber vom sonst durchgängigen Muster ab („im Query filtern"),
  das bewusst gewählt wurde, weil implizites Binding keinen Mandanten-Scope kennt.
- Angleichen oder die Ausnahme im Code begründen.
- **Abnahme:** Muster ist einheitlich oder die Ausnahme ist kommentiert.

### Etappe 5 — Annahmesemantik klären (U12) und toten Code entfernen (G7)

- **Blockiert durch offene Frage U12** (Paket 16).
- `InviteAcceptanceService::accept()` ignoriert bei einem bestehenden Nutzer Name und Passwort.
  Zusätzlich löst dieser Pfad **keine** Subject-Provisionierung aus — der Nutzer hatte aber bereits
  eines aus seiner Ersteinladung.
- Entscheidung dokumentieren und im Code kommentieren. Falls das Verhalten geändert wird: Auswirkung
  auf die Passwort-Reset-Entscheidung aus Paket 01, Etappe 6 beachten.
- **G7:** `InviteToken::isPending()` und `::isExpired()` haben **keinen Aufrufer** — die Prüfung
  erfolgt stattdessen als Query-Bedingung (`where('status','pending')->where('expires_at','>',now())`).
  Entfernen oder verwenden.
- **Abnahme:** Semantik ist im Code kommentiert; kein toter Code mehr im Modell.

## Out of Scope

- Passwort-Zurücksetzung (Paket 01, Etappe 6)
- Rate-Limiting auf `/auth/invite/verify` (Paket 01, Etappe 1)
- Partner-Statusmails (Paket 06) — konsumieren die hier gebaute Infrastruktur

## Hard constraints

- **Der Rohtoken darf nach diesem Paket an keiner Stelle mehr in einer HTTP-Antwort oder in einem
  Log erscheinen**
- Die 7-Tage-Gültigkeit bleibt unverändert, sofern nicht ausdrücklich anders entschieden
- Der 404 bei `GET /auth/invite/verify` bleibt **ununterscheidbar** zwischen unbekannt, bereits
  angenommen, widerrufen und abgelaufen
- `MAIL_MAILER` bleibt in der Testumgebung `array` (`phpunit.xml` erzwingt das)
- Partielle Unique-Indizes nur per `DB::statement()`, nie über den Blueprint-Fluent
- Kein `migrate:fresh`; Schemaänderungen nur als neue Migration im Identity-Verzeichnis

## Review-Checkliste

- [ ] Kein `invite_token` mehr in einer API-Antwort oder UI
- [ ] OpenAPI ist mitgezogen
- [ ] Firmenanlage ohne Administrator ist keine Sackgasse mehr
- [ ] Parallele Annahme erzeugt keinen 500
- [ ] Falls ein partieller Index angelegt wurde: per `DB::statement()`, nicht per Blueprint
- [ ] Der 404 bei Invite-Verify bleibt ununterscheidbar
- [ ] Annahmesemantik für bestehende Nutzer ist im Code kommentiert
- [ ] Kapitel 4.3, 6.1 und 6.3 der Dokumentation aktualisiert

## Expected output

- Geänderte Dateien je Etappe
- Entscheidung zur Mailtext-Sprache
- Gewählter Mechanismus gegen parallele Annahme
- Entscheidung zu U12
- Neue Tests je Etappe
- Ob eine Migration nötig war
