# Offene Entscheidungen: Triage

Arbeitsdokument zu `2026-07-27-00-findings-execution-plan.md`. Hier stehen alle Punkte, die der
Agent nicht selbst entscheiden darf — zusammengeführt, sortiert nach dem, was zur Beantwortung
nötig ist.

**Stand:** 27.07.2026 · Nominal 25 Punkte (14 U-Fragen + 11 Entscheidungspunkte), nach
Entdopplung **24**. Davon **10 entschieden**, 10 in Gruppe B, 4 in Gruppe C.
Dazu **3 neue** aus Paket 17 (Gruppe D) — Gesamtstand **27**.

## Warum das jetzt dran ist

Paket 16 hat im Plan Priorität 4 und steht in der empfohlenen Reihenfolge ganz hinten. Es
beantwortet aber Fragen, die **11 Etappen in acht Paketen** über alle Prioritäten blockieren —
darunter Paket 01, 02, 05 und 06 aus Priorität 1 und 2. Das ist ein Widerspruch im Plan: Wer der
Reihenfolge folgt, läuft in Paket 01 in eine gesperrte Etappe, deren Antwort erst ganz am Ende
kommt.

**Auflösung:** Paket 16 hat zwei Rollen — Entscheidungen treffen und Architekturdoku schreiben.
Die erste Rolle wird vorgezogen (dieses Dokument), die zweite bleibt Priorität 4.

## Entdopplung

| Zusammengeführt | Begründung |
|---|---|
| U14 ≡ Entscheidungspunkt 06.3 | Beide fragen, ob Partner-Sitzungen über Cookie oder Bearer laufen. Eine Antwort, zwei Fundstellen. |

| Abhängig, nicht identisch | Beziehung |
|---|---|
| U1 → 15.3 | Ob der `partner`-Portalzweig bleibt, entscheidet mit, ob `PartnerDashboardComponent` entfernt wird. |
| 15.2 → U10 | Die drei VAPID-Variablen sind Teil der 16 verwaisten ENV-Variablen. Fällt Push, fallen sie mit. |

---

## Gruppe A — jetzt entscheidbar

Kein Vorlauf nötig. Antwort ist eine Produkt- oder Konventionsfrage, die du kennst.
**Zehn Punkte, entsperrt sofort Etappen in sieben Paketen.**

| Punkt | Frage | Entsperrt | Antwort (27.07.2026) |
|---|---|---|---|
| U7 | Ist SSR produktiv vorgesehen? | 03.3 | **Nein.** SPA bleibt, SSR-Reste werden entfernt. |
| 01.6 | Passwort-Zurücksetzung fertig bauen oder Endpunkt entfernen? | 01.6 | **Fertig bauen** — vollständiger Flow mit Token, Mail und Ablauf. |
| 15.2 | Push-Benachrichtigungen: auf der Roadmap? | 15.2 | **Jetzt bauen.** → eigenes Paket 17, nichts wird entfernt. |
| 02.5 | Wearables und `health_documents`: auf der Roadmap? | 02.5 | **Bleibt liegen**, mit Ticketverweis kommentiert. Nichts entfernen. |
| U8 | Welches Doku-Verzeichnis ist maßgeblich? | 04.8 | **Nur Dopplungen auflösen:** `further-docs`→`further_docs`, `decisions`→`adr-documents`. Rest bleibt. |
| U2 | Soll `COMPANY_OWNER` von der Umfrageerstellung ausgeschlossen sein? | 11.1 | **Nein, darf.** Request erweitern, Route unverändert, OpenAPI mitziehen. |
| U3 | Soll `ELYO_SUPPORT` von der Partnerfreigabe ausgeschlossen sein? | 06.5 | **Nein — Versehen.** Rolle ergänzen, an übrige Admin-Aktionen angleichen. |
| U1 | Ist der `partner`-Portalzweig in `User::canUsePortal()` beabsichtigt? | 06.6, 15.3 | **Ja, Portal ist geplant.** Zweig und `PartnerDashboardComponent` bleiben, kommentiert. |
| U10 | Bleiben die 16 verwaisten ENV-Variablen erhalten? | 15.7 | **Ja**, mit Kommentar je Variable (geplant / Altlast / extern gesetzt). |
| 10.3 | Toter Anzeigepfad J22: entfernen oder anbinden? | 10.3 | **→ Gruppe B.** Erst aufbereiten lassen. |

### Folgen der Antworten

**Push wird gebaut → neues Paket 17.** Das ist keine Befundbehebung mehr, sondern ein Feature:
Bibliothek `minishlink/web-push` aufnehmen, VAPID-Schlüsselverwaltung, Registrierungsendpunkt,
Service Worker und `Notification.requestPermission()` im Frontend, Zustellungs- und
Fehlerbehandlung, Abmeldung, DSGVO-Einwilligung. Der vorhandene Code ist eine Attrappe und
trägt nichts davon. Etappe 15.2 wird zur Nulloperation mit Verweis auf 17.

**Passwort-Reset wird gebaut → Paket 01 wächst und bekommt eine echte Abhängigkeit.**
Etappe 01.6 ist keine Entscheidung mehr, sondern eine Implementierung: Token-Tabelle, zwei Routen,
Ablauf und Einmalverwendung, Rate-Limiting aus Etappe 01.1, Widerruf aller bestehenden Tokens.

**Wichtig:** Im Repository existiert **kein Mailversand** — keine Mailable, keine Notification,
kein `Mail::`-Aufruf. **Paket 07 Etappe 1** baut diese Grundlage erst. Damit muss 01.6 nach 07.1
laufen, obwohl Paket 01 in der Priorität vor 07 steht. Empfohlen: `01 --stage 1` bis `--stage 5`
jetzt, Etappe 6 nach Paket 07 nachziehen.

**02.5 und 15.2 sind keine gesperrten Etappen mehr.** Beide werden zu „kommentieren, nichts
entfernen".

## Gruppe B — Aufbereitung durch den Agenten, dann entscheiden

Die Frage ist beantwortbar, aber nicht aus dem Kopf. Der Agent kennt den Code, hat die
Konsequenzen vor sich und schreibt Optionen mit Folgen nach `docs/ai-results/`. Entscheidung
danach in einer zweiten, kurzen Runde.

**Zehn Punkte** (10.3 kam aus Gruppe A dazu). **Diese Etappen laufen bewusst in den Abbruch — das ist der geplante Weg,
nicht ein Fehlschlag.**

| Punkt | Frage | Aufbereitet in | Was der Agent liefern soll |
|---|---|---|---|
| U4 | Darf ein Manager mehrere Teams verwalten? | Paket 12 | Welche Stellen `team_id` als Einzelwert annehmen; Migrationsaufwand |
| U9 | Darf Retention `PROPOSED`-Kategorien löschen? | Paket 05 | Was heute gelöscht wird, was bei beiden Varianten anders wäre |
| U14 / 06.3 | Partner-Sitzung: Cookie oder Bearer? | Paket 06 | Beide Pfade im Code, wo sie sich widersprechen, Migrationsweg |
| 06.2 | Welche Partner-Statusübergänge soll es geben? | Paket 06 | Ist-Zustandsautomat, fehlende Übergänge, Auswirkung auf H1/H2/C10 |
| 05.2 | `audit_events.subject_ref` befüllen oder fallenlassen? | Paket 05 | Was die Spalte enthalten müsste, Datenschutzfolgen beider Wege |
| 14.3 | Nicht setzbare Vorlagenfelder: befüllbar oder weg? | Paket 14 | Welche Felder, wer sie setzen müsste, ob das UI sie kennt |
| 14.6 | Zuweisungsdomäne und Übungs-Tags: entfernen oder anbinden? | Paket 14 | Umfang der Domäne, was ein Anschluss kosten würde |
| 13.6 | Was bricht einen Streak? | Paket 13 | Ist-Verhalten, Randfälle (Zeitzone, Nachtragen), Alternativen |
| U13 | Ist die Zeitzonenabhängigkeit des `period_key` bewusst? | Paket 13 | Wo `period_key` gebildet wird, wer betroffen ist |
| 10.3 | Toter Anzeigepfad J22: entfernen oder anbinden? | Paket 10 | Was der Pfad zeigen würde, ob ein Backend existiert, Kosten beider Wege |

## Gruppe D — neu aus Paket 17 (Push)

Entstanden mit dem Feature, nicht aus Kapitel 14. **P1 ist keine Produktentscheidung** und
gehört deshalb nicht in Gruppe A — Web Push für eine Gesundheitsanwendung führt einen neuen
Datenfluss zu einem Drittanbieter im Drittland ein.

| Punkt | Frage | Sperrt | Wer entscheidet |
|---|---|---|---|
| P1 | Darf Web Push mit Google/Apple/Mozilla als Zustelldienst eingesetzt werden? Rechtsgrundlage, AV-Vertrag, Bezug zu ADR-002 | **Paket 17 vollständig** | Björn **+ Datenschutz** |
| P2 | Welche der drei Arten (`checkin_reminder`, `weekly_summary`, `partner_updates`) werden gebaut? | 17.1 | Björn. `partner_updates` hängt zusätzlich an 06.2 |
| P3 | Uhrzeit und Zeitzone der Check-in-Erinnerung | 17.7 | Björn — **zusammen mit U13 entscheiden**, sonst driften Check-in-Tag und Erinnerung auseinander |

## Gruppe C — erst nach der Umsetzung sinnvoll

Vorher entscheiden hieße raten. Diese bleiben bewusst offen, bis das jeweilige Paket gelaufen ist.

| Punkt | Frage | Sinnvoll ab |
|---|---|---|
| U5 | Nach welchem Kriterium sind Anamnesefelder verschlüsselt? | nach Paket 02, Etappen 1–4 — vorher ist die Feldliste nicht belastbar |
| U11 | Wo liegt der Karenzworkflow der Kontolöschung? | Recherchefrage, nicht Entscheidung — Paket 05 beantwortet sie |
| U12 | Warum ignoriert `accept()` Name und Passwort bestehender Nutzer? | vermutlich ein Fehler, kein Entwurf — klärt sich beim Lesen in Paket 07 |
| U6 | Was bedeutet `partners.minimum_level`? | Wissensfrage. Wenn es niemand mehr weiß, wird es zur Entscheidung in Paket 06 |

---

## Vorgehen

1. ~~Gruppe A beantworten.~~ **Erledigt am 27.07.2026.** Alle zehn Antworten sind in die
   betroffenen Paketdateien als Vorgabe eingetragen; die `blocked_by`-Felder von 03, 04, 11 und 15
   sind geleert, 06 steht nur noch auf U6 und U14.
2. **Pakete in der empfohlenen Reihenfolge fahren** — `04 → 09 → 01 → 05 → 02 → 03`, dann
   Priorität 2 und 3.
3. **Gruppe B einsammeln.** Jedes Paket liefert seine Aufbereitung nach `docs/ai-results/`.
   Nach Priorität 2 eine zweite Entscheidungsrunde über alles, was bis dahin aufbereitet ist.
4. **Gruppe C** bleibt offen, bis das jeweilige Paket durch ist.
5. **Paket 16** schreibt am Ende die getroffenen Entscheidungen als ADR fest — dann als
   Dokumentationsaufgabe, nicht als Entscheidungsaufgabe.

## Fortschreibung

Beantwortete Punkte werden hier mit Datum und Begründung eingetragen und in der jeweiligen
Paketdatei als Vorgabe hinterlegt. Dieses Dokument ist die Quelle, die Paketdateien sind Kopien.
