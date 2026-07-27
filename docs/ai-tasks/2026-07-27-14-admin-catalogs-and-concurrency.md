# Paket 14: Admin-Kataloge und Nebenläufigkeit

**Priorität:** 3 · **Bereich:** Backend + Frontend · **Etappen:** 6
**Befunde:** C7, C8, C9, F3, F4, G13, G18, H7, H8, H12, I3, I5, J29

```ai-run
complexity:        mittel
implement_tier:    standard
implement_effort:  medium
review_tier:       standard
review_effort:     medium
blocked_by:        -
depends_on:        12
```

## Goal

Den Admin-Bereich vertragstreu und nebenläufigkeitsfest machen und die ungenutzte
Zuweisungsdomäne entscheiden.

## Context

Der Vorlagenkatalog ist der technisch anspruchsvollste Teil des Admin-Bereichs und bereits gut
gelöst: `lockForUpdate()` auf der Elternzeile, zweiphasige Verschiebung beim Umsortieren gegen den
Unique-Index `(template_id, position)`, Abbildung von `UniqueConstraintViolationException` auf
dieselben 422-Meldungen wie die Vorabprüfungen.

Die offenen Punkte:

- **`generateUniqueSlug()` ist race-anfällig** (I3) und **doppelt implementiert** (F4) — in
  `SystemExerciseController` und `SystemMeasureTemplateController`, identisch bis auf den
  Fallback-String. Zwischen Prüfung und Insert kann ein zweiter Request denselben Slug belegen;
  die Unique-Constraint erzeugt dann einen ungefangenen 500.
- **`PUT /admin/points-config` ohne Transaktion** (I5): Ein Fehler mitten in der Schleife
  hinterlässt eine teilweise aktualisierte Konfiguration.
- **Fünf Vorlagenfelder sind nicht setzbar** (H7, C9): `goal_summary`, `recommended_frequency`,
  `default_points`, `streak_enabled`, `requires_feedback` stehen in `$fillable` und in der Tabelle,
  aber weder in den Requests noch in `COLUMN_MAP` noch in der Resource. OpenAPI beschreibt
  `defaultPoints` und `recommendedFrequency` als akzeptiert.
- **Kein Schreibendpunkt für Übungs-Tags** (H8) — sie entstehen ausschließlich über
  `SystemExerciseSeeder`.
- **Rohe Modellausgaben** (C7, C8): `GET /admin/companies` liefert Eloquent-Modelle inklusive
  aller Spalten, `GET /admin/partners` das rohe Paginator-Objekt.
- **Firmenverwaltung im Frontend unvollständig** (H12): `GET /{company}` und `PUT /{company}`
  haben keinen Aufrufer.
- **Einladungserzeugung dupliziert** (F3): `AdminCompanyController::inviteCompanyAdmin` bildet
  `CompanyInvitationService::createInvitation` nach, ohne dessen Prüfungen.
- **Zuweisungsdomäne ohne Anbindung** (G13): `user_system_measures`,
  `user_system_measure_exercises`, `user_system_measure_exercise_completions` haben Modelle,
  Migrationen und Factories — aber **keinen Controller, keinen Service, keine Route**.

## Umsetzung in Etappen

### Etappe 1 — Slug-Erzeugung entduplizieren und absichern (F4, I3)

- Gemeinsame Implementierung extrahieren (Trait oder kleiner Service).
- Race absichern: `UniqueConstraintViolationException` fangen und erneut versuchen, oder den Slug
  in derselben Transaktion mit `lockForUpdate` ermitteln.
- **Vorbild im Repository:** `SystemMeasureTemplateExerciseController::store()` fängt die
  Exception bereits ab und bildet sie auf dieselbe 422-Meldung ab wie die Vorabprüfung — mit dem
  Kommentar „so concurrent races never leak as internal errors". Dasselbe Muster hier anwenden.
- **Abnahme:** Eine Implementierung; paralleler Anlegevorgang erzeugt keinen 500.

### Etappe 2 — Punktekonfiguration transaktional (I5)

- `AdminPointsController::update` in eine `DB::transaction` fassen.
- **Achtung:** `UpdatePointSettingsRequest` verlangt **alle sechs** Aktionen als Pflichtfelder —
  ein Teil-Update ist ohnehin nicht möglich. Die Transaktion schützt gegen den Abbruch mittendrin.
- Falls Paket 13, Etappe 4 einen Cache einführt: hier invalidieren.
- **Abnahme:** Kein Teilzustand mehr möglich.

### Etappe 3 — Nicht setzbare Vorlagenfelder klären (H7, C9)

- **Entscheiden:** Felder über die API zugänglich machen oder aus Modell, Tabelle und OpenAPI
  entfernen.
- Bei „zugänglich machen": Requests, `COLUMN_MAP` und `SystemMeasureTemplateResource` ergänzen.
  Die Resource gibt sie heute konsequenterweise auch nicht aus.
- **Achtung:** `SystemMeasureTemplate::FREQUENCY_DAILY|WEEKLY|ON_DEMAND` (G18) sind Konstanten für
  `recommended_frequency` — sie werden von **nichts** verwendet. Gemeinsam mit H7 entscheiden.
- **Achtung:** `default_points`, `streak_enabled` und `requires_feedback` gehören fachlich zur
  Zuweisungsdomäne (Etappe 6). Die Entscheidung dort beeinflusst diese hier.
- **Abnahme:** Vorlagenfelder sind entweder nutzbar oder entfernt; OpenAPI stimmt überein.

### Etappe 4 — Resources einführen (C7, C8)

- `GET /admin/companies`: `CompanyResource` statt rohem Modell. Heute werden alle Spalten
  ausgegeben, inklusive `created_by_elyo_admin_id`. Zudem **keine Paginierung**.
- `GET /admin/partners`: Rohes Paginator-Objekt (`current_page`, `data`, `first_page_url`, `links`)
  — abweichend von allen anderen Admin-Listen, die `AnonymousResourceCollection` nutzen.
  Abstimmung mit Paket 06, Etappe 4, das denselben Endpunkt betrifft.
- **Abnahme:** Einheitliche Antwortstruktur über alle Admin-Listen; OpenAPI mitgezogen.

### Etappe 5 — Firmenverwaltung und Einladungserzeugung (H12, F3, J29)

- **H12:** `GET /admin/companies/{company}` und `PUT /admin/companies/{company}` im Frontend
  anbinden — Detailansicht und Bearbeitung. Heute gibt es nur Liste und Anlage.
  **Achtung:** Das löst zugleich die Sackgasse aus Paket 07, Etappe 2 (Firma ohne Administrator
  kann nicht nachträglich eingeladen werden).
- **F3:** `AdminCompanyController::inviteCompanyAdmin` bildet die Tokenerzeugung nach, ohne die
  Team-Ebenen- und Managerprüfungen des Services (hier auch nicht nötig, weil die Rolle fest
  `COMPANY_ADMIN` ist). Auf `CompanyInvitationService` umstellen oder die Abweichung begründen.
- **J29:** `SystemMeasureTemplateExerciseController::destroy` ruft `ensureBelongsToTemplate` mit
  dem **ungesperrten** Template, anders als `store`/`update`. Funktional gleichwertig (nur
  ID-Vergleich), aber inkonsistent. Angleichen.
- **Abnahme:** Firmen sind über die Oberfläche bearbeitbar; ein Erzeugungsweg für Einladungen.

### Etappe 6 — Zuweisungsdomäne und Übungs-Tags entscheiden (G13, H8)

- **G13 — die größte offene Entscheidung dieses Pakets.** Drei vollständig modellierte Tabellen
  mit Factories, ohne jeden Zugriffspfad:
  `user_system_measures`, `user_system_measure_exercises`, `user_system_measure_exercise_completions`.
  **Bemerkenswert:** Letztere enthält Schmerz- und Stressbewertungen (`pain_before_rating`,
  `stress_after_rating`, `feedback_text`) — also **gesundheitsnahe Daten in der Identity-Domäne
  auf `user_id`**. Der Migrationskommentar begründet das mit dem fehlenden Reporting-Endpunkt und
  verlangt für künftige Aggregation Schwellen und Suppression.
  → Entscheiden: anbinden (dann **zwingend die Domänenzuordnung prüfen** — die Daten gehören
  fachlich in die Health-Domäne, siehe Paket 16, J12) oder entfernen.
- **H8:** Kein Schreibendpunkt für `system_exercise_tags`. Entscheiden: Endpunkt bauen oder als
  bewusst seeder-gepflegten Katalog dokumentieren. **Achtung:** `SystemExerciseTagController::index`
  hat als einziger Admin-Listenendpunkt **keine Paginierung** — bei wachsendem Katalog relevant.
- **Abnahme:** Für beide eine Entscheidung mit Begründung; bei Anbindung der Zuweisungsdomäne ist
  die Domänenfrage geklärt.

## Out of Scope

- Partner-spezifische Anteile von `GET /admin/partners` (Paket 06)
- Autorisierungsabweichung `AdminPartnerActionRequest` (Paket 06, Etappe 5)
- Reporting über Zuweisungsdaten (Paket 10)

## Hard constraints

- Die Nebenläufigkeitsabsicherung des Vorlagenkatalogs (`lockForUpdate`, zweiphasige Verschiebung,
  Vollständigkeitsprüfung beim Reorder) bleibt **unverändert**
- Archivierung statt Löschung bleibt das Muster für Übungen und Vorlagen
- **Falls die Zuweisungsdomäne angebunden wird: Gesundheitsnahe Bewertungen dürfen nicht ohne
  Domänenprüfung in der Identity-Domäne bleiben** (ADR-001)
- Kein `migrate:fresh`; Schemaänderungen nur als neue Migration
- `tests/Feature/AdminSystemExerciseTest.php` (36) und `AdminSystemMeasureTemplateTest.php` (43)
  müssen grün bleiben

## Review-Checkliste

- [ ] Eine Slug-Implementierung, race-sicher
- [ ] Punktekonfiguration transaktional, Cache invalidiert
- [ ] Vorlagenfelder sind nutzbar oder entfernt, OpenAPI stimmt
- [ ] `FREQUENCY_*`-Konstanten wurden mitentschieden
- [ ] Einheitliche Antwortstruktur über alle Admin-Listen
- [ ] Firmen sind über die Oberfläche bearbeitbar
- [ ] Ein Erzeugungsweg für Einladungen
- [ ] Sperrverhalten in `destroy` angeglichen
- [ ] Zuweisungsdomäne: entschieden, bei Anbindung Domänenfrage geklärt
- [ ] Übungs-Tags: entschieden
- [ ] Nebenläufigkeitsabsicherung des Katalogs unverändert
- [ ] Kapitel 4.6, 6.4 und 7.1 der Dokumentation aktualisiert

## Expected output

- Geänderte Dateien je Etappe
- Entscheidungen zu Vorlagenfeldern, Zuweisungsdomäne, Übungs-Tags
- Ob eine Migration nötig war
- Neue Tests und Specs
