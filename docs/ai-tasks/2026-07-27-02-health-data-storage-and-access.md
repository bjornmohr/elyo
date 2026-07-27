# Paket 02: Gesundheitsdaten — Speicher, Zugriff und Verschlüsselung

**Priorität:** 1 · **Bereich:** Backend + Frontend · **Etappen:** 7
**Befunde:** A5, E3, G3, G6, G14, H9, H10, H16, H17, J13, J31

```ai-run
complexity:        hoch
implement_tier:    high
implement_effort:  high
review_tier:       high
review_effort:     high
blocked_by:        U5
depends_on:        05
```

## Goal

Gesundheitsdaten so ablegen und zugänglich machen, dass Zugriff eine Autorisierung erfordert, und
die ruhenden Teile der Domäne auflösen.

## Context

Die Domänentrennung selbst ist sauber umgesetzt und dreifach abgesichert (PostgreSQL-Grants,
`RuntimeProfile`-Connection-Allowlist, Deptrac-Layer). Die offenen Punkte betreffen die
**Peripherie**:

- **Medizinische PDFs liegen auf der öffentlichen Disk** mit unsignierten URLs.
  `HealthDocumentService::storeUploadedDocument()` markiert das selbst als
  „ADR-001 §2.9 storage hardening follow-up … deliberately out of scope for this prompt".
- **Kein Download- und kein Löschpfad** für hochgeladene Dokumente — eine Einbahnstraße.
- **`MappingRevokedException` wird nirgends gefangen** → 500 bei jedem Health-Zugriff eines
  Nutzers mit Tombstone-Mapping.
- **Drei Mapping-Auflösungen pro Dashboard-Aufruf**, jede mit eigenem Audit-Insert.
- **`WearableService` ist ruhend** (kein Aufrufer, keine Route), `processTerraData` verarbeitet
  nur den Typ `daily`.
- **`health_documents` hat keinen Schreiber** — die Abgrenzung zu `user_documents` ist nur im
  Migrationskommentar erklärt.
- **Labormarker haben kein Frontend** — vier vollständig implementierte und getestete Endpunkte
  ohne Konsument.
- **Markerspezifische Plausibilitätsbereiche fehlen** (`TODO ELYO-114`).
- **Die Auswahl der verschlüsselten Anamnesefelder ist unbegründet**: sechs Felder tragen den
  `encrypted`-Cast, `birth_year` und `chronic_patterns` nicht.

## Umsetzung in Etappen

### Etappe 1 — Nicht-öffentliche Disk für Gesundheitsdokumente (A5)

- Eigene Disk konfigurieren (`health-documents`), nicht öffentlich lesbar.
- `HealthDocumentService::storeUploadedDocument()` umstellen.
- `blob_url` neu bewerten: entfernen oder durch eine kurzlebige signierte URL ersetzen. Die Spalte
  bleibt vorerst — eine Schemaänderung wäre eine neue Migration.
- **Zwei bestehende Designentscheidungen bleiben unangetastet:** Das Verzeichnis ist die
  **Subject-ULID**, nicht die `user_id`; `store()` behält Laravels Zufallsnamen, damit der
  clientseitige Dateiname nie Teil eines Pfades wird.
- `AccountDeletionService::deleteHealthData()` und `EnforceRetention::deleteDocuments()` mitziehen.
  Beide werfen bei fehlgeschlagener Dateilöschung bewusst eine `RuntimeException` — beibehalten.
- **Abnahme:** Upload landet auf der neuen Disk; die Datei ist über keine öffentliche URL
  erreichbar; Löschung und Retention funktionieren weiterhin.

### Etappe 2 — Download- und Löschpfad (H17)

- Endpunkte ergänzen: `GET /employee/documents/{document}` und `DELETE /employee/documents/{document}`.
- **Fremder Datensatz wird exakt wie ein fehlender beantwortet** (404) — das Muster ist bereits in
  `LabMarkerController` etabliert und im Klassenkommentar begründet: Eigentümerschaft darf nicht
  abfragbar sein.
- Auflösung über `HealthDocumentService` mit `PurposeCode::HEALTH_SELF_READ` bzw. `HEALTH_SELF_WRITE`.
- Datei **und** Metadatenzeile löschen, in dieser Reihenfolge (Vorbild: `EnforceRetention`).
- `docs/api/openapi.yaml` ergänzen.
- **Abnahme:** Eigener Download gelingt, fremder liefert 404; Löschung entfernt Datei und Zeile.

### Etappe 3 — `MappingRevokedException` behandeln (E3)

- Zentral im Exception-Renderer behandeln (Grundlage: Paket 08, Etappe 1).
- Vorschlag: **403** mit `{"error":{"code":"SUBJECT_MAPPING_REVOKED",…}}`.
- `InvalidPurposeCodeException` und `OperationNotAvailableException` ebenfalls zuordnen
  (Letztere sinnvoll als **501**).
- **Die Meldung darf keine Rückschlüsse auf Gesundheitsdaten oder das Subject zulassen** —
  `tests/Privacy/HealthLeakAssertionsTest` prüft Antworten gegen einen Musterkatalog.
- **Abnahme:** HTTP-Aufruf mit widerrufenem Mapping liefert die definierte Antwort, keinen 500;
  Privacy-Suite grün.

### Etappe 4 — Mehrfache Mapping-Auflösung reduzieren (J13)

- `GET /employee/dashboard` löst heute **dreimal** auf: über `recentEntries()`,
  `PointsService::calculateStreak()` → `checkinPeriodKeys()` und `hasDailyCheckin()`.
  `GET /employee/profile` löst **zweimal** auf.
- Auflösung je Request einmalig durchführen. Naheliegend: Memoisierung im Trait
  `ResolvesOwnSubject` pro Instanz oder ein request-gebundener Cache.
- **Das Audit darf dabei nicht verloren gehen.** Wenn drei fachliche Zugriffe stattfinden, ist zu
  entscheiden und zu begründen, ob drei Audit-Einträge korrekt sind oder einer genügt. ADR-001
  verlangt Nachvollziehbarkeit je Zugriff — im Zweifel bleibt es bei drei Einträgen und nur der
  Datenbank-Roundtrip zur Mapping-Domäne wird gespart.
- **Abnahme:** Messbar weniger Queries gegen `subject_mappings`; die Audit-Semantik ist
  dokumentiert und getestet.

### Etappe 5 — Ruhende Teile auflösen (G3, G6, G14, H16)

- **`WearableService`** (G3, H16): entscheiden — anbinden (Webhook-Route, Signaturprüfung,
  Idempotenz, alle vier Datentypen) oder entfernen. Ohne Terra-Konfiguration in `.env.example`
  und ohne Ticketverweis spricht viel für Entfernen; dann auch `wearable_connections` und
  `wearable_syncs` in einer neuen Migration abräumen.
- **`health_documents`** (G14): Abgrenzung zu `user_documents` auflösen — entweder anbinden oder
  entfernen. Die Tabelle hat heute keinen Schreiber.
- **`LabMarkerService::historyForMarker()`** (G6): kein Produktivaufrufer, nur Tests. Entfernen
  oder als bewusste Service-API begründen.
- **Abnahme:** Je Element eine Entscheidung mit Begründung; bei Entfernung greift die
  Boundary-Suite weiterhin (`HealthSchemaBoundaryTest` prüft alle Health-Tabellen).

### Etappe 6 — Verschlüsselungsauswahl klären (J31)

- **Blockiert durch offene Frage U5** (Paket 16).
- Heute verschlüsselt (`encrypted`-Cast, Schlüssel `APP_KEY`): `biological_sex`, `activity_level`,
  `sleep_quality`, `stress_tendency`, `smoking_status`, `nutrition_type`.
- Nicht verschlüsselt: `birth_year`, `chronic_patterns`, `has_medication`, `completion_pct`.
- Kriterium festlegen und im Modellkommentar dokumentieren; Abweichungen angleichen.
- **Achtung:** Verschlüsselte Felder sind nicht durchsuchbar, filterbar oder aggregierbar, und
  eine Rotation von `APP_KEY` macht sie unlesbar. Eine Ausweitung hat Betriebsfolgen.
- **Abnahme:** Kriterium dokumentiert; falls Felder ergänzt wurden, Migrationspfad für Bestandsdaten
  benannt (pre-production: vermutlich keiner nötig).

### Etappe 7 — Labormarker: Plausibilität und Frontend (H9, H10)

- **Plausibilitätsbereiche (H9):** `LabMarkerService::createReading()` prüft heute nur generisch
  (`gte:0`, `decimal:0,4`, `max`, `before_or_equal:today`). Markerspezifische Bereiche aus
  `lab_markers.low`/`high` ableiten oder eigene Plausibilitätsspalten ergänzen.
  **Achtung:** `gte:0` statt `gt:0` ist bewusst — Marker wie CRP melden legitim 0 unterhalb der
  Nachweisgrenze. Die Zukunftsdatumssperre ist ebenfalls bewusst, weil `latestPerMarker` nach
  `measured_at` rankt.
- **Frontend (H10):** Vier Endpunkte ohne Konsument. Seite unter `/employee/lab-markers` mit
  Liste, Verlauf, manueller Eingabe und Löschung.
  **Achtung:** `LabMarkerReadingResource` liefert `group` (aus der Spalte `marker_group`) und ein
  **berechnetes** `status`-Feld — nicht selbst nachrechnen.
- **Abnahme:** Plausibilitätsregel getestet; Frontend-Seite mit Spec; keine Subject-ID in der UI.

## Out of Scope

- Änderung der Domänentrennung selbst
- Virenscan für Uploads — als Folgetask benennen, falls nicht umsetzbar
- Reporting über Gesundheitsdaten (Paket 10)

## Hard constraints

- **Kein Pfad, keine Antwort und kein Log darf `user_id`, E-Mail oder Personennamen enthalten**
- **Der clientseitige Dateiname wird nie Teil eines Speicherpfades**
- Ein widerrufenes Mapping wird **niemals** re-provisioniert (Tombstone-Semantik, ADR-001)
- `AnamnesisResource` hat bewusst **kein `id`-Feld** — das bleibt so
- Health-Zugriffe laufen ausschließlich über `App\Services\Health\*` mit Zweckcode
- Kein `migrate:fresh`; Schemaänderungen nur als neue Migration im Health-Verzeichnis
- `tests/Boundary/HealthSchemaBoundaryTest` und `tests/Privacy/*` müssen grün bleiben

## Review-Checkliste

- [ ] Jede Etappe ist ein eigener Commit mit Befund-ID
- [ ] Keine Datei ist mehr über eine unsignierte öffentliche URL erreichbar
- [ ] Download liefert bei fremdem Dokument 404, nicht 403
- [ ] `MappingRevokedException` erzeugt keinen 500 mehr
- [ ] Die Audit-Semantik nach Etappe 4 ist dokumentiert und begründet
- [ ] Für jedes ruhende Element gibt es eine Entscheidung, keine stillschweigende Beibehaltung
- [ ] Verschlüsselungskriterium ist im Modellkommentar dokumentiert
- [ ] Kapitel 4.9, 7.2 und 14 der Dokumentation aktualisiert

## Expected output

- Geänderte Dateien je Etappe
- Entscheidungen zu Wearables, `health_documents`, `historyForMarker` mit Begründung
- Gewähltes Verschlüsselungskriterium
- Migrationen mit Domänenverzeichnis
- Neue Tests je Etappe
- Ob ein Virenscan-Folgetask nötig ist
