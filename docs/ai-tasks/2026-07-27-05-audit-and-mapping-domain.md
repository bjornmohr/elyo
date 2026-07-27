# Paket 05: Audit- und Mapping-Domäne

**Priorität:** 1 · **Bereich:** Backend + Infra · **Etappen:** 5
**Befunde:** B1, G4, G5, G12, G22, J16

```ai-run
complexity:        hoch
implement_tier:    high
implement_effort:  high
review_tier:       high
review_effort:     high
blocked_by:        U9, U11
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

Eine Etappe mit Zeile **Björn** wird nicht selbst entschieden. Lage aufbereiten, Optionen mit
Konsequenzen nach `docs/ai-results/` schreiben, Etappe als blockiert markieren, weitermachen.

| Etappe | Entscheidung | Wer |
|---|---|---|
| 1 | Weg zur Grant-Korrektur: Migration oder Provisionierungsskript (B1) | Agent, Begründung in den Commit |
| 2 | `audit_events.subject_ref` befüllen oder Spalte fallenlassen (G22) | **Björn** — Etappe blockiert |


## Goal

Die Schutzdomäne vervollständigen: Das Audit muss lückenlos schreibbar sein, und die
implementierte Kontolöschung muss erreichbar werden.

## Context

Die Mapping-Domäne ist die architektonisch stärkste Komponente des Systems — Zweckbindung,
synchrones Audit innerhalb der Domänentransaktion, Tombstone-Semantik, deterministische
Subject-Ableitung zur Waisen-Adoption. Die offenen Punkte betreffen ihre Ränder.

**Der gravierendste ist B1**, weil er einen stillen Ausfall erzeugt.

## Umsetzung in Etappen

### Etappe 1 — Grant-Diskrepanz Audit/Identity beheben (B1)

**Das initdb-Skript gewährt `elyo_identity_rt` kein `CONNECT` auf `elyo_audit`.**

In `infra/postgres/initdb/01-databases-and-roles.sh`, Funktion `grant_audit()`:

```sql
GRANT CONNECT ON DATABASE :"db" TO elyo_migrator, elyo_employee_rt, elyo_company_rt, elyo_mapping_svc;
GRANT USAGE  ON SCHEMA public   TO elyo_employee_rt, elyo_company_rt, elyo_mapping_svc;
```

`elyo_identity_rt` fehlt in beiden Zeilen. `docker-compose.yml` konfiguriert dem Service
`api-identity` jedoch genau diese Verbindung, und `RuntimeProfile::CONNECTIONS['identity']`
führt `audit`.

**Auswirkung:** Der einzige Audit-Schreiber im Identity-Pfad ist `MappingService` über
`InviteAcceptanceService::accept()`. Dessen Fehler wird abgefangen und **nur geloggt**:

```php
} catch (Throwable) {
    Log::warning('Health subject provisioning failed after invite acceptance; run elyo:provision-subjects.');
}
```

Ergebnis: **stiller Ausfall der Subject-Provisionierung bei jeder Einladungsannahme** in der
Identity-Runtime. Der Nutzer wird angelegt, das Health-Subject nicht.

**Umsetzung:**

- **Variante A (naheliegend):** `elyo_identity_rt` erhält `CONNECT`, `USAGE` und `INSERT` auf
  `elyo_audit` — append-only wie die anderen Rollen. Entspricht Compose und Runtime-Profil und
  erfüllt ADR-001, wonach jede Mapping-Operation auditiert wird.
- **Variante B:** Die Identity-Runtime schreibt kein Audit; dann Audit-Connection aus
  `RuntimeProfile` und `docker-compose.yml` entfernen — **und klären, wo die Provisionierung beim
  Invite-Accept stattdessen auditiert wird.** Variante B lässt sie unauditiert und ist damit
  vermutlich nicht ADR-konform.
- Grants ergänzen im initdb-Skript **und** als Migration im Audit-Verzeichnis, weil initdb nur auf
  frischem Volume läuft. Vorbild: `identity/2026_07_26_000001_grant_employee_runtime_sanctum_token_touch.php`.
- `infra/postgres/check-grants.sh` um eine Prüfung erweitern.
- **Testlücke schließen:** `PostgresRoleBoundaryTest` prüft `employee_rt`, `company_rt` und
  `mapping_svc` gegen Audit, aber **nicht** `identity_rt`.
- **Abnahme:** Provisionierung beim Invite-Accept funktioniert in der Identity-Runtime; neuer
  Boundary-Test; `check-grants.sh` grün.

### Etappe 2 — `audit_events.subject_ref` entscheiden (G22)

- Die Spalte wird **nie befüllt** — `DatabaseAuditLogger::insertEvent()` übergibt an allen drei
  Aufrufstellen `null`.
- Die CHECK-Constraint `(subject_ref IS NULL OR user_ref IS NULL)` ist damit strukturell erfüllt
  und schützt nur gegen künftige Erweiterungen.
- Entscheiden: Spalte behalten (mit dokumentiertem Zweck) oder entfernen. **Die Constraint muss
  bleiben**, falls die Spalte bleibt — sie ist die Kerninvariante.
- **Abnahme:** Entscheidung dokumentiert; `AuditInvariantPrivacyTest` grün.

### Etappe 3 — `MappingRuntime` an die Realität binden (J16)

- `AuditActorContext` setzt den Akteur **fest je Operation**, nicht aus dem Request. `MappingRuntime`
  behauptet damit etwa `employee-health-api`, obwohl im Profil `full` alles im selben Prozess läuft.
- Der Wert ist im Audit irreführend.
- Optionen: aus `config('runtime.profile')` ableiten, oder den Wert als „vorgesehene Runtime"
  umbenennen und dokumentieren.
- **Abnahme:** Der Audit-Eintrag beschreibt entweder die tatsächliche Runtime oder das Feld ist
  eindeutig als Soll-Angabe benannt.

### Etappe 4 — Kontolöschung anbinden (G4)

- **Blockiert durch offene Frage U11** (Paket 16): Wo liegt der 30-Tage-Karenzworkflow?
- `AccountDeletionService::deleteUser()` ist **vollständig implementiert und getestet**
  (`tests/Feature/Privacy/AccountDeletionServiceTest.php`, 5 Tests), hat aber **keinen Aufrufer**.
  Der Klassenkommentar verweist auf einen Karenzworkflow, der im Repository nicht existiert.
- Die Löschreihenfolge ist bereits richtig gelöst: Health- und Identity-Daten werden gelöscht,
  **während** das Mapping noch `ACTIVE` ist; der Tombstone entsteht erst danach.
  `requireNoSubjectRowsRemain()` bricht ab, wenn eine Kaskade unvollständig war.
- Umsetzung: Route, Kommando oder Job, je nach Entscheidung aus U11.
- **Achtung:** Nach der Löschung ist das Mapping ein Tombstone. Jeder Health-Zugriff dieses
  Nutzers wirft dann `MappingRevokedException` — deren Behandlung ist Paket 02, Etappe 3.
  **Reihenfolge beachten.**
- **Abnahme:** Löschung ist auslösbar; End-to-End-Test über alle vier Datenbanken.

### Etappe 5 — Retention absichern (G12, J27, J28) und `decryptUserId` (G5)

- **Blockiert durch offene Frage U9** (Paket 16): Darf Retention `PROPOSED`-Kategorien löschen?
- `EnforceRetention` löscht mit `--execute` **auch** die zehn als `PROPOSED` markierten
  Kategorien. Der Status ist reine Anzeige, keine Sperre. Der einzige Schutz ist der
  auskommentierte Scheduler in `routes/console.php`, dessen Kommentar die Aktivierungsbedingungen
  nennt: alle Fristen `DECIDED` **und** eine dedizierte Wartungs-Runtime.
- Sperre einbauen: `--execute` verweigert `PROPOSED`-Kategorien, es sei denn, ein zusätzliches
  `--force-proposed` wird gesetzt.
- **J28:** `handle()` gibt **immer** `SUCCESS` zurück, auch ohne Löschung. Ein CI-Aufruf könnte
  keinen Fehler erkennen. Exitcode differenzieren.
- **G5:** `MappingCryptography::decryptUserId()` hat keinen Produktivaufrufer — sie ist die
  Rückrichtung für Betroffenenanfragen (DSR), die als Not-Implemented-Guard vorliegt. Entweder
  als bewusste API dokumentieren oder mit den Guards zusammen behandeln.
- **G12:** `resolveReportingCohort()` und `resolveForDataSubjectRequest()` werfen bewusst
  `OperationNotAvailableException` (ADR-003 D5). Das ist korrekt — hier nur dokumentieren,
  damit es nicht versehentlich als toter Code entfernt wird.
- **Abnahme:** `--execute` löscht keine `PROPOSED`-Kategorie ohne ausdrückliche Bestätigung;
  Exitcode spiegelt das Ergebnis.

## Out of Scope

- Änderung der Zweckbindung oder der Tombstone-Semantik
- Reporting-Domäne (Paket 10)
- Behandlung von `MappingRevokedException` (Paket 02, Etappe 3)

## Hard constraints

- **Audit bleibt append-only:** kein `UPDATE`, kein `DELETE` für eine Runtime-Rolle
- **`SELECT` auf Audit bleibt auf `elyo_mapping_svc` beschränkt**
- Ein widerrufenes Mapping wird **niemals** re-provisioniert
- Der Audit-Insert bleibt **synchron innerhalb** der Domänentransaktion — kein `catch` darum,
  keine Queue. Das ist die Invariante „keine Änderung ohne Auditspur"
- Kein `docker compose down -v`; der Migrationspfad muss ohne Volume-Reset funktionieren
- `tests/Boundary/*` und `tests/Privacy/*` müssen grün bleiben

## Review-Checkliste

- [ ] B1: Gewählte Variante ist begründet und ADR-konform
- [ ] B1: Grant sowohl im initdb-Skript als auch als Migration
- [ ] B1: Neuer Boundary-Test für `identity_rt` gegen Audit
- [ ] B1: Provisionierung beim Invite-Accept nachweislich funktionsfähig
- [ ] Audit ist weiterhin append-only, `SELECT` weiterhin nur für `mapping_svc`
- [ ] Der Audit-Insert liegt weiterhin synchron in der Domänentransaktion
- [ ] Kontolöschung: Reihenfolge zu Paket 02 Etappe 3 beachtet
- [ ] Retention löscht keine `PROPOSED`-Kategorie ohne ausdrückliche Bestätigung
- [ ] Not-Implemented-Guards sind als bewusst dokumentiert, nicht entfernt
- [ ] Kapitel 3.4, 4.8, 4.10 und 7.3 der Dokumentation aktualisiert

## Expected output

- Gewählte Variante für B1 mit Begründung
- Geänderte Dateien je Etappe
- Ob Migrationen nötig waren und in welchem Domänenverzeichnis
- Entscheidungen zu `subject_ref`, `MappingRuntime`, `decryptUserId`
- Neue Tests je Etappe
- Welche Änderungen einen Volume-Reset erfordern
