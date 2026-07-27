# ELYO-110: Wellbeing-Migration auf `health_subject_id`

## Status und Entscheidung

ELYO-110 war ursprünglich als Bewertung des Identity-seitigen
`wellbeing_entries`-Bestands vorgesehen. Die Umsetzungssession hat mit ADR-003
D3 entschieden, die Migration im frischen, noch nicht produktiven Schema direkt
umzusetzen: Check-ins liegen vollständig in `elyo_health`, sind ausschließlich
über `health_subject_id` zugeordnet, verwenden die Skala 1–5 und enthalten
keinen Freitext `note`.

Damit ist ELYO-110 **implementiert statt nur bewertet**. Es gab keine
Datenübernahme aus dem Demo-Schema.

## Umgesetzter Umfang

- Prompt 08 ersetzte das Identity-Modell durch
  `App\Models\Health\WellbeingEntry`, eine Health-Migration und
  subject-scoped Lese-/Schreibzugriffe über `MappingService`.
- `mood`, `energy` und `stress` sind erforderliche Ganzzahlen von 1 bis 5.
  `score` wird als Mittel aus Stimmung, invertiertem Stress und Energie auf
  derselben Skala berechnet.
- `note` wird nicht gespeichert, nicht ausgegeben und in
  `POST /employee/checkin` mit `422` abgelehnt.
- Ein Check-in pro Subject und Tag wird durch
  `(health_subject_id, period_key)` erzwungen; Client-IDs bleiben opaque ULIDs.
- Streak-Berechnung liest subject-scoped Wellbeing-Tage aus dem Health-Service.
  Punkte, Regeln und Beträge blieben unverändert in Identity.
- Prompt 10 stellte Check-in, Dashboard und Verlauf in Angular auf die
  kanonische 1–5-Skala und den Vertrag ohne `note` um.
- Prompt 08a verschob zusätzlich Anamnese, Health-Dokumentmetadaten und
  Wearables auf `health_subject_id`. Dies folgt ADR-003 D8 und erweitert die
  ursprüngliche ELYO-110-Bewertung bewusst.

Entscheidende Nachweise: `HealthSchemaBoundaryTest`,
`EmployeeTest`, `EmployeeHealthProfileTest`,
`DemoDataSeederSubjectProvisioningTest` und die Angular-Specs für Check-in,
Dashboard, Verlauf und `EmployeeService`. Die reviewed commits sind
`162bc85`, `187fc41`, `04bb057`, `efab7ca` und `c8475ce`.

## Auswirkungen

- Company-Runtimes haben keinen Wellbeing-Lesepfad mehr. Dashboard- und
  Report-Blöcke liefern gemäß ADR-003 D7 ausschließlich
  `status: reporting_pending` und `data: null`, bis ein Reporting-Worker
  suppressionsgeprüfte Quartals-Snapshots bereitstellt.
- Live-Aggregation aus Health-Daten wurde nicht als Übergangslösung
  nachgebaut.
- Streaks funktionieren weiter. Punkte selbst bleiben Identity-seitig; diese
  bewusst begrenzte Trennung ist kein zusätzlicher Company-Lesepfad.

## Offene Folgeentscheidungen

1. Punkte und Survey-Antworten verbleiben im Pilot abweichend von ADR-001 §2.6
   am `user_id`. Ein eigener Domain-Entscheid muss Datenmodell und
   Company-Aggregationen gemeinsam behandeln.
2. Health-Dokumente sind DB-seitig subject-scoped; Bucket-Isolation,
   pseudonyme Pfade, Metadatenbereinigung, Virenscan und kurzlebige signierte
   URLs nach ADR-001 §2.9 bleiben ein eigenes Hardening-Ticket.
3. Reporting-Worker, `resolveReportingCohort` und unveränderliche
   Quartals-Snapshots gehören in den Reporting-Epic. Bis dahin bleibt
   `reporting_pending` die einzige zulässige Company-Antwort für Wellbeing.
4. Fachliche Aufbewahrungsfristen benötigen weiterhin eine rechtliche
   Entscheidung; die vorhandene technische Löschmechanik ersetzt diese nicht.
