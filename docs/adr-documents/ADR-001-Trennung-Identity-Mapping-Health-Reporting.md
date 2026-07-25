# ADR-001: Trennung von Identity-, Mapping-, Health- und Reporting-Daten

| Feld | Wert |
|---|---|
| **Status** | Zur Abnahme (Privacy-Review ausstehend) |
| **Datum** | 2026-07-12 |
| **Entscheider** | Tech-Lead |
| **Abnahme** | Interne Kontrollpersonen (siehe 18.1/18.2 im Entscheidungsbogen) |
| **Grundlage** | ELYO-100-Architektur-Entscheidungsbogen (alle Punkte DECIDED, Stand 2026-07-12) |

---

## 1. Kontext

Elyo verarbeitet Gesundheitsdaten von Beschäftigten, die von ihrem Arbeitgeber eingeladen und gesponsert werden. Daraus entsteht ein struktureller Konflikt: Der Arbeitgeber finanziert die Plattform und erhält Reports, darf aber unter keinen Umständen Rückschlüsse auf die Gesundheitsdaten einzelner Beschäftigter ziehen können.

Die bisherige Demo-Struktur (gemeinsame Datenbank, direkte `user_id`-Verknüpfungen an Gesundheitsdaten) bietet diese Garantie nicht. Für den Pilotbetrieb mit echten Kunden braucht es eine Architektur, die die Trennung technisch erzwingt statt sie nur organisatorisch zu versprechen.

## 2. Entscheidung

### 2.1 Vier getrennte Datenbanken, ein Cluster

Vier PostgreSQL-Datenbanken auf einem gemeinsamen Cluster: `elyo_identity`, `elyo_subject_mapping`, `elyo_health`, `elyo_reporting`, ergänzt um eine separate Audit-Datenbank. Die Verbindung zwischen Person (`user_id`) und Gesundheitsprofil (`health_subject_id`) existiert ausschließlich in der Mapping-Datenbank.

### 2.2 Globales Health Subject

Jeder Nutzer erhält bei erfolgreicher Selbstregistrierung ein globales, arbeitgeberunabhängiges `health_subject_id`. Die Provisionierung startet synchron unmittelbar nach dem Commit der Identity-Registrierung; innerhalb der Mapping-Provisionierung gilt die Reihenfolge Subject vor Mapping. Schlägt die domänenübergreifende Provisionierung fehl, bleibt die Registrierung gültig, der Fehler wird ohne Identifikatoren protokolliert und ein idempotenter Abgleich (`elyo:provision-subjects`) repariert die fehlende Zuordnung. Der Gesundheitsverlauf bleibt bei Arbeitgeberwechseln erhalten; ohne aktive Membership oder ohne Sponsor gilt ein kostenloser Read-only-Basisstatus (Bestandsdaten sichtbar und exportierbar, keine neue Erfassung).

### 2.3 Mapping als geschützte Domäne

Kein freier ORM-Zugriff auf das Mapping. Es existieren genau fünf zweckgebundene, identifiertragende Lifecycle-Operationen (`provisionOwnSubject`, `resolveOwnSubject`, `resolveReportingCohort`, `revokeSubjectLink`, `resolveForDataSubjectRequest`), jede exakt einer Runtime zugeordnet, jede mit Pflicht-Purpose-Code und Audit. Als enger Bestandteil der Provisionierungsoperation darf die Identity-Runtime über `provisioningStateForUser` ausschließlich den nicht-identifizierenden Zustand `MISSING`, `ACTIVE` oder `REVOKED` abfragen; der Aufruf verwendet denselben Provisionierungs-Purpose und wird wie `provisionOwnSubject` auditiert. Statusmodell: nur `ACTIVE` und `REVOKED` (endgültig, Tombstone); `MISSING` bezeichnet das Fehlen einer Mapping-Zeile und ist kein persistierter Status. Re-Identifizierung ist ein Ausnahmeprozess mit Vier-Augen-Freigabe, Einzelauflösung, 24-Stunden-Befristung und Exportverbot.

### 2.4 Fünf Runtimes aus einem Codebestand

Identity API, Employee Health API, Company API, Reporting Worker (geplant per Scheduler) und Privacy/Admin-Runtime laufen als getrennte Prozesse aus einem identischen Container-Image mit Startprofilen. Jede Runtime besitzt eine eigene PostgreSQL-Rolle mit minimalen Rechten; die Company API hat keinerlei Zugriff auf Mapping oder Health. Es gibt keine direkte Runtime-zu-Runtime-Kommunikation; jede Runtime arbeitet nur auf ihren erlaubten Datenbanken. Migrationen laufen über eine separate Rolle, die nie in Runtime-Containern liegt.

### 2.5 Reporting ohne Personenbezug

Reporting speichert ausschließlich suppressionsgeprüfte Aggregate — keine `user_id`, keine `health_subject_id`, und statt der echten `company_id` eine eigene `reporting_tenant_id`. Im Pilot gibt es genau eine Kohorte je Kunde (Gesamtunternehmen) und Reports nur für feste Kalenderquartale als unveränderliche, versionierte Snapshots. In Reports fließen nur Daten ein, die während der aktiven Membership entstanden sind (Messdatum maßgeblich).

Suppression: Mindestschwelle 10 (sensible Kennzahlen: 20), Formel `max(platform_minimum, customer_threshold, metric_threshold)`; Freigabe nur wenn `eligible_count` und `contributor_count` die Schwelle erreichen. Kategorien unter 5 Beitragenden werden zusammengefasst oder unterdrückt; Prozentwerte auf 5 % gerundet. Medizinisch konkrete Inhalte (Laborwerte, Diagnosen, Medikamente, psychische Gesundheit, Schwangerschaft, Freitexte, Dokumente) sind grundsätzlich nicht reportbar; neue Kennzahlen brauchen eine explizite Allowlist-Freigabe mit Privacy-Review.

### 2.6 Datenmodellgrenzen

Alles, was Gesundheit oder Verhalten beschreibt (inkl. Check-ins, Wearables, Punkte/Streaks/Badges, Survey-Antworten), liegt in der Health-Domäne am `health_subject_id`. Health kennt keinerlei Unternehmensbezug; Sponsoring und Entitlements liegen in Identity. Consent Records liegen in Identity am `user_id`, zweckgebunden, versioniert und append-only; Widerruf erfolgt pro Zweck und stoppt nur zukünftige Verarbeitung.

### 2.7 Rollen, Audit und Break-glass

Vier Rollen im Pilot: Platform Admin, Company Admin, Support Admin, Privacy Admin (nur letzterer darf Mapping auflösen und Audit lesen). Auditiert wird das sicherheitskritische Set (Mapping-Zugriffe, Break-glass, Berechtigungsänderungen, Löschungen, Exporte, Dokumentabrufe, Reporting-Jobs, Fehlzugriffe) in einer append-only Audit-DB (INSERT-only-Grants, 2 Jahre Aufbewahrung). Audit-Einträge enthalten nie `user_id` und `health_subject_id` gemeinsam.

### 2.8 Lifecycle und Löschung

Accounts sind aktiv oder gelöscht (kein Zwischenzustand im Pilot). Löschverfahren mit 30-Tage-Frist: physische Löschung von Identity- und Health-Daten, Mapping zuletzt; anonyme Aggregate bleiben; Backups laufen über eine 90-Tage-Rotation aus, der Restore-Prozess prüft die Löschliste. Bei Kündigung eines Kunden enden alle Memberships (Nutzer fallen in den Basisstatus), persönliche Health Accounts bleiben bestehen, kundenspezifische Aggregate werden nach 12 Monaten gelöscht.

### 2.9 Verschlüsselung, Storage, Backup

Volume-Verschlüsselung für den Cluster, verschlüsselte Backups, zusätzlich Feldverschlüsselung nur für die Mapping-Tabelle mit eigenem Schlüssel. Schlüssel und Credentials als Docker Secrets, getrennt pro Domäne, manuelle Rotation per Runbook. Health-Dokumente in eigenem Bucket mit eigenen Credentials, pseudonymen Pfaden (UUIDs, nie Namen/E-Mail), Metadaten-Bereinigung, Virenscan, Typ-Allowlist und kurzlebigen signierten URLs. Backups als tägliche, domänengetrennte Dumps (Retention 90 Tage); Restore immer auf gemeinsamen Zeitpunkt mit Löschlisten-Check; quartalsweise Restore-Tests (RPO/RTO 24 h).

### 2.10 Absicherung durch Tests

Automatisierte Boundary-Tests laufen bei jedem Merge in der CI gegen PostgreSQL mit den echten Rollen-Grants: Company liest kein Mapping/Health, Reporting liest keine Rohdaten, keine Runtime besitzt alle Credentials, Mapping-Aufrufe erzeugen Audit-Events, unterdrückte Aggregate werden nicht ausgegeben, Logs enthalten keine sensiblen IDs.

## 3. Konsequenzen

**Positiv:**

- Die Trennung wird auf jeder Ebene erzwungen (DB-Rollen, Runtimes, Storage, Backup, Tests) — ein einzelner kompromittierter Zugang genügt nicht für eine Re-Identifikation.
- Arbeitgeber können strukturell keine Individualdaten sehen: eine Kohorte, feste Quartale, unveränderliche Snapshots, Allowlist-Kennzahlen.
- Der persönliche Gesundheitsverlauf überlebt Arbeitgeberwechsel und Kundenkündigung.

**Negativ / bewusst akzeptiert:**

- Keine echte Transaktion über DB-Grenzen: Die Identity-Registrierung kann erfolgreich sein, obwohl die anschließende Subject-Provisionierung fehlschlägt; Reihenfolge (Subject zuerst), generische Fehlerprotokollierung und der idempotente Abgleich kompensieren diesen Teilfehler.
- Kein Sperr-/Deaktivierungszustand im Pilot — bei Missbrauch bleibt nur die endgültige Löschung (11.1).
- Ein privilegierter Admin-Account für den Tech-Lead statt getrennter Rollen-Accounts; Break-glass bleibt über den Vier-Augen-Prozess geschützt (9.2).
- Die Datenschutz-Folgenabschätzung wird parallel zum Pilot nachgezogen statt vorab erstellt — dokumentiertes rechtliches Restrisiko (18.1).
- Betriebsmehraufwand: fünf Runtimes, getrennte Rollen, Secrets und Backups.

**Bewusst vertagt (nicht Teil dieser ADR):**

- Self-Service-Änderung der primären E-Mail (1.2), Einladungen ohne persönliche Firmenadresse (1.3), feinere Reporting-Kohorten (7.3-Folgeticket), OCR/Dokumentextraktion (13.3), KMS/Secret Manager (12.2), fachlich-medizinischer Owner (17.1).

## 4. Betrachtete Alternativen

- **Reine Schema-Trennung in einer Datenbank:** verworfen — schützt nicht gegen triviale Joins und kompromittierte Credentials.
- **Vollständig getrennte Microservices mit eigenen Clustern:** verworfen — Betriebsaufwand für ein Pilot-Team unverhältnismäßig.
- **Eigener Mapping-Service als sechste Runtime:** verworfen — stärkste Isolation, aber zusätzliche Deployment- und Kommunikationskomplexität; DB-Rollen-Trennung plus Operations-Matrix erreicht das Schutzziel.
- **Pseudonymisierung ohne getrennte Mapping-Domäne (nur ID-Austausch):** verworfen — ohne technische Zugriffsbeschränkung wertlos (0.4).

## 5. Referenzen

- ELYO-100-Architektur-Entscheidungsbogen (vollständige Einzelentscheidungen mit Begründungen und Folgeaufgaben; Referenz-IDs 0.1–19.2)
- Folgeaufgaben-Backlog: Mapping-Contract (5.1), Access Matrix (6.2), Membership-Transition-Spezifikation (2.2), Reporting-Zeitlogik (2.3), Security Test Specification (19.1), DSFA (18.1), Laborwert-Domänenmodell (0.6), Code-/Schema-Inventar (15.1)
