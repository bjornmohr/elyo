# ADR-002: DSFA-Vorprüfung — Scope, Methodik und Blocker-Steuerung (ELYO-101)

| Feld | Wert |
|---|---|
| **Status** | Zur Abnahme (Review durch Datenschutz-Verantwortliche ausstehend) |
| **Datum** | 2026-07-12 |
| **Entscheider** | Tech-Lead |
| **Abnahme** | Interne Kontrollperson (ELYO-100 / 18.1) |
| **Grundlage** | ELYO-101-DSFA-Vorprüfung-Entscheidungsbogen (alle Punkte DECIDED, Stand 2026-07-12); ADR-001; Gap-Analyse `docs/ai-reviews/demo-employee-lab-values-dashboard-gap-analysis.md` |

---

## 1. Kontext

Elyo plant die produktive Verarbeitung von Laborwerten und Check-in-Daten von Beschäftigten. Nach ELYO-100 / 18.1 wird die Datenschutz-Folgenabschätzung parallel zum Pilot mit höchster Priorität erstellt; das rechtliche Restrisiko (Art. 35 DSGVO regelmäßig vor Verarbeitungsbeginn) ist dokumentiert und wird bewusst getragen. ELYO-101 liefert die technische Zuarbeit dafür: Datenkategorien, Zwecke, Speicherorte, Zugriffe und Risiken — als Faktensammlung, ausdrücklich keine Rechtsberatung. Der Demo-Branch `demo/employee-lab-values-dashboard` dient dabei ausschließlich als Anschauungsmaterial für die geplanten Datenkategorien und als Risiko-Inventar; das Demo-Schema wird nicht fortgeführt (ADR-001, 0.6).

Diese ADR legt fest, *wie* diese Zuarbeit erstellt, klassifiziert, validiert und übergeben wird — und wie ihr Ergebnis den Blocker-Status der ELYO-91-Arbeiten steuert.

## 2. Entscheidung

### 2.1 Scope: Kern plus Inventar-Anhang

Volle Datenflussanalyse nur für die geplante produktive Laborwerte- und Check-in-Verarbeitung (inkl. Historie/Timeline als Lesepfad). Bereits in `main` existierende Health-Bestände (Wellbeing-Entries, Dokumenten-Upload, Anamnese-Profil, Survey-Antworten) werden als Inventar-Anhang mit Kurzeinstufung geführt und mit expliziter Empfehlung zur Behandlung in der DSFA übergeben. (1.1)

### 2.2 Zwei Ebenen: Ziel-Datenflüsse und Demo-Risiko-Inventar

Der Hauptteil dokumentiert die Ziel-Datenflüsse gegen die ADR-001-Architektur (vier DBs, Mapping-Domäne, fünf Runtimes, Aggregat-Reporting). Der Demo-Ist-Zustand wird nicht als Datenfluss analysiert, sondern als Risiko-Inventar mit Dateireferenzen geführt — vollständige Artefaktliste aus dem Health Data Separation Review: `lab_markers` (Tabelle/Model/Controller/Seeder), Check-in-`note`-Freitext, localStorage-Check-in (Symptome/Krankheit), `employee-dashboard.json` (Body Signals/Health Flag), LAB-Badge-Kategorie, Infection-Radar-Aggregate, `LabValueDemoSeeder`. Es gibt keinen Migrationspfad Demo→Prod. (1.2, 1.3, 0.2)

### 2.3 Risikoklassifikation mit zwei Spalten

Taxonomie Low/Medium/High/Blocker mit expliziten Definitionen: **Blocker** = verhindert produktive Verarbeitung realer Daten, bis behoben; **High** = Re-Identifikation oder unbefugter Health-Zugriff realistisch möglich; **Medium** = Schutzverletzung nur unter Zusatzbedingungen bzw. begrenzte Sensitivität; **Low** = kein personenbezogenes Risiko oder rein demo-gated. Jedes Finding trägt zwei Einstufungen: „Demo-Ist" (unverändert aus der Gap-Analyse) und „Restrisiko Zielarchitektur (geplant)" mit Verweis auf die adressierende ADR-001-Entscheidung und den Umsetzungsstatus (ELYO-91-Tickets). Blocker bleiben Blocker, bis die Umsetzung durch die Boundary-Tests (ADR-001 §2.10) nachgewiesen ist. Dateireferenzen: repo-relativer Pfad plus Klassen-/Migrationsname, Branch-Angabe wenn nicht `main`, keine Zeilennummern. (3.1–3.3)

### 2.4 Datenkategorien: deskriptiv statt juristisch

Jede Datenkategorie erhält eine rein deskriptive Markierung „Gesundheitsdatenbezug: direkt / abgeleitet / kein" (Laborwerte, Symptome, Krankheit = direkt; mood/energy/stress, Schonmodus, LAB-Badges, Maßnahmen-Teilnahme = abgeleitet/gesundheitsnah; Standort/Login = kein). Die rechtliche Art.-9-Subsumtion bleibt bei den Datenschutz-Verantwortlichen. Check-in-Felder werden in drei Klassen geführt: sofort persistierbar (mood/energy/stress 1–5, Location, ggf. Sleep), hardening-gated (Symptome/Krankheit — erst nach ELYO-91 und Produktentscheidung), entscheidungsoffen (Freitext `note`, ELYO-109). Laborwert-Kategorien werden aus dem Demo-Material abgeleitet (Marker-Key/-Name, Wert + Einheit, Orientierungsbereich, Soft-Status, Messdatum/Historie, Provenienz offen) — mit Vermerk, dass die finale Feldliste in ELYO-105/113/114 entsteht und die Vorprüfung dann aktualisiert wird. (4.1–4.3)

### 2.5 Dokument, Ablage, Notation

Versionierte Markdown-Datei `docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md` (neuer Privacy-Ordner als wachsender Ort für DSFA, Löschkonzept, VVT-Zuarbeit). Pro Verarbeitung ein Mermaid-Flussdiagramm (Quelle → Runtime → DB/Storage → Empfänger) plus Strukturtabelle (Datenkategorie, Felder, Zweck, Speicherort, Zugriff, Aufbewahrung/Löschung, Risiko). Gliederung: Systemkontext, Verarbeitungen & Zwecke, Datenkategorien, Datenflüsse, Speicherorte & Aufbewahrung, Zugriffe & Empfänger, Demo-Risiko-Inventar, Risikoklassifikation Zielarchitektur, offene Fragen, Übergabevermerk. (2.1–2.3)

### 2.6 Übergabe und offene Fragen

Übergabe an die interne Kontrollperson (18.1) als Erstempfänger; das Dokument ist so strukturiert, dass es unverändert an einen externen DSB/Berater weitergegeben werden kann. Übergabeform: Repo-Datei als Quelle der Wahrheit, Jira-Kommentar an ELYO-101 mit Verweis und Commit-Stand, Übergabevermerk im Dokument; PDF-Export nur bei Bedarf. Der Übergabekatalog enthält sieben Startfragen: Schreibpfade Laborwerte (Provenienz/Historie), strukturierte Symptom-/Krankheitserfassung vs. „keine medizinische Interpretation", Check-in-Freitext (ELYO-109), Rechtsgrundlage im Beschäftigungskontext, Bestätigung des DSFA-Timing-Restrisikos (18.1), Aufbewahrungsfristen je Datenkategorie, Behandlung der Altbestände. (5.1–5.3)

### 2.7 Blocker-Steuerung für ELYO-91

Design- und Bauarbeiten in ELYO-91 laufen unabhängig weiter. Die produktive Persistenz realer Gesundheitsdaten bleibt geblockt, bis kumulativ: (a) alle als Blocker klassifizierten Punkte nachweislich umgesetzt sind (Boundary-Tests) und (b) die Datenschutz-Verantwortliche die Vorprüfung ohne Veto zur Kenntnis genommen hat. Einwände erzeugen neue Blocker-Einträge im Risiko-Inventar. (5.4)

### 2.8 Validierung und Re-Review

Vollständigkeitsnachweis als Mapping-Tabelle im Anhang: jedes der 13 Findings des Health Data Separation Review verweist auf den behandelnden Abschnitt; Prüfkriterium ist „kein Finding ohne Referenz". Review durch die Datenschutz-Verantwortliche analog Abnahmeprotokoll (18.2): versionierter Vermerk mit Datum, Beteiligten und terminierten offenen Bedingungen. Re-Review-Trigger: Festlegung des Laborwertmodells (ELYO-105/113), Check-in-Contract (ELYO-133), jede neue Datenkategorie. (6.1–6.2)

## 3. Konsequenzen

**Positiv:**

- Die DSFA erhält eine anschlussfähige technische Faktenbasis (Kategorien, Flüsse, Speicherorte, Zugriffe, Fristen-Platzhalter), ohne dass die Vorprüfung juristische Wertungen vorwegnimmt.
- Die Zwei-Spalten-Klassifikation zeigt ehrlich, was die Zielarchitektur löst und was noch ungebaut ist — Blocker verschwinden erst mit Testnachweis, nicht mit Beschlusslage.
- Das Doppelkriterium (technischer Nachweis + Kenntnisnahme ohne Veto) gibt der Übergabe reale Steuerungswirkung auf ELYO-91, ohne Design-Arbeit zu blockieren.
- Der Inventar-Anhang verhindert, dass die DSFA später an unerfassten Altbeständen (Dokumente, Anamnese, Wellbeing, Surveys) scheitert.

**Negativ / bewusst akzeptiert:**

- Die Laborwert-Datenkategorien basieren auf Demo-Ableitung; das Dokument muss bei Festlegung des produktiven Modells (ELYO-105/113/114) nachgezogen werden — bewusst in Kauf genommen, um die Sprint-1-Zuarbeit nicht zu blockieren.
- Das DSFA-Timing-Restrisiko aus 18.1 bleibt bestehen; diese Vorprüfung mildert es, hebt es aber nicht auf.
- Kein hartes schriftliches Freigabe-Gate: Kenntnisnahme ohne Veto ist bewusst schwächer als eine formale Freigabe — konsistent mit 18.1, als Abweichungsrisiko dokumentiert.
- Altbestände erhalten nur eine Kurzeinstufung, keine volle Datenflussanalyse; die Tiefenprüfung wird an die DSFA delegiert.

**Bewusst vertagt (nicht Teil dieser ADR):**

- Produktentscheidung zur strukturierten Symptom-/Krankheitserfassung (Gap OQ 2), Freitext-Minimierung (ELYO-109), fachliche Aufbewahrungsfristen, finale Laborwert-Feldliste (ELYO-105/113/114), Behandlung der Altbestände in der DSFA.

## 4. Betrachtete Alternativen

- **Strikt auf Ticket-Scope begrenzen (nur Laborwerte + Check-in):** verworfen — die DSFA braucht das vollständige Bild der Health-Bestände; Nacharbeit wäre absehbar (1.1).
- **Demo-Ist als eigene Datenflussanalyse:** verworfen — Demo-Flüsse haben keinen DSFA-Wert, da das Schema nicht fortgeführt wird; Inventar mit Dateireferenzen genügt (1.3).
- **Frische Risikobewertung nur gegen die Zielarchitektur:** verworfen — würde suggerieren, Blocker seien gelöst, obwohl ELYO-91 aussteht (3.1).
- **Vollständige Art.-9-Einordnung durch die Technik:** verworfen — überschreitet die „keine Rechtsberatung"-Grenze des Tickets (4.1).
- **Hartes schriftliches Freigabe-Gate für ELYO-91:** verworfen — Verschärfung gegenüber 18.1, potenzielle Verzögerung ohne beschlossene Anforderung (5.4).

## 5. Referenzen

- ELYO-101-DSFA-Vorprüfung-Entscheidungsbogen (Einzelentscheidungen 0.1–6.2 mit Begründungen)
- ADR-001 (Zielarchitektur; §2.6 Consent, §2.8 Löschung, §2.10 Boundary-Tests)
- Gap-Analyse inkl. Health Data Separation Review, Findings 1–13 (`docs/ai-reviews/demo-employee-lab-values-dashboard-gap-analysis.md`)
- Folgeartefakt: `docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md` (Akzeptanzkriterien 1–2 des Tickets)
- Folgeaufgaben: Übergabe per Jira-Kommentar an ELYO-101 (Akzeptanzkriterium 3), Aktualisierung nach ELYO-105/113/114 und ELYO-133
