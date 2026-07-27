# Paket 13: Maßnahmen, QR-Check-in und Punkte

**Priorität:** 3 · **Bereich:** Backend + Frontend · **Etappen:** 6
**Befunde:** F6, G16, G21, I2, I7, J14, J17, J18, J24

```ai-run
complexity:        mittel
implement_tier:    standard
implement_effort:  medium
review_tier:       standard
review_effort:     medium
blocked_by:        U13
depends_on:        12
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
| 6 | Streak-Semantik: was einen Streak bricht (J24) | **Björn** — Etappe blockiert |


## Goal

Die Teilnahme- und Gamification-Logik robust und nachvollziehbar machen.

## Context

Der QR-Check-in ist sicherheitstechnisch sorgfältig gebaut: Ein fremdes Token wird **exakt wie ein
unbekanntes** beantwortet (404 statt 403), damit Existenz nicht abfragbar ist; die Scope-Prüfung
läuft doppelt als „Safety net" gegen Races; `markUsed` liegt **innerhalb** der Teilnahmetransaktion;
ein partieller Unique-Index garantiert genau ein aktives Token je Maßnahme.

Die offenen Punkte:

- **`last_used_at` wird gesetzt, aber nie geprüft** (G21). Ein Token ist beliebig oft einlösbar;
  die Einmaligkeit **pro Person** ergibt sich allein aus dem Unique-Index
  `(measure_id, user_id)`. Für einen QR-Code am Veranstaltungsort ist das korrekt — aber die
  Spalte suggeriert etwas anderes.
- **Konflikterkennung über den Indexnamen** (I7):
  `str_contains($msg, 'measure_participations_measure_id_user_id_unique')`. Eine Umbenennung des
  Index machte den Konflikt zu einem 500.
- **Punktevergabe uneinheitlich gekapselt** (J17): beim Check-in in `try/catch(\Exception)` mit
  `\Log::error()`, beim Dokumentupload **ohne** — dort bräche ein Punktefehler die Antwort ab,
  obwohl die Datei bereits gespeichert ist.
- **`awardPointsOnce()` ohne Sperre und ohne Unique-Index** (I2) — bei Parallelität doppelt möglich.
- **Kein Caching** (J14): `PointSettingsService::resolvePointMap()` fragt bei **jedem** Aufruf die
  Datenbank ab, auch mehrfach je Request. Redis läuft, wird aber nicht genutzt.
- **Anamnese-Punkte nur bei Ersterstellung** (J18): `created === true && completion_pct >= 80`.
  Ein nachträgliches Erreichen der Schwelle vergibt **nie** Punkte.
- **`user_points.level` wird nie geschrieben oder gelesen** (G16) — bleibt dauerhaft `'STARTER'`.
- **Streak zählt nur Werktage** (J24), Feiertage sind nicht berücksichtigt; `period_key` läuft in
  Serverzeitzone.
- **Backend-Regeln im Frontend nachgebaut** (F6): `CompanyMeasuresComponent` bildet
  Datumsbereichsprüfung, Dauerableitung und Editierbarkeitsregeln nach.

## Umsetzung in Etappen

### Etappe 1 — Konflikterkennung robust machen (I7)

- `MeasureParticipationService::isDuplicateParticipation()` prüft SQLSTATE **und** den Indexnamen
  im Fehlertext. Auf `UniqueConstraintViolationException` umstellen — Laravel bietet sie bereits,
  und `SystemMeasureTemplateExerciseController` nutzt sie schon.
- **Achtung:** Dort wird ebenfalls per `str_contains` auf `template_exercise_unique` geprüft, um
  **zwei** Unique-Constraints derselben Tabelle zu unterscheiden. Das ist ein anderer Fall — dort
  ist die Namensprüfung nötig. Hier gibt es nur einen Constraint.
- **Abnahme:** Der Konflikt wird ohne Namensabhängigkeit erkannt; Test mit parallelen Anfragen.

### Etappe 2 — Punktevergabe einheitlich kapseln (J17)

- Ein Muster für alle Vergabestellen festlegen. Die bestehende Asymmetrie ist begründet
  (der Gesundheitsdatensatz ist wichtiger als die Gamification), aber nur an einer Stelle umgesetzt.
- Naheliegend: Vergabe grundsätzlich kapseln und Fehler protokollieren — die fachliche Operation
  darf nie an der Punktevergabe scheitern.
- **Achtung:** `EmployeeController::checkin` nutzt `\Log::error()` mit führendem Backslash statt
  eines Imports — beim Aufräumen mitziehen.
- Betroffene Stellen: `checkin`, `uploadDocument`, `updateProfile`,
  `MeasureParticipationService::createParticipation`.
- **Abnahme:** Ein Muster; kein fachlicher Vorgang scheitert mehr an der Punktevergabe.

### Etappe 3 — `awardPointsOnce` absichern (I2)

- Heute nur eine Vorprüfung auf eine bestehende Transaktion mit demselben `reason`, ohne Sperre.
- Optionen: Unique-Index auf `(user_id, reason)` für die einmaligen Gründe — **aber Vorsicht**,
  `point_transactions` enthält auch mehrfach vergebene Gründe (`daily_checkin`,
  `measure_participation`). Ein voller Unique-Index wäre falsch.
  → Partieller Unique-Index auf die einmaligen Gründe, **nur per `DB::statement()`**
  (Blueprints `unique()->whereNull()`-Fluent ist ein stiller No-op, siehe Migrationskommentar bei
  `measure_checkin_tokens`).
  → Alternativ: `lockForUpdate` oder ein `insertOrIgnore`-Muster.
- **Achtung:** Es gibt heute **keinen Index** auf `(user_id, reason)`, obwohl `awardPointsOnce`
  genau darauf prüft — auch ein Performanzthema.
- **Abnahme:** Doppelvergabe unter Parallelität ist ausgeschlossen; Test.

### Etappe 4 — Punktekonfiguration cachen (J14)

- `PointSettingsService::resolvePointMap()` ist statisch und fragt bei jedem Aufruf ab.
  `awardPoints()` ruft sie **pro Vergabe** erneut.
- Cache einführen. Redis läuft bereits im Stack, wird aber nirgends genutzt (`CACHE_STORE=database`).
- **Achtung:** Bei einer Umstellung auf Redis prüfen, dass `phpunit.xml` weiterhin `CACHE_STORE=array`
  erzwingt und die Tests nicht über einen geteilten Cache interferieren.
- **Achtung:** `AdminPointsController::update` muss den Cache invalidieren.
- **Abnahme:** Messbar weniger Queries; Konfigurationsänderung wirkt sofort.

### Etappe 5 — Anamnese-Punkte und `level` (J18, G16)

- **J18:** Die Bedingung `created && completion_pct >= 80` vergibt nie Punkte, wenn die Schwelle
  erst später erreicht wird. Auf „einmalig, sobald die Schwelle erreicht ist" umstellen —
  `awardPointsOnce()` ist dafür gedacht und deckt die Einmaligkeit ab.
- **G16:** `user_points.level` wird nie geschrieben oder gelesen; der Default `'STARTER'` bleibt
  dauerhaft. Entweder eine Levellogik bauen (dann fachlich definieren: welche Stufen, welche
  Schwellen) oder die Spalte entfernen.
- **Achtung bei G16:** `partners.minimum_level` hat den Default `'STARTER'` — offenbar sollte es
  einmal eine Kopplung geben (Partner sichtbar ab Level X). Das ist offene Frage U6 (Paket 06).
  **Beide zusammen entscheiden.**
- **Abnahme:** Anamnese-Punkte werden auch nachträglich vergeben; `level` hat eine Entscheidung.

### Etappe 6 — Streak-Semantik und Frontend-Duplikate (J24, F6, G21)

- **J24 — blockiert durch offene Frage U13** (Paket 16): `period_key` ist
  `Carbon::now()->toDateString()` in **Serverzeitzone**; der „Tag" ist für alle Nutzer identisch
  definiert. Die Streak-Berechnung zählt nur Werktage (`isWeekday()`), Feiertage fehlen.
  Entscheidung dokumentieren und im Code kommentieren; bei Bedarf Nutzerzeitzone einführen.
  **Achtung:** Eine Zeitzonenänderung betrifft den Unique-Index
  `(health_subject_id, period_key)` — bestehende Einträge blieben gültig, aber die Tagesgrenze
  verschöbe sich.
- **F6:** `CompanyMeasuresComponent` bildet drei Backend-Regeln nach: `dateRangeInvalid()`
  (spiegelt `validateEffectiveDateRange`), `durationPreviewMinutes()` (spiegelt
  `deriveScheduledDuration`), `isEditableMeasure()` (spiegelt die Statusregeln).
  Das verbessert die Nutzerführung, verdoppelt aber die Logik. Entscheiden: beibehalten und
  **kommentiert an die Backend-Regel koppeln**, oder die Vorschau serverseitig berechnen lassen.
- **G21:** `measure_checkin_tokens.last_used_at` wird gesetzt, aber nie geprüft. Entweder für eine
  Einmal-Einlösung nutzen (fachlich vermutlich **nicht** gewünscht — ein QR-Code am
  Veranstaltungsort wird von vielen gescannt) oder als reines Diagnosefeld kommentieren.
- **Abnahme:** Zeitzonenentscheidung dokumentiert; Frontend-Duplikate begründet oder aufgelöst;
  `last_used_at` hat einen dokumentierten Zweck.

## Out of Scope

- Manager-Scoping bei Maßnahmen (Paket 12) — konsumiert die dortige Vereinheitlichung
- Der 400-Statuscode bei `invalid_transition` (Paket 08, Etappe 4)
- Nutzer-Maßnahmenzuweisung `UserSystemMeasure` (Paket 14)

## Hard constraints

- **Der QR-Check-in beantwortet fremde und unbekannte Tokens weiterhin identisch mit 404** —
  Existenz darf nicht abfragbar sein
- Der partielle Unique-Index `measure_checkin_tokens_one_active_per_measure` bleibt unverändert
- `markUsed` bleibt **innerhalb** der Teilnahmetransaktion
- Partielle Indizes nur per `DB::statement()`
- Kein `migrate:fresh`; Schemaänderungen nur als neue Migration
- `tests/Feature/MeasureParticipationPersistenceTest.php` (11 Tests) muss grün bleiben

## Review-Checkliste

- [ ] Konflikterkennung ohne Abhängigkeit vom Indexnamen
- [ ] Ein Kapselungsmuster für alle Punktevergaben
- [ ] Doppelvergabe bei `awardPointsOnce` unter Parallelität ausgeschlossen
- [ ] Falls ein partieller Index angelegt wurde: per `DB::statement()`
- [ ] Punktekonfiguration wird gecacht und bei Änderung invalidiert
- [ ] Anamnese-Punkte werden auch nachträglich vergeben
- [ ] `level` und `minimum_level` wurden gemeinsam entschieden
- [ ] Zeitzonenentscheidung ist im Code kommentiert
- [ ] Frontend-Duplikate sind begründet oder aufgelöst
- [ ] QR-Check-in-Semantik unverändert
- [ ] Kapitel 4.4, 4.5 und 13.4 der Dokumentation aktualisiert

## Expected output

- Geänderte Dateien je Etappe
- Entscheidungen zu U13, `level`, `last_used_at`, Frontend-Duplikaten
- Ob eine Migration nötig war
- Messbare Query-Reduktion aus Etappe 4
- Neue Tests und Specs
