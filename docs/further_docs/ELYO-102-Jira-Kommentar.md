Hi zusammen,

die Contract-Entscheidungen für die Produktions-APIs sind getroffen und dokumentiert: `docs/decisions/elyo-102-api-contract-entscheidungen.md` (Stand: Commit *<HASH eintragen>*). Das Dokument ist per Referenz-ID zitierbar und der direkte Input für ELYO-114 (finaler OpenAPI-Contract). Der Demo-OpenAPI-Entwurf war Referenz-Input, nicht Grundlage — alle Abweichungen vom Demo-Format sind in §5 begründet.

**Die vier Kernentscheidungen:**

1. **Schreibpfad Laborwerte (§1.4):** Ja, es gibt einen — im MVP ausschließlich manuelle Eingabe durch den Beschäftigten, mit Pflicht-Provenienzfeld `source`, sodass Dokumentenimport später additiv nachrüstbar ist. Damit ist die offene Frage aus dem Ticket (und DSFA-Frage 1) beantwortet; die DSFA-Vorprüfung wird entsprechend nachgezogen (Re-Review-Trigger).
2. **Route-Semantik (§4.1):** Der Demo-Split wird übernommen: `/employee/measures` = eigene System-Maßnahmen, `/employee/company-measures` = Firmen-Maßnahmen. Bewusster Breaking Change — Frontend wird ohnehin neu gebaut, keine externen Konsumenten.
3. **Check-in (§3):** Kanonische 1–5-Skala (Migration ELYO-135), Drei-Klassen-Trennung aus der DSFA übernommen, Freitext `note` aus dem v1-Contract **gestrichen** (Risiko R5/Z7; Wiedereinführung nur über ELYO-109).
4. **Dashboard-Payload (§2.1):** v1 enthält nur Blöcke mit realer Produktivquelle: `wellbeing`, `metrics`, `sleep` (letzteres gekoppelt an das Schlaf-Feld im Check-in-Contract ELYO-133). `bodySignals`/`healthFlag`/`levers` kommen erst nach ELYO-91 + Per-Block-Entscheidung (ELYO-117).

**Breaking Changes gegenüber main** sind vollständig inventarisiert (§4.2, B1–B5): Measures-Route-Split, Skalenwechsel 1–10 → 1–5 inkl. Response-Wertebereiche, note-Streichung.

**Bitte um Review (Frontend + Backend):** Schaut euch besonders §1.1–1.3 (lab-markers-Ressourcenschnitt inkl. History-Endpoint), §4.2 (Breaking-Change-Inventar) und §5 (Demo-Abweichungen) an. Abgleich bitte gegen die fachlichen Anforderungen der Gap-Analyse, nicht gegen den Demo-Code. Zustimmung oder Einwände als Kommentar hier oder inline im Dokument; Sign-off-Block ist am Ende.

Bewusst offen gelassen (delegiert): `location`-Wertemenge und `sleep`-Typ → ELYO-133, Plausibilitätsgrenzen je Marker → ELYO-114, Per-Block-Entscheidung Dashboard → ELYO-117.

Danke!
