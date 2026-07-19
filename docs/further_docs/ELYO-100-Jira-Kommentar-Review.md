Hi ihr zwei,

die Architekturentscheidung für die Datentrennung (ADR-001, im Anhang) steht. Die technischen Details müsst ihr nicht prüfen – aber ihr seid beide Stand jetzt unsere internen Kontrollpersonen für Privacy, und ein paar Entscheidungen sind fachlich/geschäftlich, nicht technisch. Die sollt ihr bitte bewusst mittragen oder Einspruch erheben.

**Bitte reviewt diese Punkte (Referenzen = Abschnitte im Entscheidungsbogen):**

1. **Eure eigene Rolle (5.3, 9.3, 17.2):** Jede Re-Identifizierung eines Nutzers braucht künftig die vorherige Freigabe von einer von euch (Vier-Augen-Prinzip), und jede neue Reporting-Kennzahl geht über euren Tisch (Privacy-Review). Könnt und wollt ihr das leisten? Das ist die wichtigste Frage in diesem Review.
2. **Was Arbeitgeber sehen (7.3, 8.1, 8.5):** Nur Gesamtunternehmens-Reports pro Quartal, Mindestgruppengröße 10 (bei sensiblen Werten 20), und eine Liste von Inhalten, die nie in Reports auftauchen (Diagnosen, Laborwerte, psychische Gesundheit, Schwangerschaft …). Passt das zu dem, was wir Kunden versprechen bzw. verkaufen wollen?
3. **Was Nutzer ohne Arbeitgeber behalten (3.2, 4.2):** Wer die Firma verlässt oder deren Vertrag endet, behält kostenlosen Lesezugriff auf seine Daten inkl. Export, kann aber nichts Neues erfassen. Ist das aus Nutzer- und Vertriebssicht die richtige Botschaft?
4. **Kundenkündigung (11.5):** Der Kunde behält seine bezahlten Reports als Export, seine Aggregate löschen wir nach 12 Monaten, die Konten der Beschäftigten bleiben bestehen. Ist die 12-Monats-Frist aus Vertragssicht okay?
5. **Bewusst eingegangene Risiken (11.1, 18.1):** Es gibt im Pilot keinen "Account sperren"-Zustand (nur löschen), und die Datenschutz-Folgenabschätzung ziehen wir parallel zum Pilot nach statt vorab. Beides ist dokumentiert – ihr solltet es aber bewusst mittragen, besonders Punkt 18.1.

Alles andere (Datenbanken, Runtimes, Verschlüsselung etc.) ist rein technisch – könnt ihr überfliegen, müsst ihr aber nicht bewerten.

Gebt mir bitte bis Ende nächster Woche ein Go oder eure Einwände pro Punkt (reicht als Kommentar hier im Ticket). Danach machen wir die formale Abnahme nach 18.2.

Danke euch!
