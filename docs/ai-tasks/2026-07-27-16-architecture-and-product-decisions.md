# Paket 16: Architektur- und Produktentscheidungen dokumentieren

**Priorität:** 4 (aber **vor Paket 15**) · **Bereich:** Dokumentation · **Etappen:** 4
**Befunde:** J1, J2, J12, U1–U14

```ai-run
complexity:        hoch
implement_tier:    high
implement_effort:  high
review_tier:       high
review_effort:     medium
blocked_by:        -
depends_on:        -
```

## Goal

Die 14 offenen Fragen beantworten, die nicht aus dem Code ableitbar sind — sie blockieren Etappen
in acht anderen Paketen.

**Kein Produktionscode wird geändert.** Ergebnis sind Entscheidungen als Repository-Dokumente.

## Context

Die Dokumentation hat 14 Punkte identifiziert, die aus dem Code **nicht** eindeutig ableitbar sind.
Sie sind keine Fehler, sondern unbeantwortete Fragen — und mehrere blockieren konkrete Umsetzungen.

Dazu drei Strukturbefunde, die ebenfalls Entscheidungen und keine Bugs sind:

- **J1 — uneinheitliche Schichtdisziplin:** Manche Controller nutzen Services, andere sprechen
  direkt mit Eloquent (`Employee\SurveyController`, `AdminCompanyController`, `TeamController`),
  `MeasureController` trägt rund 130 Zeilen Fachlogik in privaten Methoden.
- **J2 — kein Repository-Pattern, keine DTOs:** Services sprechen direkt mit Eloquent;
  Servicegrenzen verwenden typisierte Arrays mit PHPDoc-Shapes.
- **J12 — gesundheitsnahe Daten in der Identity-Domäne:**
  `user_system_measure_exercise_completions` enthält `pain_before_rating`, `pain_after_rating`,
  `stress_before_rating`, `stress_after_rating` und `feedback_text` — auf `user_id` in
  `elyo_identity`. Der Migrationskommentar begründet das mit dem fehlenden Reporting-Endpunkt und
  verlangt für künftige Aggregation Schwellen und Suppression.

## Umsetzung in Etappen

### Etappe 1 — Sicherheits- und Datenschutzentscheidungen (U5, U9, U11, U13)

| ID | Frage | Blockiert |
|---|---|---|
| **U5** | Nach welchem Kriterium sind Anamnesefelder verschlüsselt? Heute sechs Felder mit `encrypted`-Cast, `birth_year` und `chronic_patterns` ohne. | Paket 02, Etappe 6 |
| **U9** | Darf `elyo:enforce-retention --execute` `PROPOSED`-Kategorien löschen? Zehn von zwölf Fristen sind `PROPOSED`; der Status ist heute reine Anzeige, keine Sperre. | Paket 05, Etappe 5 |
| **U11** | Wo liegt der 30-Tage-Karenzworkflow der Kontolöschung? `AccountDeletionService` ist implementiert und getestet, aber ohne Aufrufer. | Paket 05, Etappe 4 |
| **U13** | Ist die Zeitzonenabhängigkeit des `period_key` bewusst? Er ist `Carbon::now()->toDateString()` in Serverzeitzone; die Streak-Berechnung ignoriert Feiertage. | Paket 13, Etappe 6 |

- **Achtung bei U5:** Eine Ausweitung der Verschlüsselung hat Betriebsfolgen — verschlüsselte
  Felder sind nicht durchsuchbar, filterbar oder aggregierbar, und eine `APP_KEY`-Rotation macht
  sie unlesbar.
- **Achtung bei U9:** Der auskommentierte Scheduler in `routes/console.php` nennt die
  Aktivierungsbedingungen bereits: alle Fristen `DECIDED` **und** eine dedizierte Wartungs-Runtime.
  Die Entscheidung sollte darauf aufbauen.
- **Abnahme:** Vier Entscheidungen als ADR oder als Ergänzung zu ADR-003, mit Begründung.

### Etappe 2 — Berechtigungs- und Rollenentscheidungen (U1, U2, U3, U4)

| ID | Frage | Blockiert |
|---|---|---|
| **U1** | Ist der `partner`-Portalzweig beabsichtigt? `User::canUsePortal('partner')` prüft die Rolle `PARTNER`, die **kein Codepfad vergibt**. | Paket 06, Etappe 6 |
| **U2** | Soll `COMPANY_OWNER` von der Umfrageerstellung ausgeschlossen sein? `CreateSurveyRequest` lässt nur `COMPANY_ADMIN` und `COMPANY_MANAGER` zu. | Paket 11, Etappe 1 |
| **U3** | Soll `ELYO_SUPPORT` von der Partnerfreigabe ausgeschlossen sein? `AdminPartnerActionRequest` verlangt nur `ELYO_ADMIN`, die Route lässt auch Support zu. | Paket 06, Etappe 5 |
| **U4** | Darf ein Manager mehrere Teams verwalten? Der Code widerspricht sich: zwei Controller nehmen genau eins an, zwei andere eine Liste. | Paket 12, Etappe 3 |

- **U2 und U3 sind vermutlich Versehen** — in beiden Fällen ist die Request-Autorisierung enger
  als die Route, ohne Kommentar. Die Entscheidung sollte das ausdrücklich benennen.
- **U4 ist die folgenreichste:** Sie bestimmt das Verhalten in vier Controllern und beeinflusst
  Pakete 11, 12, 13 und 14.
- **Abnahme:** Vier Entscheidungen mit Begründung; bei „Versehen" ausdrücklich als solches benannt.

### Etappe 3 — Produkt- und Betriebsentscheidungen (U6, U7, U8, U10, U12, U14, E7)

| ID | Frage | Blockiert |
|---|---|---|
| **U6** | Was bedeutet `partners.minimum_level`? String-Spalte mit Default `'STARTER'`, validiert als `integer|min:0`, nirgends ausgewertet. Vermutliche Kopplung an `user_points.level`, das ebenfalls nie geschrieben wird. | Paket 06, Etappe 7 · Paket 13, Etappe 5 |
| **U7** | Ist SSR produktiv vorgesehen? Vollständig konfiguriert, nie gestartet, und **sofort fehlerhaft** wegen `localStorage` und `window.location`. | Paket 03, Etappe 1 |
| **U8** | Welches Doku-Verzeichnis ist maßgeblich — `docs/further-docs/` oder `docs/further_docs/`? | Paket 04, Etappe 6 |
| **U10** | Bleiben die 16 verwaisten ENV-Variablen als Platzhalter erhalten? | Paket 15, Etappe 7 |
| **U12** | Warum ignoriert `InviteAcceptanceService::accept()` bei bestehendem Nutzer Name und Passwort? | Paket 07, Etappe 5 |
| **U14** | Gilt `PartnerSession` (Cookie, laut OpenAPI) oder das implementierte Bearer-Verfahren? | Paket 06, Etappe 4 |
| **E7** | Nur deutsch oder mehrsprachig? Die Oberfläche ist durchgängig deutsch hartcodiert, `APP_LOCALE` steht auf `en`, es gibt keine `lang/`-Dateien und keine i18n im Frontend. | Paket 07, Etappe 1 · Paket 08 |

- **U12 ist gekoppelt an die Passwort-Reset-Entscheidung** (Paket 01, Etappe 6): Wenn es keine
  Zurücksetzung gibt, ist eine neue Einladung der einzige Weg — und der funktioniert wegen dieses
  Verhaltens nicht.
- **Abnahme:** Sieben Entscheidungen mit Begründung.

### Etappe 4 — Strukturentscheidungen (J1, J2, J12, A6)

- **J1 — Schichtkonvention:** Festlegen, wann Fachlogik in einen Service gehört und wann sie im
  Controller bleiben darf. Die bestehende Uneinheitlichkeit ist nicht per se falsch, aber
  undokumentiert. Ergebnis gehört nach `docs/ai-context/` zu den bestehenden Kontextdokumenten.
- **J2 — Repository-Pattern und DTOs:** Der Verzicht ist vertretbar und konsistent. Als bewusste
  Entscheidung festhalten, damit sie nicht in jedem Review neu diskutiert wird.
  **Achtung:** Paket 15, Etappe 8 (J4) will dynamische Modellattribute durch DTOs ersetzen — die
  Entscheidung hier setzt den Rahmen dafür.
- **J12 — Domänenzuordnung der Completion-Daten:** Gehören Schmerz- und Stressbewertungen in die
  Health-Domäne? Nach ADR-001 spricht viel dafür. Die Entscheidung bestimmt, ob Paket 14, Etappe 6
  die Zuweisungsdomäne anbinden kann oder erst eine Migration in die Health-Domäne braucht.
- **A6 — Tokenablage im Frontend:** Aus Paket 01 hierher übergeben. Der Wechsel von `localStorage`
  auf ein `HttpOnly`-Cookie wäre ein Architekturwechsel: Sanctum im SPA-Modus mit CSRF-Schutz,
  `authInterceptor` entfiele, und die Cookie-Konfiguration hinge an der Deployment-Topologie
  (Paket 03). Entscheidung mit Aufwandsabschätzung.
- **Abnahme:** Vier Entscheidungen als ADR oder als Kontextdokument unter `docs/ai-context/`.

## Out of Scope

- **Jede Änderung an Produktionscode.** Dieses Paket produziert ausschließlich Dokumente.
- Umsetzung der Entscheidungen — die erfolgt in den jeweiligen Paketen

## Hard constraints

- **Kein Produktionscode wird geändert** — kein Laravel, kein Angular, keine Migration, kein
  Seeder, kein Test, kein Docker
- Bestehende ADRs werden **nicht** überschrieben, sondern ergänzt oder abgelöst — mit Verweis
- Bestehende dokumentierte Entscheidungen sind zu respektieren, insbesondere
  `docs/ai-tasks/2026-06-02-13-clarify-team-breakdown-contract.md` (`teamBreakdown` bleibt
  nullbares Zukunftsfeld) und ADR-003 D1–D10
- Jede Entscheidung nennt die blockierten Etappen, damit die Freigabe nachvollziehbar ist

## Review-Checkliste

- [ ] Alle 14 U-Fragen sind beantwortet, keine offen gelassen
- [ ] Jede Entscheidung nennt die Begründung, nicht nur das Ergebnis
- [ ] Jede Entscheidung nennt die blockierten Etappen
- [ ] Vermutliche Versehen (U2, U3) sind ausdrücklich als solche benannt
- [ ] Bestehende ADRs und dokumentierte Entscheidungen wurden respektiert
- [ ] J1, J2, J12 und A6 sind entschieden
- [ ] **Kein Produktionscode wurde geändert** — Diff enthält nur Dokumente
- [ ] Kapitel 14 und 15 der Confluence-Dokumentation aktualisiert

## Expected output

- Neue oder ergänzte ADR- bzw. Kontextdokumente
- Tabelle: Frage → Entscheidung → Begründung → freigegebene Etappen
- Bestätigung, dass kein Produktionscode geändert wurde
- Liste der Pakete, die nach diesem Paket startklar sind
