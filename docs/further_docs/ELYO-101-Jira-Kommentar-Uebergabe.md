Hi ihr zwei,

die technische Vorprüfung für die Datenschutz-Folgenabschätzung ist fertig (Dokument: `docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md`, Stand: Commit *<HASH eintragen>*; Methodik in ADR-002). Das ist eine reine Faktensammlung — Datenkategorien, Zwecke, Speicherorte, Zugriffe, Risiken — keine Rechtsberatung. Die rechtliche Bewertung liegt bei euch bzw. später beim externen Berater; das Dokument ist so aufgebaut, dass ihr es unverändert weitergeben könnt.

**Was diese Übergabe formal bedeutet:** Reale Gesundheitsdaten werden erst produktiv gespeichert, wenn (a) die drei technischen Blocker nachweislich behoben sind (automatische Tests) und (b) ihr diese Vorprüfung ohne Veto zur Kenntnis genommen habt. Eure Einwände werden als neue Blocker aufgenommen. Ihr könnt den Start also stoppen — bitte lest entsprechend aufmerksam.

**Bitte schaut euch konkret an (Abschnitte im Dokument):**

1. **Die 7 offenen Fragen (§9):** Die müssen vor bzw. in der DSFA beantwortet werden. Drei davon sind Produktentscheidungen mit Privacy-Kern: Woher kommen Laborwerte (manuelle Eingabe/Dokumentimport)? Dürfen wir Symptome/Krankheiten strukturiert erfassen? Behalten wir das Freitextfeld im Check-in? Sagt mir, welche ihr selbst entscheiden könnt und welche extern geklärt werden müssen.
2. **Risiko-Inventar Demo (§7):** 11 Befunde aus dem Demo-Branch mit Einstufung — einmal Ist-Zustand, einmal Restrisiko nach geplanter Architektur. Die drei Blocker (R1–R3) sind alle "Laborwerte hängen direkt am Nutzerkonto"; die Ziel-Architektur aus ADR-001 löst das, ist aber noch nicht gebaut. Bitte bestätigt, dass ihr die Logik "Blocker bleibt Blocker bis Testnachweis" mittragt.
3. **Das bekannte Timing-Risiko (§8, Z5):** Die DSFA selbst ziehen wir laut ELYO-100/18.1 parallel zum Pilot nach. Diese Vorprüfung mildert das Risiko, hebt es nicht auf. Ihr habt das bei ADR-001 schon mitgetragen — hier taucht es wieder auf, bewusst.
4. **Altbestände (Anhang B):** Dokumenten-Upload, Anamnese und der alte Wellbeing-Bestand sind NICHT Teil dieser Vorprüfung, sondern nur kurz eingestuft (alle High). Die brauchen ihre eigene Behandlung in der DSFA — bitte bestätigt, dass das für euch so okay ist und nicht untergeht.

Die Datenfluss-Diagramme und Tabellen (§4–6) sind das Kernmaterial für den DSB — überfliegen reicht für euch, prüfen muss die der Fachmensch.

Gebt mir bitte bis Ende nächster Woche pro Punkt ein Go oder eure Einwände (Kommentar hier im Ticket reicht). Danach trage ich Übergabedatum und Ergebnis im Dokument ein (§10) und die Blocker-Steuerung für ELYO-91 ist offiziell aktiv.

Danke euch!
