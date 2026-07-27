# Paket 04: Infrastruktur- und Betriebshärtung

**Priorität:** 1 · **Bereich:** Infra · **Etappen:** 8
**Befunde:** A8, A9, A10, A11, A13, A14, B4, B6, B7, B8, B9, B10, B11, B12, H18

```ai-run
complexity:        mittel
implement_tier:    standard
implement_effort:  medium
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

**U8 entschieden am 27.07.2026:** Nur die **Dopplungen** auflösen — `docs/further-docs/`
(1 Datei) nach `docs/further_docs/` und `docs/decisions/` (2 Dateien) nach `docs/adr-documents/`.
Die übrigen 14 Verzeichnisse bleiben, wo sie sind. Keine Umstrukturierung, keine Umbenennung.
Querverweise auf verschobene Dateien mitziehen. Etappe 8 ist **nicht mehr blockiert**.

Keine. Alle Etappen sind aus dem Code heraus entscheidbar.


## Goal

Den Stack so aufstellen, dass er außerhalb der lokalen Entwicklung betrieben werden kann.

## Context

Die Runtime-Aufteilung selbst ist belastbar: Fail-Safe-Profilauflösung, Credential-Isolation je
Service, `assertMatchesEnvironment()` gegen falsch gecachte Konfiguration. Die offenen Punkte
liegen um sie herum.

**Dieses Paket zuerst umsetzen**, weil Etappe 1 (`api-tooling` hinter ein Profil) alle
Test- und CI-Aufrufe betrifft und damit Paket 09 vorwegnimmt.

## Umsetzung in Etappen

### Etappe 1 — `api-tooling` hinter ein Compose-Profil (A9)

- `api-tooling` läuft heute **ohne** `profiles:`-Eintrag und startet bei jedem `docker compose up`.
  Er hält alle vier Runtime-Rollen, die Migrator-Rolle und das gesamte Mapping-Schlüsselmaterial.
- Die Absicht ist nur ein Kommentar: „LOCAL DEVELOPMENT ONLY — never deploy this service."
- Profil `tools` vergeben, konsistent zum bereits korrekt konfigurierten `migrate`-Service.
- **Alle Aufrufer mitziehen:** `Makefile` (`test`, `test-boundary`, `deptrac`),
  `.github/workflows/privacy.yml`, Setup-Anleitung.
- **Achtung:** `docker compose run` aktiviert ein Profil automatisch, `exec` und `up` **nicht**.
  Verhalten verifizieren und im Handoff festhalten.
- **Abnahme:** `docker compose up -d` startet `api-tooling` nicht mehr; `make test` funktioniert.

### Etappe 2 — Dev-Passwörter entschärfen (A14)

- Dieselben fünf Rollenpasswörter stehen an **drei** Stellen im Klartext: `.env.example`,
  `docker-compose.yml` (als `${VAR:-default}`-Fallback je Service) und
  `infra/postgres/initdb/01-databases-and-roles.sh` (als `: "${VAR:=default}"`).
- Die Compose-Fallbacks greifen **still**, wenn die Variable fehlt — es gibt keinen Abbruch.
- Empfohlene Richtung: Fallbacks entfernen und einen klaren Fehler erzeugen, analog zu
  `RuntimeProfile::validate()`, das bei fehlendem `ELYO_RUNTIME` außerhalb local/testing abbricht.
- Falls Fallbacks für die lokale Entwicklung bleiben: an **einer** Stelle zusammenführen.
- **Achtung:** `initdb` läuft nur auf einem frischen Volume. Eine Änderung wirkt erst nach
  `docker compose down -v && docker compose up -d postgres` — im Handoff benennen, hier **nicht**
  ausführen.
- **Abnahme:** Keine Rollenpasswörter mehr an drei Stellen; Setup-Anleitung angepasst.

### Etappe 3 — `APP_DEBUG` und `DB_SSLMODE` (A10, A11)

- `APP_DEBUG` in `.env.example` produktionssicher setzen; der explizite `"true"`-Wert in
  `docker-compose.yml` darf für lokal bleiben.
- `DB_SSLMODE` steht heute in **keiner** `.env.example` und hat den Code-Default `prefer` —
  also „verschlüsseln, wenn der Server es anbietet, sonst nicht, ohne Fehler". Über diese
  Verbindungen laufen Gesundheitsdaten, das Subject-Mapping und das Auditprotokoll.
- Variable aufnehmen und dokumentieren; Default auf `require` erwägen, mit lokalem Override.
- **Achtung:** Der lokale `postgres:16-alpine` bietet ohne Konfiguration kein TLS an. Bei
  Default-Wechsel muss der lokale Stack weiter starten oder der Zusatzschritt dokumentiert sein.
- **Abnahme:** Beide Variablen dokumentiert; lokaler Stack und Testsuite laufen.

### Etappe 4 — API-Image härten (B4)

- Heute: kein Multi-Stage, kein `--no-dev`, kein `USER`, kein `HEALTHCHECK`. PHPUnit, Deptrac,
  Faker und Pint landen im Image.
- Multi-Stage-Build; Runtime-Stage ohne Entwicklungsabhängigkeiten; non-root; `HEALTHCHECK`.
- **Achtung:** Der Bind-Mount `./apps/api-laravel:/var/www` überdeckt im lokalen Betrieb das
  Image-`vendor/`. Die Entwicklungserfahrung darf sich nicht verschlechtern — ggf. getrennte
  Stages für Entwicklung und Produktion.
- **Abnahme:** Produktions-Image ohne Dev-Abhängigkeiten; lokaler Betrieb unverändert.

### Etappe 5 — Health-Check und Runtime-Offenlegung (B9, B10)

- **B10:** `GET /api/health` prüft nur die Default-Connection (`identity`) über
  `DB::connection()->getPdo()`. Ein Ausfall der Audit-Datenbank blockiert **alle** schreibenden
  Health-Operationen, wird aber als „up" gemeldet. Prüfung auf alle Connections des aktiven
  Profils ausweiten (`RuntimeProfile::connections()`).
- **B9:** Die Antwort enthält `{"runtime":"employee"}` und legt damit die Topologie offen —
  im Gegensatz zur nginx-Konfiguration, die bewusst keinen Runtime-Header setzt. Entfernen oder
  auf einen authentifizierten Endpunkt verlagern.
- `/up` (Framework-Healthcheck aus `bootstrap/app.php`) ist in keiner Konfiguration referenziert —
  entweder als Container-Healthcheck nutzen oder entfernen.
- **Abnahme:** Ausfall einer Domänenverbindung wird erkannt; keine Runtime-Kennung in der Antwort.

### Etappe 6 — Welcome-Route und Dateireste (B8, B11, B12)

- **B8:** `routes/web.php` enthält `Route::get('/', fn() => view('welcome'))`. Unter
  `http://localhost:8080/` erscheint damit die Laravel-Willkommensseite statt des Frontends.
  Entfernen oder auf das Frontend weiterleiten.
- **B11:** `apps/api-laravel/database/database.sqlite` existiert, obwohl die sqlite-Testlane laut
  ADR-003 D9 entfernt wurde. Entfernen.
- **B12:** `docs/further-docs/` und `docs/further_docs/` existieren parallel.
  **Blockiert durch offene Frage U8** (Paket 16) — welches ist maßgeblich?
- **Abnahme:** Keine irreführende Startseite, keine verwaisten Dateien.

### Etappe 7 — n8n entkoppeln (A8)

- n8n nutzt den **Postgres-Superuser** der Legacy-Datenbank `elyo`
  (`POSTGRES_USER`/`POSTGRES_PASSWORD`). Der Superuser hätte technisch Zugriff auf **alle**
  Domänendatenbanken — die Rollentrennung schützt gegen die Anwendungsrollen, nicht gegen ihn.
- Eigene, minimal berechtigte Rolle für n8n anlegen, ausschließlich auf `elyo`.
- **Achtung:** Die Rollenanlage gehört ins initdb-Skript und wirkt erst nach einem Volume-Reset.
- **Abnahme:** n8n startet mit einer nicht-privilegierten Rolle; Zugriff auf die vier
  Domänendatenbanken ist für diese Rolle verweigert (prüfbar über `check-grants.sh`).

### Etappe 8 — Betriebsgrundlagen dokumentieren (A13, B6, B7, H18)

- **A13:** Es gibt **kein** dokumentiertes Backup-, Restore- oder Schlüsselrotationsverfahren.
  Besonders kritisch: Eine Rotation von `MAPPING_HMAC_KEY` macht **alle Mappings unauffindbar**,
  weil der Lookup über die HMAC-Spalte läuft. Eine Rotation von `APP_KEY` macht die sechs
  verschlüsselten Anamnesefelder unlesbar. Verfahren beschreiben, auch wenn es zunächst nur
  „nicht rotierbar ohne Migrationsskript" lautet.
- **B6:** Keine Deployment-Pipeline, kein Registry-Push, kein Release-Prozess. Mindestens einen
  Build-und-Push-Workflow anlegen.
- **B7:** Keine Observability — kein Monitoring, kein Tracing, kein strukturiertes Logging.
  Mindestens strukturiertes Logging und einen Container-Healthcheck vorsehen.
  **Hinweis:** `DatabaseAuditLogger` führt bereits eine `correlation_id` je Request
  (`X-Correlation-ID`, sonst generierte ULID) — sie ist der natürliche Anknüpfungspunkt, wird vom
  Anwendungslog aber nicht genutzt.
- **H18:** `reporting-worker` und `api-privacy` sind leere Platzhalter (`sleep infinity`, keine
  domänenspezifischen Zugangsdaten). Solange `api-privacy` fehlt, laufen Retention und
  Subject-Reparatur manuell in `api-tooling`. Roadmap festhalten.
- **Abnahme:** Betriebshandbuch als Repository-Dokument; mindestens ein Deployment-Workflow.

## Out of Scope

- Frontend-Auslieferung (Paket 03)
- CI-Testabdeckung (Paket 09)
- Audit-Grant-Diskrepanz (Paket 05)

## Hard constraints

- Kein `docker compose down -v` im Rahmen dieses Pakets — nötige Volume-Resets nur benennen
- Die Credential-Isolation der drei Runtime-Container darf nicht aufgeweicht werden
- Die Testsuite muss nach jeder Etappe lauffähig bleiben
- `initdb`-Änderungen wirken erst nach Volume-Reset — im Handoff benennen

## Review-Checkliste

- [ ] Jede Etappe ist ein eigener Commit mit Befund-ID
- [ ] `docker compose up -d` startet `api-tooling` nicht mehr, `make test` funktioniert weiterhin
- [ ] Keine Rollenpasswörter mehr an drei Stellen
- [ ] `APP_DEBUG` und `DB_SSLMODE` dokumentiert, lokaler Stack läuft
- [ ] Produktions-Image ohne Dev-Abhängigkeiten, lokaler Betrieb unverändert
- [ ] Health-Check erkennt den Ausfall einer Domänenverbindung
- [ ] Keine Runtime-Kennung mehr in der Health-Antwort
- [ ] n8n läuft ohne Superuser
- [ ] Schlüsselrotation ist beschrieben, auch wenn das Ergebnis „nicht rotierbar" lautet
- [ ] Nötige Volume-Resets sind benannt, nicht ausgeführt
- [ ] Kapitel 3, 9 und 12 der Dokumentation aktualisiert

## Expected output

- Geänderte Dateien je Etappe
- Entscheidungen zu `DB_SSLMODE`-Default und Compose-Fallbacks
- Betriebshandbuch mit Backup-, Restore- und Rotationsverfahren
- Welche Änderungen einen Volume-Reset erfordern
- Nachweis, dass lokaler Stack und Testsuite laufen
