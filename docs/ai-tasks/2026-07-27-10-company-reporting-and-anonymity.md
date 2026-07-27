# Paket 10: Company-Reporting und Anonymisierung

**Priorität:** 3 · **Bereich:** Backend + Frontend · **Etappen:** 5
**Befunde:** C3, C4, H5, H6, J21, J22, J23

```ai-run
complexity:        mittel
implement_tier:    standard
implement_effort:  medium
review_tier:       standard
review_effort:     medium
blocked_by:        -
depends_on:        -
```

## Goal

Den Reporting-Pending-Zustand ehrlich abbilden und die Anonymisierungssemantik vereinheitlichen.

## Context

`CompanyController::dashboard` und `ReportController::index` führen **alle** Scope- und
Autorisierungsprüfungen aus, liefern aber keine Kennzahlen, sondern `ReportingPendingResource`:

```json
{"status":"reporting_pending","data":null,"isAboveThreshold":null,"responseCount":null}
```

Die Begründung steht im Code (ADR-003 D7): Eine Live-Aggregation aus Rohdaten war nie
ADR-001-§2.5-konform; die Reporting-Domäne existiert noch nicht. `elyo_reporting` ist angelegt und
leer, nur `elyo_migrator` hat `CONNECT`.

Das ist eine **bewusste, gut umgesetzte Entscheidung** — `ReportingPendingResource` ist `final` und
erzwingt im Konstruktor, dass nur die zwei Legacy-Felder und nur mit `null` übergeben werden.

Die offenen Punkte liegen drumherum:

- **`CompanyReportsComponent` behandelt den Zustand nicht.** Es liest `res.data ?? []` und zeigt
  eine leere Liste **ohne Hinweis** — anders als `CompanyDashboardComponent`, das
  `status === 'reporting_pending'` auswertet und „Berichtsdaten werden vorbereitet" anzeigt.
- **Das Dashboard trägt einen toten Anzeigepfad** für Live-Kennzahlen (`avgScore`,
  `respondentCount`, `eligibleEmployeeCount`, `participationRate`), die das Backend nicht liefert.
- **OpenAPI beschreibt weiterhin Kennzahlen** (C3, C4).
- **`teamBreakdown` ist dauerhaft `null`** — im Service **und** fest in der Resource. Reiner
  Vertragsplatzhalter.
- **Vier Schwellen-Semantiken nebeneinander** (J23): Plattform-Minimum 10, Kategorie-Minimum 5,
  firmenspezifischer Wert (nur nach oben wirksam), DB-Default 5 (wirkungslos).

## Umsetzung in Etappen

### Etappe 1 — Pending-Zustand im Berichte-Frontend behandeln (J21)

- `CompanyReportsComponent` auf das Muster aus `CompanyDashboardComponent` bringen:
  `isReportingPending()` prüfen und einen erklärenden Text zeigen statt einer leeren Liste.
- **Achtung:** `CompanyDashboardComponent::trendPoints()` akzeptiert bereits **beide** Formen
  (direktes Array oder `{data: […]}`) — dieses Muster übernehmen, damit die spätere
  Reporting-Domäne beide Varianten liefern kann.
- **Abnahme:** Der Nutzer sieht „Berichtsdaten werden vorbereitet", nicht „keine Daten"; Spec dazu.

### Etappe 2 — Vertrag angleichen (C3, C4)

- `docs/api/openapi.yaml` für `GET /company/dashboard` und `GET /company/reports` auf den
  Pending-Zustand umstellen.
- Das Schema so gestalten, dass die spätere Live-Antwort ohne erneute Vertragsänderung passt —
  etwa als `oneOf` aus Pending-Block und Kennzahlenblock.
- **Achtung:** Falls Paket 08, Etappe 7 (Contract-Test) bereits läuft, hier den entsprechenden
  Allowlist-Eintrag entfernen.
- **Abnahme:** Vertrag beschreibt die tatsächliche Antwort; Allowlist-Eintrag entfernt.

### Etappe 3 — Toten Anzeigepfad entscheiden (J22)

- `CompanyDashboardComponent` enthält vollständige Logik für Kennzahlen, die nie ankommen:
  `scoreLabel()`, `participationLabel()`, Teile von `participantLabel()` und `responseCountLabel()`,
  `trendBars()`.
- Entscheiden: als Vorbereitung auf die Reporting-Domäne behalten (dann **kommentieren**, warum) —
  oder entfernen und bei Bedarf neu bauen.
- **Abnahme:** Entscheidung getroffen und im Code sichtbar.

### Etappe 4 — `teamBreakdown` klären (H6)

- Das Feld ist `null` im Service **und** hartcodiert `null` in
  `MeasureParticipationSummaryResource`.
- **Achtung:** Es gibt bereits eine dokumentierte Entscheidung dazu:
  `docs/ai-tasks/2026-06-02-13-clarify-team-breakdown-contract.md` hält fest, dass `teamBreakdown`
  als nullbares Zukunftsfeld **bewusst** bestehen bleibt und niemals Team-Zahlen oder
  Einzeldaten enthalten darf, bis ein eigenes datenschutzgeprüftes Feature existiert.
- **Diese Entscheidung ist zu respektieren.** Hier nur prüfen, ob die Dokumentation in Kapitel 6.3
  und der OpenAPI-Vertrag sie korrekt abbilden.
- **Abnahme:** Vertrag und Dokumentation stimmen mit der bestehenden Entscheidung überein; keine
  Codeänderung.

### Etappe 5 — Schwellen-Semantik vereinheitlichen (J23) und Reporting-Roadmap (H5)

- **J23:** `AnonymityThreshold::resolve()` hebt jeden firmenspezifischen Wert auf mindestens 10 an
  (`max(10, $configured ?? 10)`). Der DB-Default von `companies.anonymity_threshold` ist damit
  **wirkungslos**, und `AdminCompanyController` erlaubt Werte ab 1, die nie greifen.
  → DB-Default auf 10 anheben (neue Migration) und die Validierung auf `min:10` setzen, damit
  Konfiguration und Wirkung übereinstimmen.
  **Die Anhebung auf das Plattform-Minimum bleibt** — sie ist die eigentliche Schutzwirkung.
- **H5:** Die Reporting-Domäne planen, nicht bauen. Festhalten: welche Aggregate, welcher Weg
  (`resolveReportingCohort` liegt als Not-Implemented-Guard vor, ADR-003 D5), welche Runtime
  (`reporting-worker` ist ein leerer Platzhalter), welche Suppressionsregeln.
- **Abnahme:** Konfigurierbarer Wert und tatsächliche Wirkung stimmen überein; Reporting-Konzept
  als Dokument.

## Out of Scope

- Bau der Reporting-Domäne
- Änderung der Suppressionsregeln in `SurveyResultsAggregationService` (Paket 11)
- `resolveReportingCohort` aktivieren (bewusster Not-Implemented-Guard, ADR-003 D5)

## Hard constraints

- **Die Company-Runtime erhält keinen Health-Lesepfad** — das ist der Kern von ADR-001 §2.5
- `ReportingPendingResource` bleibt `final` und erzwingt weiterhin nur `null`-Legacy-Felder
- Das Plattform-Minimum von 10 wird **nicht** absenkbar
- `tests/Privacy/CompanyWellbeingPrivacyTest` und `CompanyAdminRoutePrivacyTest` müssen grün bleiben
- Kein `migrate:fresh`

## Review-Checkliste

- [ ] Berichte-Seite zeigt den Pending-Zustand statt einer leeren Liste
- [ ] OpenAPI beschreibt die tatsächliche Antwort, erweiterbar für die Live-Variante
- [ ] Entscheidung zum toten Anzeigepfad ist im Code sichtbar
- [ ] Die bestehende `teamBreakdown`-Entscheidung wurde respektiert, nicht überschrieben
- [ ] Konfigurierbare Schwelle und tatsächliche Wirkung stimmen überein
- [ ] Plattform-Minimum 10 ist weiterhin nicht unterschreitbar
- [ ] Reporting-Konzept liegt als Dokument vor
- [ ] Privacy-Suite grün
- [ ] Kapitel 4.5, 6.3 und 10 der Dokumentation aktualisiert

## Expected output

- Geänderte Dateien je Etappe
- Entscheidung zum toten Anzeigepfad
- Ob eine Migration für den Schwellen-Default nötig war
- Reporting-Konzept
- Neue Tests und Specs
