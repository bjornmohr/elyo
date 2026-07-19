# ELYO-99 - Scope-Entscheidungen je Demo-Feature

> **Source of Truth.** Diese Seite ist massgeblich. JIRA-Subtasks (ELYO-149..158) tragen nur Kurzfassung + Status und verlinken hierher. Git-MD in `docs/` ist Export/Vorlage.

**Grundsatz:** Demo-Code ist Referenz, keine Produktionsbasis. Grundlage: `docs/ai-reviews/demo-employee-lab-values-dashboard-gap-analysis.md`.

**Reviewer:** je Feature unten per **Inline-Kommentar** zustimmen/widersprechen, Screenshots direkt in den Abschnitt einfuegen.

**Abstimmung:** Product Owner + Privacy-Verantwortliche (manuelles Review, keine automatisierte Validierung).


## Uebersicht

| # | Feature | JIRA | Draft-Entscheidung | Klassifikation | Prio |
|---|---|---|---|---|---|
| 1 | Employee Laborwerte-Dashboard | ELYO-149 | Produktiver Neuaufbau. Demo = reine UX-/Scope-Referenz. | produktiver Neuaufbau + Backend-Contract | Health-Data-Hardening | Privacy-Review | Höchste |
| 2 | Laborwert-Marker-Erklaerungen | ELYO-150 | Produktiver Neuaufbau als versionierter Backend-Katalog. Demo-Texte = Content-Kandidaten. | produktiver Neuaufbau + Backend-Contract | Privacy-Review (Wording-Grenze) | Mittel |
| 3 | Lab-verknuepfte Routine-/Massnahmen-Karten | ELYO-151 | Karten demo-only, aus Produktionspfad ausgeschlossen. Regelbasierte Verknuepfung = neues Design (Later). | UI-only / demo-only | bei Beauftragung: Neuaufbau (Later) + Privacy-Review | Mittel |
| 4 | Employee-Dashboard-Bloecke & Schonmodus | ELYO-152 | Produktiver Neuaufbau pro Block (Produktivquelle / entfaellt / demo-only). Schonmodus-Regelwerk neu. | produktiver Neuaufbau + Backend-Contract | Health-Data-Hardening | Privacy-Review | Hoch |
| 5 | Wellbeing-Skala 1-5 | ELYO-153 | Produktive Einfuehrung der 1-5-Skala ueber eigenstaendig gereviewte Migration. Demo = Mapping-Referenz. | produktiver Neuaufbau (leicht) | kein Privacy-Blocker | Niedrig |
| 6 | Adaptiver Check-in (Stepper + Chat) | ELYO-154 | Produktiver Neuaufbau, API-first auf der Health-Domaene. Demo-localStorage ausgeschlossen. | produktiver Neuaufbau + Backend-Contract | Health-Data-Hardening | Privacy-Review | Höchste |
| 7 | Check-in History-Timeline | ELYO-155 | Produktiver Neuaufbau auf finalen Backend-Contracts. Kein Demo-Blending im Produktionscode. | produktiver Neuaufbau + Backend-Contract | Privacy-Review (Gesundheitsdetails clientseitig) | Mittel |
| 8 | Badges / Gamification | ELYO-156 | Demo-only, flag-gated. Produktentscheidung ausstehend (needs-decision). | UI-only / demo-only | needs-decision | Privacy-Review (LAB-Badge) | Niedrig |
| 9 | Employee Measures Hub | ELYO-157 | Produktiv (naechster am Produkt). Verifikation gegen finalen Contract + Autorisierungsmatrix; Route-Semantik bewusst entscheiden. | produktiver Neuaufbau + Backend-Contract (Verifikation) | niedrig-mittlerer Privacy-Impact | Mittel |
| 10 | Company Insights Suite (inkl. Infection Radar = BLOCKIERT) | ELYO-158 | Scope-Entscheidung PRO MODUL. Infection Radar = BLOCKIERT (raus aus MVP). Andere Module: Neuaufbau nur mit Suppression-Spezifikation. | produktiver Neuaufbau (pro Modul) + Backend-Contract | BLOCKIERT (Infection Radar) | Privacy-Review | Hoch |

**Klassifikations-Legende:** `demo-only/UI-only` = reine Referenz, kein Produktivbau · `produktiver Neuaufbau + Backend-Contract` = aus Zielarchitektur neu, Demo nur UX-Referenz · `Health-Data-Hardening` = health_subject_id/Pseudonymisierung/Audit noetig · `Privacy-Review` = Datenschutz-Freigabe noetig · `blockiert` = raus aus MVP mit Begruendung.


---

## 1. Employee Laborwerte-Dashboard  (ELYO-149)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Produktiver Neuaufbau. Demo = reine UX-/Scope-Referenz.

**Klassifikation:** produktiver Neuaufbau + Backend-Contract | Health-Data-Hardening | Privacy-Review  ·  **Prio:** Höchste


**Kontext (Demo-Beobachtung):** Employee-Seite 'Laborwerte & Marker' (/employee/lab-markers): auffaellige Marker zuerst, gruppierte Karten (Blutbild, Immun-/Entzuendungssignale, Mikronaehrstoffe), Wert + Soft-Status vs. Orientierungsbereich, Privacy-Banner, Erklaerungs-Popover. Backend: LabMarkerController liest nur eigene labMarkers; Demo-Datenmodell mit direktem user_id, ein Wert je Marker, kein Messzeitpunkt; nur Seed-Daten. Demo-Privacy-Tests gruen (Company/Admin 403/404).


**Begruendung:** Demo speichert Gesundheitswerte mit direktem user_id-FK, ohne Pseudonymisierung/health_subject_id/Audit. Blocker fuer echte Daten bis Health-Domaene (ELYO-91).


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Validierte User Journey, Gruppierungs-/Highlight-Hierarchie, Privacy-Banner-Platzierung, Soft-Status-Labels, Erklaerungs-Interaktion, Feldset (Name, Einheit, Range, Gruppe, Status).


**Produktions-Target:** Neues Laborwert-Modell in der Health-Domaene (health_subject_id, historienfaehig), finaler OpenAPI-Contract API-first, neue Employee-UI gegen den Contract. Ausgeschlossen: Demo-Tabelle, -Model, -Controller, -Seeds, -Registry, hardcodierte UI-Inhalte.


**Referenz:** Gap-Analyse §1; Epics ELYO-91/92/93; Feature ELYO-119.


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 2. Laborwert-Marker-Erklaerungen  (ELYO-150)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Produktiver Neuaufbau als versionierter Backend-Katalog. Demo-Texte = Content-Kandidaten.

**Klassifikation:** produktiver Neuaufbau + Backend-Contract | Privacy-Review (Wording-Grenze)  ·  **Prio:** Mittel


**Kontext (Demo-Beobachtung):** Hover-Popover je Marker ('Was beschreibt ...?', Kontextnotiz, Sicherheitshinweis 'ersetzt keine aerztliche Einordnung'), Inhalt hardcodiert in lab-marker-catalog.ts. Der Demo-TODO benennt selbst die Zielrichtung: versionierter Backend-Katalog mit fachlicher Freigabe.


**Begruendung:** Inhalt kein Personenbezug; Risiko ist Wording, das in medizinische Interpretation abdriftet. Freigabeprozess noetig.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Informationsstruktur (Titel, Kurzerklaerung, Kontextnotiz, Sicherheitshinweis) und nicht-diagnostischer Ton; die 12 Texte als Kandidaten-Content.


**Produktions-Target:** Neues versioniertes Backend-Content-Modell mit Quell-Metadaten und Freigabe-Workflow; neue barrierefreie Erklaerungskomponente (Klick, optional Hover, Touch-Sheet, Keyboard). Produktives Frontend bezieht Inhalte ausschliesslich aus dem Backend-Katalog. Nichts hardcodiert.


**Referenz:** Gap-Analyse §2; Epic ELYO-94.


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 3. Lab-verknuepfte Routine-/Massnahmen-Karten  (ELYO-151)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Karten demo-only, aus Produktionspfad ausgeschlossen. Regelbasierte Verknuepfung = neues Design (Later).

**Klassifikation:** UI-only / demo-only | bei Beauftragung: Neuaufbau (Later) + Privacy-Review  ·  **Prio:** Mittel


**Kontext (Demo-Beobachtung):** Statische 'Fokus-Routinen' und Massnahmen-Vorschlagskarten unter den Laborwerten, thematisch Marker->Massnahme implizierend (z.B. Vitamin-D-Routine) - hardcodierte Arrays (FOCUS_ROUTINES, MEASURE_CARDS), keine Logik, keine Personalisierung.


**Begruendung:** Empfehlung aus Markerstatus ist abgeleitete Gesundheitsdatum (High). Hardcodierte Pseudo-Empfehlungen nicht als Produktlogik missverstehen.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Illustriert die Produktidee (praeventive Routinen nahe der Laborwerte) - nicht mehr.


**Produktions-Target:** Statische Karten aus dem Produktionspfad ausschliessen. Falls Verknuepfung beauftragt: von Grund auf als regelbasiertes, praeventives, nicht-diagnostisches Feature in der Health-Domaene.


**Referenz:** Gap-Analyse §3; Epic ELYO-95 (ELYO-129 Later, ELYO-130 Sprint 4).


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 4. Employee-Dashboard-Bloecke & Schonmodus  (ELYO-152)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Produktiver Neuaufbau pro Block (Produktivquelle / entfaellt / demo-only). Schonmodus-Regelwerk neu.

**Klassifikation:** produktiver Neuaufbau + Backend-Contract | Health-Data-Hardening | Privacy-Review  ·  **Prio:** Hoch


**Kontext (Demo-Beobachtung):** Metrik-Kacheln (Stimmung/Energie/Stress, Woche-ueber-Woche), Wellbeing-Sparkline, Schlaf-Block, Body-Signals, Health-Flag, 'Schonmodus'-Banner (gated Levers). Wellbeing-Aggregate aus echten 1-5-Eintraegen (EmployeeDashboardService); Schlaf/BodySignals/HealthFlag/Levers aus Company-Demo-JSON mit Seed-Varianz, in prod null (UI null-safe).


**Begruendung:** Health-Flag und Body-Signals sind - sobald real - individuelle Gesundheitsdaten (Health-Domaene). Demo-JSON company-keyed & synthetisch: fuer Demo ok.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Block-Layout und -Prioritaeten; EmployeeDashboardService-Aggregatlogik als funktionale Spezifikation; resolveLevers als Referenz fuer 'nur eigene Massnahmen'.


**Produktions-Target:** Pro-Block-Entscheidung, produktiver Dashboard-Payload im finalen Contract, neu implementiert. Schonmodus als dokumentiertes, praeventives Regelwerk neu. Keine Produktionsabhaengigkeit von DemoEmployeeDashboardProvider/Demo-JSON.


**Referenz:** Gap-Analyse §4; Epic ELYO-96 (ELYO-117 Sprint 3, ELYO-136 Sprint 4).


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 5. Wellbeing-Skala 1-5  (ELYO-153)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Produktive Einfuehrung der 1-5-Skala ueber eigenstaendig gereviewte Migration. Demo = Mapping-Referenz.

**Klassifikation:** produktiver Neuaufbau (leicht) | kein Privacy-Blocker  ·  **Prio:** Niedrig


**Kontext (Demo-Beobachtung):** Kanonische 1-5-Skala mit Datenmigration bestehender Eintraege, CheckinRequest min:1 max:5, Factory angepasst. Main: 1-10-Validierung.


**Begruendung:** Bestehende Datenkategorie, niedriges Risiko. Mapping-Semantik auf produktionsaehnlichen Daten verifizieren, im Contract dokumentieren.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Mapping-Semantik 1-10 -> 1-5 als Referenz.


**Produktions-Target:** 1-5-Skala in Produktion via unabhaengig gereviewter Migration einfuehren; Contract dokumentiert die Skala; Aggregate verifiziert.


**Referenz:** Gap-Analyse §5; Epic ELYO-96 (ELYO-135).


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 6. Adaptiver Check-in (Stepper + Chat)  (ELYO-154)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Produktiver Neuaufbau, API-first auf der Health-Domaene. Demo-localStorage ausgeschlossen.

**Klassifikation:** produktiver Neuaufbau + Backend-Contract | Health-Data-Hardening | Privacy-Review  ·  **Prio:** Höchste


**Kontext (Demo-Beobachtung):** Zwei Varianten (Stepper 2a, Chat 2c); der gesamte Lauf nur in Browser-localStorage (elyo.demo.checkin.<date>) - inkl. Ort, Stimmung/Energie/Stress, Schlaf, Symptome mit Schmerzregionen/Schwere, Krankheitstypen - bewusst ohne API-Writes. Main: ein einfacher Check-in mit API-Write.


**Begruendung:** Gesundheitsdaten im localStorage (High). Strukturierte Symptom-/Krankheitserfassung braucht Produktentscheidung gegen 'keine medizinische Interpretation'.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Flow, Fragenlogik und adaptive Schritte als UX-Referenz.


**Produktions-Target:** Neuer produktiver Check-in API-first: Contract trennt sofort persistierbare Felder (Stimmung/Energie/Stress 1-5, Ort, Schlaf) von haertungs-gegateten Feldern (Symptome, Krankheit - erst nach ELYO-91). CheckinDemoStorageService/localStorage demo-only, aus Produktions-Import-Graph ausgeschlossen.


**Referenz:** Gap-Analyse §6; ELYO-133 (Sprint 3), ELYO-134 (Sprint 4), ELYO-109 (Sprint 2).


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 7. Check-in History-Timeline  (ELYO-155)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Produktiver Neuaufbau auf finalen Backend-Contracts. Kein Demo-Blending im Produktionscode.

**Klassifikation:** produktiver Neuaufbau + Backend-Contract | Privacy-Review (Gesundheitsdetails clientseitig)  ·  **Prio:** Mittel


**Kontext (Demo-Beobachtung):** Reiche Timeline (+585 Zeilen), die API-History mit lokalen Demo-Check-ins mischt. Main: einfache History.


**Begruendung:** Rendert Gesundheitsdetails clientseitig (Medium); Quelldaten folgen der Health-Domaene.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Timeline-UX und Informationsdichte als Referenz.


**Produktions-Target:** Neue produktive Timeline unabhaengig auf finalen Contracts: nur API-Daten, Empty-State fuer neue User, definierte Fehler-/Ladezustaende. Keine Demo-Storage-Imports im Produktionscode.


**Referenz:** Gap-Analyse §7; Epic ELYO-96 (ELYO-138).


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 8. Badges / Gamification  (ELYO-156)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Demo-only, flag-gated. Produktentscheidung ausstehend (needs-decision).

**Klassifikation:** UI-only / demo-only | needs-decision | Privacy-Review (LAB-Badge)  ·  **Prio:** Niedrig


**Kontext (Demo-Beobachtung):** Frontend-only EmployeeBadgesDemoService, Badge-Definitionen inkl. Kategorie LAB ('Labor') - Badge-Vergabesemantik koennte Markerstatus verraten. Kein Backend-Modell.


**Begruendung:** LAB-Badge kann Markerstatus indirekt offenlegen (Medium). Vor Produktion: Produktentscheidung + Privacy-Check.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Gamification-Konzept und Badge-Kategorien als Produkt-Ideenreferenz.


**Produktions-Target:** Bis zur Produktentscheidung demo-only, flag-gated. Bei Beauftragung: neues Backend-Modell + Privacy-Check (keine health-encodierenden Badges).


**Referenz:** Gap-Analyse §8; ELYO-137 (Later, needs-decision).


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 9. Employee Measures Hub  (ELYO-157)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Produktiv (naechster am Produkt). Verifikation gegen finalen Contract + Autorisierungsmatrix; Route-Semantik bewusst entscheiden.

**Klassifikation:** produktiver Neuaufbau + Backend-Contract (Verifikation) | niedrig-mittlerer Privacy-Impact  ·  **Prio:** Mittel


**Kontext (Demo-Beobachtung):** Zugewiesene System-Massnahmen (UserSystemMeasureController index/show), Massnahmen-Detail mit Steps/Piktogrammen, Uebungs-Player mit Countdown/Auto-Advance, MeasureExecutionService, Katalogfeld-Migration, 60+ Piktogramm-SVGs, Admin-Editing. Route-Semantik geaendert: /employee/measures = persoenliche System-Massnahmen; Company-Massnahmen nach /employee/company-measures. Autorisierungstests gruen.


**Begruendung:** Uebungs-Completion ist gesundheitsnahe Aktivitaetsdatum (Low-Medium). Route-Aenderung braucht Breaking-Change-Bewertung.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Verhalten und Feldset als starke Spezifikationsreferenz; das produktnaechste Teil des Branches.


**Produktions-Target:** Faehigkeit im finalen OpenAPI-Contract bestaetigen, Route-Semantik bewusst entscheiden, Autorisierungsmatrix mit Produktionstests validieren. Contract, nicht Demo, ist Source of Truth.


**Referenz:** Gap-Analyse §9; Epic ELYO-95 (ELYO-102/114/116/132).


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## 10. Company Insights Suite (inkl. Infection Radar = BLOCKIERT)  (ELYO-158)

**Scope-Entscheidung (DRAFT - bitte bestaetigen/aendern):** Scope-Entscheidung PRO MODUL. Infection Radar = BLOCKIERT (raus aus MVP). Andere Module: Neuaufbau nur mit Suppression-Spezifikation.

**Klassifikation:** produktiver Neuaufbau (pro Modul) + Backend-Contract | BLOCKIERT (Infection Radar) | Privacy-Review  ·  **Prio:** Hoch


**Kontext (Demo-Beobachtung):** Executive Summary, Risk Landscape, Usage Funnel, Infection Radar (Respiratory-Warnlogik), Measure-Impact-Dialog, Measure-Statistics. Architektur: Contract-Interfaces; Demo-Provider lesen Seed-JSON mit Company-Varianz; Prod-Bindings = Null-Provider ausser DbMeasureStatisticsProvider (anonymity_threshold, isAboveThreshold). Feature-Flags in prod aus; Angular-Routen via featureFlagGuard.


**Begruendung:** Infection Radar: gesundheitsnah/praediktive-Abwesenheit-Naehe, ausserhalb MVP; bleibt demo-flag-gated; Wiederbelebung = neues Design. Re-Identifikation bei realen Daten ohne Suppression = High.


**Demo-Referenzwert** (nur UX-/Scope-Referenz, keine Produktionsbasis)**:** Demo/prod-Gating-Mechanismus selbst; anonymity_threshold als Startinput fuer die Suppression-Spezifikation; Module als Konzept-Demonstrationen.


**Produktions-Target:** Pro-Modul-Entscheidung. Fuer freigegebene Module: eigenstaendig autorisierte Aggregations-/Suppression-Spezifikation (Metriken, Mindestgruppengroessen, Suppression-Anzeige), gegen die verifiziert wird. Demo/prod-Isolation systematisch verifiziert. Infection Radar formal blockiert.


**Referenz:** Gap-Analyse §10; Epic ELYO-97 (ELYO-139 blockiert, ELYO-140, ELYO-141, ELYO-103).


**Screenshots:** _(hier per Strg+V einfuegen)_


**Reviewer-Feedback** _(Inline-Kommentar oder hier ergaenzen)_**:**

- Reviewer 1 - Zustimmung? ja / nein / Anmerkung: 
- Reviewer 2 - Zustimmung? ja / nein / Anmerkung: 
- Finale Entscheidung (nach Abstimmung): 

---

## Sign-off

- [ ] Product Owner: 
- [ ] Privacy-Verantwortliche: 
