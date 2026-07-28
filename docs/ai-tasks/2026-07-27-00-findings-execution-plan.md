# Ausführungsplan: Befunde aus der technischen Dokumentation

## Herkunft

Alle Befunde stammen aus der implementierungsnahen Dokumentation in Confluence (Space **ELYO**,
Seite „Technische Dokumentation", Kapitel 14 „Bekannte Inkonsistenzen und technische Risiken"),
erstellt am 26./27.07.2026 gegen Commit `56b4a53148d2ab9c59a74c0396b486671958fc49`
(Branch `elyo-91/17-docs-closure-and-verification`).

Kapitel 14 listet rund 164 Einzelbefunde. Dieser Plan bündelt sie zu **16 fachlichen
Arbeitspaketen**. Jedes Paket ist in **einzeln reviewbare Etappen** zerlegt: eine Etappe ist ein
Commit mit eigenem Test und eigenem Abnahmekriterium.

## Warum fachlich gebündelt

Ein Task je Befund hätte 164 Branches erzeugt, viele davon mit Ein-Zeilen-Diffs, und
zusammengehörige Änderungen künstlich getrennt — etwa die sieben Stellen, an denen dieselbe
Manager-Prüfung dupliziert ist. Die fachliche Bündelung hält zusammen, was gemeinsam getestet
werden muss.

**Reviewbarkeit bleibt erhalten durch:**

- Jede Etappe ist ein eigener Commit mit eigenem Abnahmekriterium
- Jede Etappe nennt die abgedeckten Befund-IDs aus Kapitel 14
- Jedes Paket endet mit einer Review-Checkliste
- Pakete mit mehr als sechs Etappen werden als Serie von Pull Requests gefahren, nicht als einer

## Arbeitsweise

Analog zur bestehenden Prompt-Serie (`2026-07-19-00-elyo-91-execution-plan.md`):

- Ein Paket pro Branch: `findings/<NN>-<slug>`
- Frische Session je Paket
- Eine Etappe = ein Commit; Reihenfolge innerhalb des Pakets einhalten
- Nach jeder Etappe: Tests grün, dann committen
- Nach dem Paket: validieren, reviewen, mergen

### Harte Randbedingungen für alle Pakete

- Kein `migrate:fresh`, kein `db:wipe`, kein `docker compose down -v`
- Kein bare `php artisan migrate` — nur `php artisan elyo:migrate-fresh` bzw. Domänen-Pfade
- Reviewte Baselines nie editieren; Schemaänderungen ausschließlich als neue Migration im
  jeweiligen Domänenverzeichnis
- ADR-001 / ADR-003 sind bindend: kein direkter Join zwischen Identity und Health, kein
  Health-Lesepfad für die Company-Runtime, Mapping-Zugriffe ausschließlich über `MappingService`
- Tests laufen im Container: `docker compose exec api-tooling php artisan test`
- Keine Mehrfirmen-Nutzer einführen

---

## Die Arbeitspakete

| # | Paket | Etappen | Befunde | Prio | Status |
|---|---|---|---|---|---|
| 01 | Authentifizierung und Sitzungssicherheit | 6 | A1, A2, A6, A12, D5, E5, H4 | 1 | offen |
| 02 | Gesundheitsdaten: Speicher, Zugriff, Verschlüsselung | 7 | A5, E3, G3, G6, G14, H9, H10, H16, H17, J13, J31 | 1 | offen |
| 03 | Frontend-Auslieferung | 4 | B2, B3, J25, J26 | 1 | offen |
| 04 | Infrastruktur- und Betriebshärtung | 8 | A8, A9, A10, A11, A13, A14, B4, B6, B7, B8, B9, B10, B11, B12, H18 | 1 | offen |
| 05 | Audit- und Mapping-Domäne | 5 | B1, G4, G5, G12, G22, J16 | 1 | offen |
| 06 | Partner-Subsystem | 7 | A3, A4, C10, C11, D1, D6, H1, H2, H14, H15, J30, J33, K1 | 2 | offen |
| 07 | Einladung und Onboarding | 5 | A7, D10, G7, H3, I1, U12 | 2 | offen |
| 08 | Fehlerbehandlung und API-Vertrag | 7 | A10, C1, C2, C5, C6, C12, C14, C-FE, E1, E2, E4, E6, E7, K4 | 2 | offen |
| 09 | CI und Testinfrastruktur | 5 | B5, J34, K1, K2, K3 | 2 | offen |
| 10 | Company-Reporting und Anonymisierung | 5 | C3, C4, H5, H6, J21, J22, J23 | 3 | offen |
| 11 | Umfragen | 7 | C13, C14, D2, D9, F5, G17, G20, H13, I4, I8, J5, J15, J19, J20 | 3 | offen |
| 12 | Teams und Manager-Berechtigungen | 6 | D3, D4, D7, D8, F1, F2, F8, H11, I9 | 3 | offen |
| 13 | Maßnahmen, QR-Check-in und Punkte | 6 | F6, G16, G21, I2, I7, J14, J17, J18, J24 | 3 | offen |
| 14 | Admin-Kataloge und Nebenläufigkeit | 6 | C7, C8, C9, F3, F4, G13, G18, H7, H8, H12, I3, I5, J29 | 3 | offen |
| 15 | Aufräumen: toter Code, Datenmodell, Frontend-Struktur | 12 | G1, G2, G8–G11, G15, G19, G23–G27, F7, I10, I11, J3, J4, J6–J11, J32 | 4 | offen |
| 16 | Architektur- und Produktentscheidungen dokumentieren | 4 | J1, J2, J12, U1–U14 | 4 | offen |
| 17 | Push-Benachrichtigungen bauen | 9 | G2, G15 | 3 | offen |

**Summe: 109 Etappen** über 17 Pakete.

Paket 17 ist kein Befundpaket, sondern ein Feature — entstanden aus der Entscheidung zu 15.2.
Es bringt drei eigene Entscheidungspunkte mit (**P1–P3**), von denen P1 das ganze Paket sperrt.

---

## Empfohlene Reihenfolge

```
Prio 1  ──►  04 ──► 05 ──► 01 (Etappen 1–5) ──► 02 ──► 03
             │      │
             │      └─ 05 vor 02: die Audit-Grant-Diskrepanz (B1) betrifft
             │         den Provisionierungspfad, auf dem 02 aufsetzt
             └─ 04 zuerst: legt api-tooling hinter ein Profil, was CI und
                alle folgenden Test-Aufrufe betrifft

             ⚠ 01 Etappe 6 (Passwort-Zurücksetzung) erst NACH 07 Etappe 1.
               Es gibt im Repository keinerlei Mailversand; 07.1 baut ihn.
               Seit der Entscheidung „fertig bauen" (27.07.) ist das eine
               echte Abhängigkeit, keine Empfehlung.

Prio 2  ──►  09 ──► 08 ──► 07 ──► 06
             │      │      │
             │      │      └─ 07 vor 06: der Mailversand aus 07 wird von
             │      │         06 für Statusmails konsumiert
             │      └─ 08 vor 07: der Exception-Handler ist Grundlage
             └─ 09 zuerst: ohne CI ist keine der folgenden Änderungen abgesichert

Prio 3  ──►  12 ──► 11 ──► 13 ──► 14 ──► 10
             │
             └─ 12 zuerst: die entduplizierte Manager-Prüfung wird von
                11, 13 und 14 verwendet

Prio 4  ──►  16 ──► 15
             │
             └─ 16 vor 15: die Entscheidungen aus 16 bestimmen, was in 15
                entfernt und was angebunden wird

Feature   ►  17 (nach 04)
             │
             └─ 17 braucht den Queue-Worker und die Scheduler-Runtime,
                die 04 anlegt. P1 (Datenschutz) sperrt bis dahin alles.
```

**Paket 16 blockiert Teile von 15**, weil dort entschieden wird, ob toter Code entfernt oder
angebunden wird. Wer 15 vorzieht, muss die betroffenen Etappen überspringen.

---

## Arbeitsregeln und Entscheidungspunkte

Jede Paketdatei trägt direkt nach dem `ai-run`-Block zwei Abschnitte, die den restlichen Inhalt
überstimmen.

**Arbeitsregeln** — sechs Regeln gegen die typischen Fehlerbilder eines Agenten:

1. Erst prüfen, dann ändern — die Befunde stammen vom Stand `56b4a53`, nicht vom Branch
2. Befund trifft nicht zu → melden, nicht umdeuten
3. Nur die in der Etappe genannten Dateien anfassen
4. Nichts löschen ohne ausdrücklichen Auftrag; „kein Aufrufer gefunden" ist kein Löschgrund
5. Abbruch ist ein gültiges Ergebnis und wird als solches berichtet
6. Abnahme ist Nachweis (gelaufener Testbefehl), nicht Behauptung

**Entscheidungspunkte** — eine Tabelle je Paket, die jede Entweder-oder-Stelle einem Entscheider
zuordnet. Zeilen mit **Björn** sind für den Agenten gesperrt: Optionen und Konsequenzen nach
`docs/ai-results/` schreiben, Etappe als blockiert markieren, weitermachen. Zeilen mit *Agent*
darf er selbst entscheiden, mit Begründung im Commit.

Nach der Entscheidungsrunde vom 27.07.2026 sind noch **8 Etappen** in sechs Paketen gesperrt:
05.2, 06.2, 06.3, 10.3, 13.6, 14.3, 14.6, 15.3. Alle acht gehören zu **Gruppe B** — sie werden
nicht blind übersprungen, sondern vom Agenten aufbereitet (Optionen mit Konsequenzen nach
`docs/ai-results/`) und danach in einer zweiten Runde entschieden.

Die vollständige Triage steht in **`2026-07-27-entscheidungen.md`** — das ist ab jetzt die Quelle
für alle offenen Entscheidungen, die Paketdateien tragen Kopien. `scripts/run-ai-task.sh` zeigt
die gesperrten Etappen im Preflight unter „Your decisions"; die Kreuz-Review prüft als Punkt 9,
ob der Agent sich daran gehalten hat.

**Zehn Punkte wurden am 27.07.2026 entschieden** (U1, U2, U3, U7, U8, U10 sowie 01.6, 02.5,
15.2, und 10.3 als Verschiebung nach Gruppe B). Zwei davon ändern den Zuschnitt: Push wird
gebaut (neues **Paket 17**), die Passwort-Zurücksetzung wird fertiggestellt statt entfernt
(**Paket 01** wächst um etwa eine Etappe).

Paket 17 bringt mit **P1–P3** drei neue Entscheidungspunkte mit, die nicht aus Kapitel 14
stammen, sondern aus dem Feature selbst. **P1** (Einwilligung und Drittlandtransfer) ist keine
Produktfrage, sondern eine datenschutzrechtliche — sie gehört nicht in Gruppe A.

Der Unterschied zu den U-Fragen unten: U-Fragen sind **paketübergreifend** und werden in Paket 16
gesammelt beantwortet. Entscheidungspunkte sind **paketlokal** und lassen sich einzeln klären,
ohne auf Paket 16 zu warten.

---

## Offene Entscheidungen

Diese Punkte sind **nicht** durch Code entscheidbar. Sie werden in **Paket 16** gesammelt
beantwortet und blockieren bis dahin die genannten Etappen.

| ID | Frage | Blockiert |
|---|---|---|
| ~~U1~~ | ~~Ist der `partner`-Portalzweig in `User::canUsePortal()` beabsichtigt?~~ **Beantwortet 27.07.: Ja, Partner-Portal ist geplant — Zweig bleibt** | — |
| ~~U2~~ | ~~Soll `COMPANY_OWNER` von der Umfrageerstellung ausgeschlossen sein?~~ **Beantwortet 27.07.: Nein, `COMPANY_OWNER` darf** | — |
| ~~U3~~ | ~~Soll `ELYO_SUPPORT` von der Partnerfreigabe ausgeschlossen sein?~~ **Beantwortet 27.07.: Nein, war ein Versehen — Rolle ergänzen** | — |
| U4 | Darf ein Manager mehrere Teams verwalten? | 12.3 |
| U5 | Nach welchem Kriterium sind Anamnesefelder verschlüsselt? | 02.6 |
| U6 | Was bedeutet `partners.minimum_level`? | 06.7 |
| ~~U7~~ | ~~Ist SSR produktiv vorgesehen?~~ **Beantwortet 27.07.: nein, SPA bleibt** | — |
| ~~U8~~ | ~~Welches Doku-Verzeichnis ist maßgeblich?~~ **Beantwortet 27.07.: Nur die Dopplungen auflösen** | — |
| U9 | Darf Retention `PROPOSED`-Kategorien löschen? | 05.5 |
| ~~U10~~ | ~~Bleiben die 16 verwaisten ENV-Variablen erhalten?~~ **Beantwortet 27.07.: Ja, bleiben mit Kommentar** | — |
| U11 | Wo liegt der Karenzworkflow der Kontolöschung? | 05.4 |
| U12 | Warum ignoriert `accept()` Name und Passwort bestehender Nutzer? | 07.5 |
| U13 | Ist die Zeitzonenabhängigkeit des `period_key` bewusst? | 13.6 |
| U14 | Gilt `PartnerSession` (Cookie) oder Bearer? | 06.4 |

---

## Fortschreibung

Nach jedem abgeschlossenen Paket:

1. Status in der Tabelle oben auf `umgesetzt` setzen
2. Befunde in Confluence Kapitel 14 als erledigt markieren
3. Betroffene Fachseiten der Dokumentation aktualisieren
4. Bei Schemaänderungen: Kapitel 7 aktualisieren
5. Bei neuen oder geänderten Routen: Kapitel 6 und den Coverage-Report (Kapitel 15) aktualisieren
6. Bei behobenen Vertragsabweichungen: den Eintrag aus der Allowlist des Contract-Tests entfernen
   (siehe Paket 08, Etappe 7)

## Review-Konvention für alle Pakete

Jeder Pull Request nennt im Beschreibungstext:

- Paketnummer und abgedeckte Etappen
- Befund-IDs aus Kapitel 14 je Etappe
- Was bewusst **nicht** geändert wurde und warum
- Ob eine offene Entscheidung (U-Frage) berührt wurde
- Ob eine Migration nötig war und in welchem Domänenverzeichnis

---

## Ausführung mit `scripts/run-ai-task.sh`

```bash
scripts/run-ai-task.sh <paket> [--agent claude|codex] [--stage N] [optionen]
```

Das Skript liest den `ai-run`-Block aus der Paketdatei, erklärt dem Agenten die Semantik
dieses Plans und fährt anschließend die Kreuz-Review.

### Ablauf je Lauf

```
preflight  ──► Branch anlegen ──► Implementierung (interaktiv)
                                        │
                                        ▼
                              Handoff-Snapshot
                                        │
                                        ▼
                       Kreuz-Review durch den anderen Agenten
                       (schreibt docs/ai-reviews/<...>-review-<ts>.md)
                                        │
                                        ▼
                    Fix der kritischen Punkte (implementierender Agent)
```

**Kreuz-Review-Regel:** Codex implementiert → Claude reviewt. Claude implementiert → Codex
reviewt. Der Fix läuft wieder beim implementierenden Agenten, weil er den Kontext hat.

### Der `ai-run`-Block

Jede Paketdatei trägt nach der Befundzeile:

```yaml
complexity:        hoch          # Einordnung für Menschen
implement_tier:    high          # high | standard | fast
implement_effort:  high          # low | medium | high
review_tier:       high
review_effort:     high
blocked_by:        U9, U11       # offene Entscheidungen, oder -
depends_on:        05            # empfohlenes Vorgängerpaket, oder -
```

**Tier statt Modellname**, damit die Datei agentenneutral bleibt. Das Skript bildet ab:

| Tier | Claude | Codex |
|---|---|---|
| `high` | `opus` | `gpt-5.6-sol` |
| `standard` | `sonnet` | `gpt-5.6-terra` |
| `fast` | `haiku` | `gpt-5.6-luna` |

Überschreibbar über `RUN_AI_CLAUDE_MODEL_HIGH`, `RUN_AI_CODEX_MODEL_STANDARD` usw.

**Effort** ist bei beiden Agenten ein echtes CLI-Flag: Claude `--effort`
(`low|medium|high|xhigh|max`), Codex `-c model_reasoning_effort=` (`low|medium|high`).
Die `ai-run`-Blöcke nutzen nur `low|medium|high`, damit dieselbe Datei mit beiden
Agenten funktioniert; `xhigh` und `max` sind über `--effort` möglich und werden für
Codex auf `high` heruntergesetzt.

Das Skript zeigt Modell und Effort im Preflight und wartet auf Bestätigung, bevor
irgendetwas läuft. `--yes` überspringt die Rückfrage, `--dry-run` zeigt nur den Plan.

### Was interaktiv läuft und was nicht

| Phase | Modus | Warum |
|---|---|---|
| Implementierung | interaktive TUI | Du siehst mit und kannst eingreifen. **Beende die Sitzung mit Strg-D, wenn die Etappe fertig ist** — das Skript läuft dann von selbst weiter. |
| Review | headless | Braucht keine Eingabe. Der Agent darf lesen und denken, aber nichts ändern; sein Bericht wird nach `docs/ai-reviews/` geschrieben. |
| Fix | interaktive TUI | Fasst Produktivcode an — da schaust du zu. |

### Modell- und Effort-Zuordnung der 16 Pakete

| Paket | Komplexität | Implementierung | Review | Blockiert durch |
|---|---|---|---|---|
| 01 Auth und Sitzung | hoch | high / high | standard / medium | — |
| 02 Gesundheitsdaten | hoch | high / high | high / high | U5 |
| 03 Frontend-Auslieferung | mittel | standard / medium | standard / low | — |
| 04 Infrastruktur | mittel | standard / medium | standard / medium | — |
| 05 Audit und Mapping | hoch | high / high | high / high | U9, U11 |
| 06 Partner | mittel | standard / medium | standard / medium | U6, U14 |
| 07 Onboarding | hoch | standard / high | standard / medium | U12 |
| 08 Fehler und Vertrag | hoch | high / high | standard / medium | — |
| 09 CI und Tests | niedrig | standard / medium | standard / low | — |
| 10 Reporting | mittel | standard / medium | standard / medium | — |
| 11 Umfragen | hoch | standard / high | standard / medium | U2 |
| 12 Teams | mittel | standard / high | standard / medium | U4 |
| 13 Maßnahmen und Punkte | mittel | standard / medium | standard / medium | U13 |
| 14 Admin-Kataloge | mittel | standard / medium | standard / medium | — |
| 15 Aufräumen | niedrig | standard / medium | standard / medium | U10 |
| 16 Entscheidungen | hoch | high / high | high / medium | — |

`high`-Tier nur dort, wo eine Fehlentscheidung teuer ist: Sicherheit (01), Datenschutz und
ADR-Invarianten (02, 05), breite Vertragsänderungen (08) und Urteilsfragen (16). Der
Review-Tier folgt dem Risiko, nicht dem Umfang — Paket 15 ist groß, aber mechanisch.

### Etappenweise arbeiten

Pakete mit mehr als fünf Etappen sollten in mehreren Läufen gefahren werden:

```bash
scripts/run-ai-task.sh 04 --stage 1     # Branch findings/04-...-s1
scripts/run-ai-task.sh 04 --stage 2     # Branch findings/04-...-s2
```

Jede Etappe bekommt einen eigenen Branch und eine eigene Review. Das hält die Diffs klein
genug für ein sinnvolles Review.

### Wichtige Optionen

| Option | Wirkung |
|---|---|
| `--agent codex` | Codex implementiert, Claude reviewt |
| `--stage N` | Nur eine Etappe |
| `--baseline` | Testlauf **vor** der Arbeit nach `docs/ai-results/` — unterscheidet „war schon rot" von „ich hab's kaputt gemacht" |
| `--review-only` | Nur Review und Fix auf dem aktuellen Branch |
| `--no-review` | Ohne Kreuz-Review |
| `--plain` | Ohne caveman ultra |
| `--dry-run` | Nur Preflight anzeigen; funktioniert auch ohne installierte CLIs |
| `--tier`, `--effort` | Metadaten des Pakets überschreiben |

### Was das Skript prüft, bevor es startet

- Paketdatei und Ausführungsplan auflösbar
- `ai-run`-Block vorhanden und gültig (Tier und Effort aus der Werteliste)
- Etappennummer im gültigen Bereich
- Arbeitsverzeichnis sauber
- Branch existiert noch nicht (oder `--continue`)
- beide CLIs im `PATH`
- Container `api-tooling` läuft — sonst scheitert jede Validierung mitten im Lauf
- offene Entscheidungen und empfohlene Vorgängerpakete werden angezeigt

### Grenzen

Das Skript **übergibt** Modell und Effort und zeigt sie an. Es kann nicht nachprüfen, was
die CLI intern tatsächlich verwendet. Wer das sicher wissen will, prüft es in der
laufenden Sitzung.

Ebenso kann es nicht erzwingen, dass der Agent den caveman-ultra-Stil einhält oder eine
blockierte Etappe wirklich überspringt — beides steht als Anweisung im Prompt und wird
in der Kreuz-Review geprüft (Reviewpunkt 9).

---

## Abdeckungsnachweis

Maschineller Abgleich der 164 Befund-IDs aus Kapitel 14 gegen die 16 Arbeitspakete, durchgeführt
am 27.07.2026 nach Fertigstellung.

### Ergebnis

**164 von 164 Befunden sind zugeordnet.** Kein Befund ist unbehandelt.

### Befunde mit doppelter ID im Katalog

Kapitel 14 führt vier Sachverhalte unter zwei Kategorien. Sie werden **einmal** umgesetzt:

| IDs | Sachverhalt | Paket |
|---|---|---|
| F5 ≡ J5 | Autorisierungslogik in `Company\SurveyResource` | 11, Etappe 5 |
| A10 ≡ E2 | Leerer Exception-Handler und `APP_DEBUG` | 04, Etappe 3 · 08, Etappe 1 |
| B5 ≡ K2 | CI führt nur die Privacy-Suite aus | 09, Etappe 1 |
| C13 ≡ D2 | `COMPANY_OWNER` bei Umfragen — als Vertragsabweichung **und** als Autorisierungslücke geführt | 11, Etappe 1 |

### Testlücken K5–K9

Die Befunde K5 bis K9 sind **keine eigenständigen Arbeiten**, sondern Querverweise auf fehlende
Tests zu Befunden aus anderen Kategorien. Sie werden durch die dort geforderten Tests geschlossen:

| ID | Testlücke | Geschlossen durch |
|---|---|---|
| K5 | Grant-Diskrepanz Audit/Identity ungetestet | Paket 05, Etappe 1 (neuer Boundary-Test für `identity_rt`) |
| K6 | Nebenläufigkeit I1, I2, I3, I6 ungetestet | Paket 07 Etappe 3 · Paket 13 Etappe 3 · Paket 14 Etappe 1 · Paket 07 Etappe 2 |
| K7 | Autorisierungslücken D1, D2, D4, D7 ungetestet | Paket 06 Etappe 5 · Paket 11 Etappe 1 · Paket 12 Etappen 3 und 5 |
| K8 | Fehlerpfade E3, J17, J18, I10 ungetestet | Paket 02 Etappe 3 · Paket 13 Etappen 2 und 5 · Paket 15 Etappe 8 |
| K9 | Toter Code ungetestet | entfällt — der Code wird in Paket 15 entfernt |

K1 (Partner-Subsystem), K3 (Angular-CI) und K4 (Contract-Test) sind eigenständig und in den
Paketen 06, 09 und 08 zugeordnet.

### Befunde in Sammeletappen

Diese IDs erscheinen nicht einzeln in einer Paket-Kopfzeile, sondern als Bereichsangabe. Sie sind
im Fließtext des jeweiligen Pakets namentlich behandelt:

| Bereich | Paket | Etappe |
|---|---|---|
| G8–G11 | 15 | 1 und 3 |
| G23–G27 | 15 | 5 |
| J6–J11 | 15 | 6 und 8 |
| U1–U14 | 16 | 1 bis 4 |

### Nicht als Codeänderung umgesetzt

| ID | Grund |
|---|---|
| A6 | Tokenablage im `localStorage` — Architekturwechsel, bewusst nach Paket 16 verschoben |
| E7 | Locale und i18n — Produktentscheidung, Paket 16 Etappe 3 |
| G12 | Not-Implemented-Guards sind **korrekt** (ADR-003 D5) — nur dokumentieren, nicht entfernen |
| H6 | `teamBreakdown` — bestehende Entscheidung in `2026-06-02-13-clarify-team-breakdown-contract.md` wird respektiert |
| J1, J2, J12 | Strukturentscheidungen, kein Bug — Paket 16 Etappe 4 |

### Reproduktion des Abgleichs

```bash
cd docs/ai-tasks
grep -ho "^\*\*Befunde:\*\* .*" 2026-07-27-*.md \
  | sed 's/^\*\*Befunde:\*\* //' | tr ',' '\n' | tr -d ' ' \
  | grep -E '^[A-K][0-9]+$' | sort -u
```
