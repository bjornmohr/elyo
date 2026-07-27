# ELYO-109: Entscheidung zum Check-in-Freitext `note`

## Entscheidung

Das Feld `note` ist im produktiven Check-in-Vertrag entfernt. Diese Entscheidung
setzt ELYO-102 B4/§3.3 und ADR-003 D3 um:

- `POST /employee/checkin` lehnt jede mitgelieferte `note` mit `422` und dem
  Validierungsfehler `prohibited` ab.
- Check-in-Antworten, Status, Verlauf und Dashboard enthalten keine `note`.
- Das Health-Schema besitzt keine Freitextspalte für Wellbeing-Einträge.
- Die Angular-Anwendung sendet oder rendert keine Check-in-Notiz.

Die harte Ablehnung ist beabsichtigt. Ein älterer Client darf Freitext nicht
scheinbar erfolgreich senden und dabei unbemerkt verlieren.

## Begründung

Freitext kann unbeabsichtigt besonders sensible, identifizierende oder
medizinisch konkrete Angaben enthalten. Für den aktuellen Zweck
„strukturierte tägliche Selbsteinschätzung“ sind die drei Werte auf der Skala
1–5 ausreichend; das Entfernen folgt daher dem Prinzip der Datenminimierung.
Diese technische Produktentscheidung nimmt keine rechtliche Einordnung vor.

Nachweise: `CheckinRequest`, `EmployeeTest`, die Health-Migration für
`wellbeing_entries`, Angular `checkin.component.spec.ts` und
`docs/api/openapi.yaml`; reviewed commits `162bc85`, `04bb057` und `c8475ce`.

## Additiver Weg für eine spätere Wiedereinführung

Eine spätere ELYO-109-Entscheidung darf `note` nur als neue, additive
Funktion einführen. Vor Umsetzung sind mindestens erforderlich:

1. expliziter, dokumentierter Zweck und eine Minimierungsentscheidung
   einschließlich zulässiger Inhalte, Länge und Aufbewahrung;
2. Privacy-/DSFA-Re-Review und Entscheidung über Ausschluss oder technische
   Begrenzung medizinischer bzw. identifizierender Angaben;
3. aktualisiertes Health-Schema, Form Request, Resource und bindender
   OpenAPI-Vertrag;
4. test-first Nachweise für Validierung, Subject-Scoping, Löschung,
   Nichtausgabe an Company/Admin sowie Leak-Scanner-Abdeckung;
5. abgestimmte UI-Texte ohne Diagnose- oder Therapieaussagen.

Bis diese Gates erfüllt sind, bleibt `note` verboten; stilles Ignorieren ist
keine zulässige Zwischenlösung.
