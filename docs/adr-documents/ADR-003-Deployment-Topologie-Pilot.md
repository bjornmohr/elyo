# ADR-003: Deployment-Topologie und Umsetzungsentscheidungen für den Pilot (ELYO-91)

| Feld | Wert |
|---|---|
| **Status** | Angenommen (Umsetzungssession) |
| **Datum** | 2026-07-19 |
| **Entscheider** | Tech-Lead (Björn) |
| **Grundlage** | ADR-001 (Zielarchitektur), ELYO-91-Ausführungsplan `docs/ai-tasks/2026-07-19-00-elyo-91-execution-plan.md` (Session-Entscheidungen D1–D10) |
| **Bezug** | Konkretisiert ADR-001 für den Pilotbetrieb; wo eine Entscheidung von ADR-001 abweicht, ist dies unten als **Pilot-Konkretisierung** ausdrücklich markiert. |

---

## 1. Kontext

ADR-001 legt die Zielarchitektur verbindlich fest: vier getrennte Datenbanken plus Audit-DB, fünf Runtimes aus einem Image, eine geschützte Mapping-Domäne mit genau fünf zweckgebundenen Operationen, Reporting ausschließlich über suppressionsgeprüfte Aggregate und keine direkte Runtime-zu-Runtime-Kommunikation.

ADR-001 beschreibt den Zielzustand, nicht den Weg dorthin. Für den Pilotbetrieb müssen Deployment-Topologie, Umfang der ersten Umsetzung und bewusste Vertagungen konkretisiert werden. Diese ADR hält die zehn Umsetzungsentscheidungen der Session vom 2026-07-19 fest, damit jede nachfolgende Aufgabe der Prompt-Serie (`2026-07-19-02` … `-17`) dieselben Randbedingungen erbt. Es werden keine ADR-001-Entscheidungen aufgehoben; einzelne Punkte werden für den Pilot enger gefasst oder zeitlich gestaffelt.

## 2. Entscheidung

Jede Entscheidung D1–D10 ist wortgleich aus dem Ausführungsplan übernommen; darunter stehen Begründung und der konkretisierte bzw. abweichende ADR-001-Abschnitt.

### D1 — Datenbanktrennung

> Domain separation as **separate PostgreSQL databases in the existing container**: `elyo_identity`, `elyo_subject_mapping`, `elyo_health`, `elyo_audit`; own PG role per runtime with minimal grants. (`elyo_reporting` prepared, not populated — no reporting worker yet.)

- **Begründung:** Die logische Trennung nach ADR-001 wird für den Pilot in einem einzigen bestehenden Postgres-Container abgebildet — separate Datenbanken plus je Runtime eine eigene Rolle mit minimalen Grants liefern die geforderte Isolation ohne den Betriebsaufwand mehrerer Cluster.
- **ADR-001-Bezug:** Konkretisiert §2.1 (vier DBs + Audit-DB auf einem Cluster). **Pilot-Konkretisierung:** `elyo_reporting` wird nur angelegt, nicht befüllt (kein Reporting-Worker), da im Pilot noch keine aggregierten Snapshots nach §2.5 erzeugt werden.

### D2 — Runtime-Split

> **Runtime split now**: `api-identity`, `api-employee`, `api-company` as compose services from one image via `ELYO_RUNTIME` profile; **nginx path routing** keeps a single base URL for Angular. No aggregator gateway (would violate ADR-001 §2.4). Reporting worker + privacy runtime prepared only.

- **Begründung:** Drei API-Runtimes aus einem Image über ein `ELYO_RUNTIME`-Startprofil setzen die Prozesstrennung sofort um; nginx-Path-Routing hält für Angular eine einzige Basis-URL, ohne einen aggregierenden Gateway einzuführen (der die Runtime-Grenzen aufweichen würde).
- **ADR-001-Bezug:** Konkretisiert §2.4 (fünf Runtimes aus einem Image, eigene Rollen, keine Runtime-zu-Runtime-Kommunikation). **Pilot-Konkretisierung:** Reporting-Worker und Privacy/Admin-Runtime werden nur vorbereitet, nicht ausgeliefert; die fünf Runtimes aus §2.4 sind damit gestaffelt, nicht gleichzeitig aktiv.

### D3 — Wellbeing in die Health-Domäne

> `wellbeing_entries` moves **fully** into the health domain now (health_subject_id, scale 1–5, `note` removed) **including** the Angular check-in UI adjustment. ELYO-110 is documented as "implemented instead of assessed".

- **Begründung:** Der Neuaufbau des Schemas ist der günstigste Zeitpunkt, `wellbeing_entries` vollständig auf `health_subject_id` zu stellen (Skala 1–5 kanonisch, Freitext-`note` entfernt). Die zugehörige Angular-Check-in-Anpassung gehört zum genehmigten Scope; ELYO-110 wird als „umgesetzt statt bewertet" dokumentiert.
- **ADR-001-Bezug:** Konkretisiert §2.6 (Check-ins liegen in der Health-Domäne am `health_subject_id`, kein Unternehmensbezug).

### D4 — Laborwert-Endpoints

> ELYO-105 includes the **employee lab-marker HTTP endpoints** per ELYO-102 §1 (list, history, POST manual, DELETE own).

- **Begründung:** Die Employee-Laborwert-Endpoints (Liste, Historie, manuelles POST, DELETE eigener Werte) werden zusammen mit dem Laborwertmodell umgesetzt, damit das Datenmodell direkt gegen einen realen Zugriffspfad nach dem ELYO-102-Contract abgesichert ist.
- **ADR-001-Bezug:** Konkretisiert §2.6 (Health-Daten am `health_subject_id`); der Zugriff erfolgt zweckgebunden über die Mapping-Auflösung nach §2.3.

### D5 — Mapping-Operationen

> Mapping operations: **3 now** (`provisionOwnSubject`, `resolveOwnSubject`, `revokeSubjectLink`), `resolveReportingCohort` / `resolveForDataSubjectRequest` as defined interface with not-implemented guard.

- **Begründung:** Von den fünf zweckgebundenen Operationen werden die drei für den Pilot benötigten implementiert; die beiden reporting- bzw. DSAR-bezogenen Operationen bleiben als definierte Schnittstelle mit einem Not-Implemented-Guard bestehen, damit der Contract vollständig, aber nicht funktionslos vorgetäuscht ist.
- **ADR-001-Bezug:** Konkretisiert §2.3 (genau fünf zweckgebundene Operationen). **Pilot-Konkretisierung:** 3-von-5 Operationen sind aktiv; `resolveReportingCohort` und `resolveForDataSubjectRequest` existieren als Interface mit Guard — der Contract bleibt fünfteilig, die Umsetzung ist gestaffelt.

### D6 — Boundary-Durchsetzung

> Static boundary enforcement via **Deptrac** (dev dependency) + runtime grants tests.

- **Begründung:** Statische Namespace-Grenzen werden über Deptrac (Dev-Abhängigkeit) erzwungen, ergänzt um Laufzeittests gegen die realen Rollen-Grants — statische und dynamische Absicherung greifen ineinander.
- **ADR-001-Bezug:** Konkretisiert §2.10 (Boundary-Tests bei jedem Merge in CI).

### D7 — Company-Aggregate

> Company wellbeing aggregates lose their live source; endpoints return an explicit **reporting-pending** state until the reporting domain exists (live aggregation was never ADR-001 §2.5 conform).

- **Begründung:** Die Company-Wellbeing-Aggregate hatten eine Live-Quelle aus den Rohdaten — ein Pfad, der §2.5 nie entsprach. Mit dem Wegfall dieser Quelle liefern die Endpoints einen expliziten Reporting-Pending-Zustand, bis die Reporting-Domäne mit suppressionsgeprüften Snapshots existiert.
- **ADR-001-Bezug:** Konkretisiert §2.5 (Reporting nur über suppressionsgeprüfte Quartals-Aggregate, kein Personenbezug). **Pilot-Konkretisierung:** Statt Live-Aggregation ein Reporting-Pending-Zustand — die Company-Runtime erhält keinen Lesepfad auf Health-Rohdaten.

### D8 — Anamnese, Dokumente, Wearables

> Anamnesis profiles, health documents (DB side), and wearables move to the health domain **now** (prompt 08a) — consistency with the fresh schema rebuild. Storage hardening for documents (own bucket, signed URLs, virus scan — ADR-001 §2.9) is a follow-up ticket. Points and surveys stay on `user_id` (own epic: they break company aggregation features); flagged in the ELYO-110 evaluation doc.

- **Begründung:** Anamnese-Profile, Health-Dokument-Metadaten (DB-Seite) und Wearables ziehen konsistent mit dem Schema-Neuaufbau jetzt in die Health-Domäne auf `health_subject_id`. Die Storage-Härtung der Dokumentdateien (eigener Bucket, signierte URLs, Virenscan) ist ein Folgeticket. Punkte und Surveys bleiben vorerst am `user_id`, weil sie in Company-Aggregationsfeatures verflochten sind und einen eigenen Epic brauchen.
- **ADR-001-Bezug:** Konkretisiert §2.6 (alle Health-/Verhaltensdaten am `health_subject_id`) und §2.9 (Dokument-Storage). **Pilot-Konkretisierung / bewusste Abweichung:** §2.6 nennt Punkte/Streaks/Badges/Survey-Antworten ausdrücklich als Health-Domäne; im Pilot verbleiben Punkte und Surveys vorerst am `user_id`. Diese Abweichung ist im ELYO-110-Bewertungsdokument festgehalten; die Storage-Härtung nach §2.9 ist als Folgeticket vertagt.

### D9 — Testinfrastruktur

> **Postgres-only testing** (revised 2026-07-19): the sqlite lane is removed. All suites (default, `boundary`, `privacy`) run against Postgres test databases (`elyo_*_test`) with the real roles — one engine, production parity, grants testable. Docker is required to run tests (already the documented workflow).

- **Begründung:** Die sqlite-Lane entfällt; alle Suiten (`default`, `boundary`, `privacy`) laufen gegen Postgres-Test-Datenbanken (`elyo_*_test`) mit den echten Rollen. Nur so sind die Grants überhaupt testbar und es gibt Produktionsparität mit einer einzigen Engine. Docker ist Voraussetzung — es ist bereits der dokumentierte Workflow.
- **ADR-001-Bezug:** Konkretisiert §2.10 (Boundary-Tests gegen PostgreSQL mit echten Rollen-Grants).

### D10 — Mapping-Verschlüsselung

> Mapping table field-encrypted with dedicated key (`MAPPING_ENCRYPTION_KEY`), HMAC lookup column; KMS deferred per ADR-001.

- **Begründung:** Die Mapping-Tabelle wird feldverschlüsselt mit einem dedizierten Schlüssel (`MAPPING_ENCRYPTION_KEY`); eine HMAC-Spalte ermöglicht deterministische Lookups ohne Klartext. Für die nach DB-übergreifenden Teilfehlern geforderte Adoption eines verwaisten Health Subjects leitet ausschließlich die Mapping-Domäne dessen opaque ULID deterministisch aus `user_id` und einem dritten, unabhängigen Secret (`MAPPING_SUBJECT_DERIVATION_KEY`) ab. Es wird keine zusätzliche Verknüpfung in der Health-Datenbank gespeichert; ohne dieses Mapping-Domänen-Secret ist die Ableitung nicht möglich, und eine Offenlegung des Lookup-Schlüssels allein reicht nicht zur Berechnung von Health-IDs. Das Secret darf weder `MAPPING_HMAC_KEY` noch `APP_KEY` wiederverwenden. Ein KMS/Secret Manager ist per ADR-001 bewusst vertagt.
- **ADR-001-Bezug:** Konkretisiert §2.9 (Feldverschlüsselung nur für die Mapping-Tabelle mit eigenem Schlüssel); KMS bleibt gemäß §3 (bewusst vertagt) offen.

## 3. Konsequenzen

**Positiv:**

- Die ADR-001-Trennung ist im Pilot in einem einzigen Postgres-Container plus drei API-Runtimes real umgesetzt und über statische (Deptrac) wie dynamische (Grants-Tests) Grenzen abgesichert.
- Angular sieht trotz Runtime-Split über nginx-Path-Routing eine einzige Basis-URL.
- Die Health-Domäne ist nach dem Schema-Neuaufbau konsistent am `health_subject_id` verankert (Wellbeing, Anamnese, Dokumente-Metadaten, Wearables, Laborwerte).

**Negativ / bewusst akzeptiert:**

- Reporting-Domäne, Reporting-Worker und Privacy/Admin-Runtime sind nur vorbereitet; Company-Aggregate liefern bis dahin einen Reporting-Pending-Zustand.
- Zwei der fünf Mapping-Operationen existieren nur als Interface mit Guard.
- Punkte und Surveys bleiben abweichend von §2.6 vorerst am `user_id` (eigener Epic).
- Storage-Härtung für Health-Dokumente (§2.9) ist als Folgeticket vertagt.

## 4. Referenzen

- ADR-001 (`docs/adr-documents/ADR-001-Trennung-Identity-Mapping-Health-Reporting.md`) — Zielarchitektur, insbesondere §2.1, §2.3, §2.4, §2.5, §2.6, §2.9, §2.10.
- ADR-002 (`docs/adr-documents/ADR-002-DSFA-Vorpruefung-Scope-Methodik-Blocker-Steuerung.md`) — Scope, Methodik und Blocker-Steuerung.
- ELYO-91-Ausführungsplan (`docs/ai-tasks/2026-07-19-00-elyo-91-execution-plan.md`) — Session-Entscheidungen D1–D10, Prompt-Serie und Abhängigkeitsgraph.
