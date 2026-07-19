# ELYO-101 – Entscheidungsbogen DSFA-Vorprüfung (technische Zuarbeit)

## Zweck

Zentrale Arbeitsgrundlage für die technische Vorprüfung zur Datenschutz-Folgenabschätzung (DSFA) der geplanten produktiven Laborwerte- und Check-in-Verarbeitung. Keine Rechtsberatung — ausschließlich technische Faktensammlung und Risikoklassifikation als Zuarbeit für die Datenschutz-Verantwortlichen.

Vorgehen analog ELYO-100: stabile Referenz-IDs, Status je Punkt, am Ende ADR als Abnahme-Element.

## Verwendung

- **Status:** `DECIDED`, `OPEN`, `DEFERRED`, `OUT_OF_SCOPE`, `PARTIALLY_DECIDED`
- **Entscheidung / Antwort**, **Begründung**, **Folgeaufgabe**, **ADR-relevant:** `JA`/`NEIN`

Referenz-IDs bleiben stabil.

---

# 0. Bereits festgelegte Grundlagen (aus ELYO-100 / ADR-001 / Gap-Analyse übernommen)

## 0.1 Zielarchitektur als Bezugsrahmen

- **Status:** DECIDED (übernommen)
- **Entscheidung / Antwort:** Die Datenflüsse werden gegen die in ADR-001 beschlossene Zielarchitektur dokumentiert (4 DBs, Mapping-Domäne, 5 Runtimes, Reporting-Aggregate, Suppression).
- **Begründung:** ADR-001 ist die verbindliche Zielarchitektur; die DSFA bewertet die geplante produktive Verarbeitung.
- **ADR-relevant:** JA

## 0.2 Demo = Risiko-Inventar, nicht Zielmodell

- **Status:** DECIDED (übernommen)
- **Entscheidung / Antwort:** Der Demo-Branch (`demo/employee-lab-values-dashboard`) dient ausschließlich als Anschauungsmaterial für geplante Datenkategorien und als Risiko-Inventar. Kein Demo-Artefakt wird als Produktionszustand beschrieben.
- **Begründung:** Guiding Principle der Gap-Analyse; ADR-001 §1.
- **ADR-relevant:** JA

## 0.3 Risikotaxonomie

- **Status:** DECIDED (übernommen, Detaillierung unter 3.x)
- **Entscheidung / Antwort:** Klassifikation Low / Medium / High / Blocker aus der Gap-Analyse (Health Data Separation Review, Findings 1–13).
- **Begründung:** Ticket-Vorgabe; Konsistenz mit bestehendem Review.
- **ADR-relevant:** JA

## 0.4 DSFA-Timing

- **Status:** DECIDED (übernommen aus ELYO-100 / 18.1)
- **Entscheidung / Antwort:** Die DSFA selbst wird parallel zum Pilot mit höchster Priorität erstellt; diese Vorprüfung ist die technische Zuarbeit dafür. Das dokumentierte rechtliche Restrisiko (Art. 35 DSGVO regelmäßig vor Verarbeitungsbeginn) bleibt bestehen und wird in der Übergabe explizit benannt.
- **ADR-relevant:** JA

---

# 1. Scope der Vorprüfung

## 1.1 Verarbeitungstätigkeiten im Scope

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Kern-Scope = Laborwerte + Check-in (inkl. Historie/Timeline als Lesepfad). Bestehende Bestände (Wellbeing, Dokumente, Anamnese, Surveys) als **Inventar-Anhang** mit Kurzeinstufung, ohne vollständige Datenflussanalyse — mit expliziter Empfehlung, sie in der DSFA selbst zu behandeln.
- **Begründung:** Ticket-Scope schlank halten; die DSFA braucht aber ein vollständiges Bild der Health-Datenbestände, sonst scheitert die Vollständigkeitsprüfung.
- **ADR-relevant:** JA

## 1.2 Demo-Risiko-Inventar: Umfang

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Vollständige Liste aus dem Health Data Separation Review: `lab_markers`-Tabelle/-Model/-Controller/-Seeder, Check-in-`note`-Freitext, localStorage-Check-in (Symptome/Krankheit), `employee-dashboard.json` (Body Signals/Health Flag), LAB-Badge-Kategorie, Infection-Radar-Aggregate, Demo-Seeder (`LabValueDemoSeeder`). Jeweils mit Dateireferenz und Einstufung.
- **Begründung:** Deckungsgleich mit Gap-Analyse → Vollständigkeitsprüfung trivial.
- **ADR-relevant:** JA

## 1.3 Bezugszeitpunkte der Datenflussdokumentation

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Zwei Ebenen: (a) Ziel-Datenflüsse der produktiven Verarbeitung (Hauptteil, DSFA-relevant), (b) Demo-Ist nur als Risiko-Inventar (1.2), nicht als Datenflussanalyse. Kein „Migrationspfad Demo→Prod", da das Demo-Schema laut ADR-001/0.6 nicht fortgeführt wird.
- **ADR-relevant:** JA

---

# 2. Dokumentstruktur und Methodik

## 2.1 Format und Ablageort

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Versionierte Markdown-Datei im Repo (analog Abnahmeprotokoll-Entscheidung 18.2 aus ELYO-100), neuer Ordner `docs/privacy/`, Datei `dsfa-vorpruefung-laborwerte-checkin.md`. Export für Übergabe siehe 5.2.
- **ADR-relevant:** JA

## 2.2 Datenfluss-Notation

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Pro Verarbeitung ein Mermaid-Flussdiagramm (Quelle → Runtime → DB/Storage → Empfänger) plus je eine strukturierte Tabelle: Datenkategorie, Feld(er), Zweck, Speicherort (DB/Tabelle/Bucket), Zugriff (Runtime/Rolle), Aufbewahrung/Löschung, Risiko-Einstufung.
- **Begründung:** Tabellen sind DSFA-anschlussfähig; Diagramme machen die Trennungsarchitektur für Externe verständlich.
- **ADR-relevant:** JA

## 2.3 Gliederung nach DSFA-Zuarbeit-Logik

- **Status:** OPEN
- **Frage:** Feste Gliederung des Dokuments?
- **Vorschlag:** 1. Systemkontext & Zielarchitektur (Kurzfassung ADR-001), 2. Verarbeitungstätigkeiten & Zwecke, 3. Datenkategorien (inkl. Art.-9-Markierung, 4.1), 4. Datenflüsse (je Verarbeitung), 5. Speicherorte & Aufbewahrung, 6. Zugriffe & Empfänger (Rollen-/Runtime-Matrix), 7. Risiko-Inventar Demo, 8. Risikoklassifikation Zielarchitektur, 9. Offene Fragen an DSB, 10. Übergabe-/Statusvermerk.
- **ADR-relevant:** JA

---

# 3. Risikoklassifikation

## 3.1 Übernahme vs. Neubewertung der Gap-Analyse-Einstufungen

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Beide Spalten führen: „Einstufung Demo-Ist" (unverändert aus Gap-Analyse) und „Restrisiko Zielarchitektur (geplant)" mit Verweis auf die adressierende ADR-001-Entscheidung und den Umsetzungsstatus (ELYO-91-Tickets). Blocker bleiben Blocker, bis die Umsetzung nachgewiesen ist (Boundary-Tests, ADR-001 §2.10).
- **Begründung:** DSB braucht beides: was das Demo-Material zeigt und was die geplante Architektur davon löst — ohne so zu tun, als sei es schon gelöst.
- **ADR-relevant:** JA

## 3.2 Definition der Stufen

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Ja, kurz: **Blocker** = verhindert produktive Verarbeitung realer Daten, bis behoben; **High** = Re-Identifikation oder unbefugter Health-Zugriff realistisch möglich; **Medium** = Schutzverletzung nur unter Zusatzbedingungen / begrenzte Sensitivität; **Low** = kein personenbezogenes Risiko oder rein demo-gated.
- **ADR-relevant:** JA

## 3.3 Format der Dateireferenzen

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Repo-relativer Pfad + Klasse/Migration (z. B. `apps/api-laravel/database/migrations/2026_07_05_010000_create_lab_markers_table.php`), Branch-Angabe wenn nicht `main`. Keine Zeilennummern (instabil).
- **ADR-relevant:** NEIN (handwerklich)

---

# 4. Datenkategorien

## 4.1 Art.-9-Markierung

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Ja, rein deskriptive Spalte „Gesundheitsdatenbezug: direkt / abgeleitet / kein" — die rechtliche Subsumtion bleibt beim DSB. Laborwerte/Symptome/Krankheit = direkt; mood/energy/stress, Schonmodus, Badges mit LAB-Bezug, Maßnahmen-Teilnahme = abgeleitet/gesundheitsnah; Standort/Login = kein.
- **ADR-relevant:** JA

## 4.2 Check-in-Feldkatalog für die Ziel-Verarbeitung

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Drei-Klassen-Trennung der Gap-Analyse übernehmen: sofort persistierbar (mood/energy/stress 1–5, Location, ggf. Sleep) vs. hardening-gated (Symptome, Krankheit — erst nach ELYO-91) vs. Freitext `note` (ELYO-109: Minimierung offen) und den Freitext als offene Produktentscheidung an den DSB adressieren (→ 5.3). Symptom-/Krankheitserfassung als „geplant, aber entscheidungsoffen" führen (Gap-Analyse Open Question 2).
- **ADR-relevant:** JA

## 4.3 Laborwerte-Feldkatalog

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Die aus dem Demo-Material ableitbaren geplanten Kategorien: Marker-Key/-Name (klinisch benennend, z. B. Hämoglobin, CRP, Ferritin), Wert + Einheit, Orientierungsbereich, Soft-Status, Messdatum/Historie (geplant), Provenienz (offen: manuelle Eingabe / Dokumentimport / BGM-Import — Gap-Analyse Open Question 1). Explizit vermerken: finale Feldliste entsteht in ELYO-105/113/114; die Vorprüfung ist bei Modellfestlegung zu aktualisieren.
- **ADR-relevant:** JA

---

# 5. Offene Fragen und Übergabe

## 5.1 Empfänger der Übergabe

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Übergabe an die interne Kontrollperson (18.1) als Erstempfänger; Dokument so strukturieren, dass es unverändert an einen externen DSB/Berater weitergegeben werden kann. Empfängerklärung explizit als Entscheidung festhalten.
- **ADR-relevant:** JA

## 5.2 Übergabeform

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Repo-Datei ist die Quelle der Wahrheit; Übergabe = Jira-Kommentar mit Verweis + Stand (Commit) an ELYO-101, optional PDF-Export für Externe. Übergabevermerk (Datum, Empfänger, Stand) im Dokument selbst.
- **ADR-relevant:** NEIN (handwerklich), Vermerk im ADR unter Konsequenzen

## 5.3 Katalog offener Fragen an DSB/extern

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort (Startliste, erweiterbar während der Dokumenterstellung):**
  1. Schreibpfad(e) Laborwerte: manuelle Eingabe / Dokumentimport / Import aus Vorsorgeuntersuchung? (steuert Provenienz/Historie, Gap OQ 1)
  2. Strukturierte Symptom-/Krankheitserfassung im MVP — vereinbar mit „keine medizinische Interpretation"? (Gap OQ 2)
  3. Check-in-Freitext: zulässig mit Minimierung, oder streichen? (ELYO-109)
  4. Rechtsgrundlage der Verarbeitung im Beschäftigungskontext (Einwilligung/Freiwilligkeit) — reine DSB-Frage, technisch: Consent-Modell aus ADR-001 §2.6 beschreiben.
  5. DSFA-Pflicht-Bestätigung und Timing-Risiko (18.1) — bewusst getragenes Restrisiko bestätigen lassen.
  6. Aufbewahrungsfristen je Datenkategorie (technisch vorbereitet: Löschkonzept ADR-001 §2.8; fachliche Fristen offen).
  7. Bestehende Bestände (Dokumente-Upload, Anamnese, Wellbeing) — Behandlung in der DSFA (aus 1.1).
- **ADR-relevant:** JA

## 5.4 Blocker-Steuerung für ELYO-91

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** ELYO-91-Design-/Bauarbeiten laufen unabhängig weiter; **produktive Persistenz realer Gesundheitsdaten** bleibt geblockt, bis (a) die als Blocker klassifizierten Punkte nachweislich umgesetzt sind und (b) die Datenschutz-Verantwortliche die Vorprüfung ohne Veto zur Kenntnis genommen hat. Veto-Mechanik: Einwände der Kontrollperson erzeugen neue Blocker-Einträge.
- **ADR-relevant:** JA

---

# 6. Validierung

## 6.1 Vollständigkeitsprüfung gegen die Gap-Analyse

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Mapping-Tabelle im Anhang: jedes Finding 1–13 des Health Data Separation Review → Abschnitt im Vorprüfungsdokument. Prüfkriterium: kein Finding ohne Referenz.
- **ADR-relevant:** NEIN (handwerklich, aber Akzeptanzkriterium)

## 6.2 Review-Prozess

- **Status:** DECIDED (2026-07-12)
- **Entscheidung / Antwort:** Review durch die Datenschutz-Verantwortliche (5.1) analog Abnahmeprotokoll 18.2: versionierter Vermerk mit Datum, Beteiligten, offenen Bedingungen mit Frist. Re-Review-Trigger: Festlegung Laborwertmodell (ELYO-105/113), Check-in-Contract (ELYO-133), jede neue Datenkategorie.
- **ADR-relevant:** JA

---

# Abschluss

Nach Entscheidung aller Punkte:

1. **Datenfluss-Dokument** `docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md` erstellen (Akzeptanzkriterium 1–2).
2. **ADR-002** „DSFA-Vorprüfung: Scope, Methodik und Blocker-Steuerung" als Abnahme-Element (analog ADR-001).
3. Übergabe gemäß 5.1/5.2 (Akzeptanzkriterium 3).
