# ELYO-102 – Contract-Entscheidungen Produktions-APIs (API-first)

> **Source of Truth** für die Contract-Entscheidungen zu lab-markers, erweitertem Dashboard-Payload und Check-in-Persistenz. Direkter Input für ELYO-114 (finaler OpenAPI-Contract) — dort per Referenz-ID zitieren. Der Demo-OpenAPI-Entwurf (`docs/api/openapi.yaml` auf `demo/employee-lab-values-dashboard`) ist Referenz-Input, **nicht** Grundlage; Demo-Response-Formate sind nicht bindend.

| Feld | Wert |
|---|---|
| **Version** | 1.0 |
| **Datum** | 2026-07-12 |
| **Grundlage** | Domänenmodell ADR-001 (health_subject-Zielmodell), Privacy-Entscheidungen ELYO-101 / DSFA-Vorprüfung (`docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md`), Scope-Entscheidungen ELYO-99 (`docs/decisions/elyo-99-scope-decisions.md`), Gap-Analyse |
| **Abhängigkeit** | ELYO-99 (Scope-Entscheidungen je Demo-Feature) |
| **Input für** | ELYO-114 (finaler OpenAPI-Contract), ELYO-133 (Check-in-Contract Detail), ELYO-113 (Laborwert-Historie), ELYO-117 (Dashboard-Blöcke) |

**Status-Legende:** `DECIDED`, `OPEN`, `DEFERRED`, `OUT_OF_SCOPE`. Referenz-IDs bleiben stabil.

---

# 0. Übernommene Grundlagen

## 0.1 health_subject-Kompatibilität als Contract-Anforderung

- **Status:** DECIDED (übernommen aus ADR-001)
- **Entscheidung:** Alle Contracts für Gesundheitsdaten (lab-markers, Check-in, Dashboard-Gesundheitsblöcke) sind so geschnitten, dass die Persistenz am `health_subject_id` in `elyo_health` erfolgt. Kein Contract-Feld setzt eine `user_id`-Verknüpfung in der Health-Domäne voraus; Ressourcen-IDs sind opake IDs (ULID) ohne Identity-Semantik.
- **Begründung:** Ticket-Vorgabe (Privacy/Health-Data); ADR-001 §2.1/2.6.
- **ADR-relevant:** JA (bestätigt ADR-001)

## 0.2 Demo = Referenz-Input, nicht Grundlage

- **Status:** DECIDED (übernommen aus ELYO-99)
- **Entscheidung:** Abweichungen vom Demo-Format sind erlaubt und werden in §5 dokumentiert, wo sie fachlich geboten sind.
- **Begründung:** ELYO-99-Grundsatz; ADR-001 0.6 (Demo-Schema wird nicht fortgeführt).

## 0.3 Blocker-Steuerung bleibt unberührt

- **Status:** DECIDED (übernommen aus ELYO-101/ADR-002)
- **Entscheidung:** Diese Contract-Entscheidungen legen die Schnittstelle fest, heben aber keinen Blocker auf: produktive Persistenz realer Gesundheitsdaten bleibt bis zum Testnachweis R1–R3 (ELYO-111) und Kenntnisnahme der DSFA-Vorprüfung gesperrt.

---

# 1. lab-markers

## 1.1 Read-Contract: Ressourcenschnitt und Felder

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** `GET /employee/lab-markers` liefert je Marker den **jeweils aktuellsten Messwert** einer historienfähigen Messreihe. Felder je Marker: `markerKey` (stabiler Key), `name`, `unit`, `value`, `measuredAt` (Pflicht, Datum der Messung), `status` (Enum, siehe 1.2), `low`/`high` (Orientierungsbereich, nullable), `group` (Enum: `blutbild | immun | mikro | sonstige`), `source` (Provenienz, siehe 1.4), `id` (opake ID des Messwerts).
- **Begründung:** Demo-Modell (ein Wert je Marker, kein Messzeitpunkt) ist laut Gap-Analyse/DSFA §3.1 unzureichend; Historie und Messdatum sind im Zielmodell von Anfang an vorgesehen (ELYO-113). Feldset (Name, Einheit, Range, Gruppe, Status) aus der Demo als validierte UX-Referenz übernommen.
- **Folgeaufgabe:** Schema-Detail in ELYO-114; Modell in ELYO-105.
- **ADR-relevant:** NEIN

## 1.2 Soft-Status als stabile Keys

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** `status`-Enum mit stabilen technischen Keys: `below_range | in_range | above_range`. Anzeige-Wording (nicht-diagnostisch, z. B. „unter Orientierungsbereich") liegt beim Frontend bzw. im Erklärungs-Katalog (ELYO-94), nicht im Daten-Contract.
- **Begründung:** Demo kodiert deutsche Anzeigetexte als Enum-Werte („unter Bereich") — Lokalisierungs- und Wording-Änderungen würden zu Breaking Changes im Contract. Wording-Grenze (Health Data Guardrails) bleibt über den Katalog steuerbar.
- **ADR-relevant:** NEIN

## 1.3 Verlauf (Historie)

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** Eigener Lesepfad `GET /employee/lab-markers/{markerKey}/history`: chronologische Messwerte (`value`, `measuredAt`, `source`, `status`) des authentifizierten Beschäftigten für einen Marker. Paginierung contract-definiert; leere Historie = leere Liste (kein 404, sofern der Marker-Key existiert).
- **Begründung:** Historienfähigkeit ist Kernabweichung vom Demo-Modell (ELYO-113, DSFA §3.1 Zeitbezug). Getrennter Endpoint hält die Übersichts-Response schlank.
- **Folgeaufgabe:** ELYO-113/114.
- **ADR-relevant:** NEIN

## 1.4 Schreibpfad: manuelle Eingabe im MVP

- **Status:** DECIDED (2026-07-12, Produktentscheidung Björn)
- **Entscheidung:** **Es gibt einen Schreibpfad.** MVP: ausschließlich **manuelle Eingabe** durch den Beschäftigten — `POST /employee/lab-markers` (neuer Messwert je Marker mit `value`, `measuredAt`, optional Korrektur/Löschung eigener Einträge via `DELETE /employee/lab-markers/{id}`). Der Contract führt ein Pflichtfeld `source` (Enum, MVP: `manual`; reserviert: `document_import`, `bgm_import`), damit spätere Importpfade **additiv** ergänzt werden können. Dokumentenimport ist **nicht** im MVP (DEFERRED, eigenes Feature inkl. eigener Privacy-Prüfung, vgl. DSFA Anhang B).
- **Begründung:** Beantwortet die offene Frage aus dem Ticket und DSFA-Frage 1 (Arbeitshypothese „Lesepfad + manuelle Eingabe" aus DSFA §4.1 wird bestätigt). Ohne Schreibpfad hätte das Feature keine reale Datenquelle; Dokumentenimport bringt neue Empfänger-/Auftragsverarbeiter-Fragen und bleibt draußen.
- **Folgeaufgabe:** Validierungsregeln (Plausibilitätsgrenzen je Marker) in ELYO-114; DSFA-Vorprüfung nachziehen (Re-Review-Trigger ADR-002 §2.8).
- **ADR-relevant:** JA (Provenienz-Design)

## 1.5 Autorisierung lab-markers

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** Alle lab-marker-Endpoints: Sanctum-Auth, nur Rolle Employee, ausschließlich eigene Daten (Auflösung über `resolveOwnSubject`, Purpose-Code + Audit). Company/Admin/Partner-Rollen → 403. Fremde Messwert-IDs → 404. **Negativzusage:** Kein Company-/Admin-/Reporting-Endpoint exponiert individuelle Laborwerte oder Laborwert-Aggregate (Allowlist-Prinzip ADR-001 §2.5: Laborwerte nie reportbar); abgesichert durch Boundary-Tests (ELYO-111).
- **Begründung:** DSFA §4.4/§6; AGENTS.md Health-Data-Regeln.
- **ADR-relevant:** JA (bestätigt ADR-001)

---

# 2. Erweiterter Dashboard-Payload

## 2.1 Blöcke im Contract v1

- **Status:** DECIDED (2026-07-12, Produktentscheidung Björn)
- **Entscheidung:** `GET /employee/dashboard` definiert im produktiven Contract v1 **nur Blöcke mit realer Produktivquelle**: `wellbeing` (Wochenscore, current/previous/delta, Sparkline, `scale: 5`), `metrics` (mood/energy/stress-Aggregate Woche-über-Woche) und `sleep` (currentH/previousH) — `sleep` **unter der Bedingung**, dass der Check-in-Contract (ELYO-133) Schlaf als sofort persistierbares Feld aufnimmt (vgl. 3.2); bis dahin liefert der Block `null`. **Nicht im Contract v1:** `bodySignals`, `healthFlag`, `levers` — Aufnahme erst nach ELYO-91 + Per-Block-Entscheidung (ELYO-117) als additive Contract-Erweiterung.
- **Begründung:** bodySignals/healthFlag sind — sobald real — individuelle Gesundheitsdaten mit offener Produktentscheidung (ELYO-99 §4, DSFA R7); ungeklärte Health-Strukturen gehören nicht in den v1-Contract. Demo-Blöcke waren Provider-Blöcke mit `null` in prod — tote Felder werden nicht standardisiert.
- **Folgeaufgabe:** ELYO-117 (Per-Block-Entscheidung), ELYO-136 (Schonmodus-Regelwerk).
- **ADR-relevant:** NEIN

## 2.2 Bestehende Basisfelder und Aggregatlogik

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** Basisfelder aus main bleiben erhalten: `latest`, `entries`, `streakCount`, `points`, `todayCheckinCompleted`. Aggregatsemantik des Wellbeing-Scores (Mittel aus mood, invertiertem stress, energy; kanonische 1–5-Skala) wird im Contract dokumentiert; die Demo-`EmployeeDashboardService`-Logik dient als funktionale Spezifikation (ELYO-99 §4), wird aber neu implementiert.
- **Begründung:** Additive Erweiterung hält den Dashboard-Endpoint abwärtskompatibel (vgl. §4).
- **ADR-relevant:** NEIN

## 2.3 Autorisierung Dashboard

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** Sanctum-Auth, Rolle Employee, nur eigene Aggregate (health_subject-Auflösung wie 1.5). `levers` — falls später aufgenommen — referenzieren ausschließlich eigene zugewiesene System-Maßnahmen (Demo-`resolveLevers` als Referenz).
- **ADR-relevant:** NEIN

---

# 3. Check-in-Persistenz

## 3.1 Kanonische Skala 1–5

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** `POST /employee/checkin` validiert `mood`, `energy`, `stress` als Pflichtfelder Integer 1–5 (main: 1–10). Bestandsdaten werden per eigenständig gereviewter Migration gemappt (ELYO-135); die Mapping-Semantik 1–10 → 1–5 wird im Contract dokumentiert. `WellbeingEntry`-Responses (dashboard/history/checkin-status) liefern durchgängig 1–5-Werte; `score` wird auf der 1–5-Skala berechnet.
- **Begründung:** ELYO-99 §5 (produktive Einführung der 1–5-Skala); Breaking Change gegenüber main, siehe §4.
- **ADR-relevant:** NEIN

## 3.2 Feldumfang: Drei-Klassen-Trennung

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** Der Contract übernimmt die Drei-Klassen-Trennung der DSFA §3.2 wörtlich:
  - **Sofort persistierbar (Contract v1):** `mood`/`energy`/`stress` (1–5, Pflicht), `location` (optional, Enum — Wertemenge in ELYO-133 festlegen), `sleep` (optional, Stunden — Detailtyp in ELYO-133).
  - **Hardening-gated (nicht im Contract v1):** Symptome (inkl. Schmerzregionen/-stärke), Krankheitstypen — erst nach ELYO-91 **und** Produktentscheidung (DSFA-Frage 2); Aufnahme später nur additiv.
  - **Gestrichen:** Freitext `note`, siehe 3.3.
- **Begründung:** Trennt persistierbare von entscheidungsoffenen Feldern, hält den v1-Contract stabil und die Blocker-Steuerung intakt. Demo-localStorage-Erfassung (Symptome/Krankheit) ist explizit ausgeschlossen (ELYO-134).
- **Folgeaufgabe:** ELYO-133 (Detail-Contract), ELYO-109 bleibt für note-Wiedereinführung zuständig.
- **ADR-relevant:** NEIN

## 3.3 Freitext `note` gestrichen

- **Status:** DECIDED (2026-07-12, Produktentscheidung Björn)
- **Entscheidung:** Das Freitextfeld `note` wird aus dem produktiven Check-in-Contract **gestrichen** (Request und Response). Vorhandene Bestandsnotizen werden im Rahmen der Altbestands-Behandlung (DSFA Anhang B, ELYO-110) bewertet. Wiedereinführung nur nach Privacy-Entscheidung ELYO-109, dann als additive Erweiterung mit Minimierungskonzept.
- **Begründung:** DSFA R5/Z7 (High: beliebige Gesundheitsangaben im Freitext). Streichung entschärft das Risiko sofort und ohne offene Stelle in ELYO-114.
- **ADR-relevant:** JA (Datenminimierung)

## 3.4 Persistenz- und Fehlersemantik

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** Ein Check-in je Beschäftigtem und Tagesperiode; Wiederholung → `409` mit `CHECKIN_ALREADY_DONE` (Fehlerformat gemäß `docs/ai-context/api-contract-rules.md`). `GET /employee/checkin/status` bleibt mit `completed` + `entry` (nullable). Erfolgs-Response bleibt `success`/`score`/`periodKey`. Persistenz-Ziel ist die Health-Domäne am `health_subject_id`; bis ELYO-91 umgesetzt ist, gilt die Blocker-Steuerung aus 0.3 für jede Erweiterung über den main-Bestand hinaus.
- **Begründung:** Bewährte Semantik aus main; Zielmodell-Kompatibilität per 0.1.
- **ADR-relevant:** NEIN

---

# 4. Route-Semantik und Breaking Changes gegenüber main

## 4.1 Route-Split /employee/measures vs. /employee/company-measures

- **Status:** DECIDED (2026-07-12, Produktentscheidung Björn)
- **Entscheidung:** Der Demo-Split wird produktiv übernommen: `GET /employee/measures` = **eigene zugewiesene System-Maßnahmen** (inkl. `GET /employee/measures/{userSystemMeasure}` Detail, numerische ID, fremde IDs → 404); `GET /employee/company-measures` = Firmen-/Team-Maßnahmen mit Teilnahmestatus (bisherige main-Semantik von `/employee/measures`). `POST /employee/measures/{measure}/participate` und `POST /employee/measure-checkins/{token}` bleiben unverändert. Feld-Contract des Measures-Hub wird in ELYO-114/116 gegen die Autorisierungsmatrix verifiziert; Demo-Anzeigefelder ohne Produktivquelle (Demo-Streak, „last session"-Effekt) gehen nicht ungeprüft in den Contract.
- **Begründung:** Semantisch saubere Benennung („meine Maßnahmen" unter `/measures`). Der Breaking Change ist vertretbar: das Frontend wird ohnehin neu gebaut, es gibt keine externen API-Konsumenten. Bewusste Entscheidung gemäß ELYO-99 §9.
- **ADR-relevant:** NEIN

## 4.2 Breaking-Change-Inventar gegenüber main

- **Status:** DECIDED (2026-07-12)
- **Entscheidung:** Folgende Breaking Changes sind identifiziert und werden in ELYO-114 als solche ausgewiesen:

| # | Endpoint | Änderung | Typ |
|---|---|---|---|
| B1 | `GET /employee/measures` | Response-Shape komplett neu: `EmployeeMeasure[]` → `AssignedMeasure[]`; Semantik wechselt von Firmen- zu System-Maßnahmen | **Breaking** |
| B2 | `GET /employee/company-measures` | Neue Route übernimmt die bisherige Semantik von B1; Clients der alten Route müssen umziehen | **Breaking** (Folge von B1) |
| B3 | `POST /employee/checkin` | Validierung `mood`/`energy`/`stress` 1–10 → 1–5; Werte 6–10 → 422 | **Breaking** + Datenmigration (ELYO-135) |
| B4 | `POST /employee/checkin` | `note` wird nicht mehr akzeptiert/persistiert | **Breaking** (Feld-Streichung) |
| B5 | `WellbeingEntry` (dashboard/history/status) | Wertebereich mood/energy/stress und `score`-Semantik wechseln auf 1–5 | **Breaking** (Anzeige-Logik) |

  Nicht breaking (additiv): Dashboard-Erweiterung §2.1, neue lab-marker-Endpoints §1, optionale Check-in-Felder `location`/`sleep`.
- **Begründung:** Akzeptanzkriterium „Breaking Changes gegenüber main sind identifiziert".
- **ADR-relevant:** NEIN

---

# 5. Dokumentierte Abweichungen vom Demo-Format

| # | Bereich | Demo | Produktiv (Entscheidung) | Fachlicher Grund |
|---|---|---|---|---|
| A1 | lab-markers | Ein Wert je Marker, kein Messzeitpunkt | Historienfähige Messreihe, `measuredAt` Pflicht, History-Endpoint | Zielmodell ELYO-113; Verlauf ist Kernanforderung (1.1/1.3) |
| A2 | lab-markers | `status`-Enum = deutsche Anzeigetexte | Stabile Keys `below_range`/`in_range`/`above_range` | Lokalisierung/Wording nicht im Daten-Contract (1.2) |
| A3 | lab-markers | `isHighlighted` als Server-Flag | Gestrichen — ableitbar aus `status` | Redundantes, präsentationsnahes Feld (1.1) |
| A4 | lab-markers | Kein Schreibpfad (nur Seed) | `POST` manuelle Eingabe + `source`-Provenienz | Produktentscheidung 1.4; DSFA-Frage 1 |
| A5 | Dashboard | 6 Zusatzblöcke, davon 4 Demo-Provider (`null` in prod) | v1 nur `wellbeing`/`metrics`/`sleep`; Rest erst nach ELYO-117 | Keine ungeklärten Health-Strukturen im Contract (2.1) |
| A6 | Check-in | `note` im Request | Gestrichen | DSFA R5/Z7 (3.3) |
| A7 | Check-in | Symptome/Krankheit nur in localStorage | Nicht im Contract v1 (hardening-gated) | DSFA-Frage 2; ELYO-91-Gate (3.2) |
| A8 | Check-in | Kein `location`/`sleep` im Demo-API-Contract | Optional im v1 (Detail in ELYO-133) | Sofort persistierbare Klasse laut DSFA §3.2 (3.2) |
| A9 | Measures-Hub | Demo-Streak, „last session"-Effekt aus Recommendation-Context | Nicht ungeprüft übernehmen; Verifikation in ELYO-114/116 | Keine Felder ohne Produktivquelle (4.1) |

---

# 6. Validierung und Übergabe

- **Review:** Contract-Entscheidungen mit Frontend- und Backend-Verantwortlichen reviewen (Kommentar in ELYO-102 oder inline hier). Abgleich erfolgt gegen die fachlichen Anforderungen der Gap-Analyse, nicht gegen den Demo-Code.
- **Referenzierbarkeit:** ELYO-114 zitiert Entscheidungen per Referenz-ID (z. B. „1.4", „4.2 B3").
- **Re-Review-Trigger DSFA:** 1.4 (Schreibpfad entschieden) und 3.2/3.3 (Check-in-Feldumfang) sind Re-Review-Trigger gemäß ADR-002 §2.8 — DSFA-Vorprüfung §2 V1 und §9 Frage 1/3 nachziehen.
- **Offen (bewusst delegiert):** `location`-Wertemenge und `sleep`-Detailtyp (ELYO-133); Plausibilitätsgrenzen je Marker (ELYO-114); Per-Block-Entscheidung bodySignals/healthFlag/levers (ELYO-117).

## Sign-off

- [ ] Frontend-Verantwortliche:
- [ ] Backend-Verantwortliche:
- [ ] Privacy-Kenntnisnahme (nur 1.4/3.3, Re-Review-Trigger):
