# Übergabe — Stand 31.07.2026

Ersteller: Björn Mohr (verlässt das Projekt)
Referenz-Commit `main`: siehe `git log -1 main`
Zuletzt in Confluence dokumentierter Commit: `56b4a53148d2ab9c59a74c0396b486671958fc49` (27.07.2026)

Dieses Dokument ist die Landkarte für die Nachfolge. Es beschreibt, **was
ungesichert ist**, **wo welches Wissen liegt** und **welche Fäden offen sind**.

---

## 1. SOFORT: ungesicherte Arbeit

Das ist der einzige Teil dieses Dokuments mit einer Deadline. Alles Übrige
ist nachholbar — das hier nicht, wenn die Maschine wegfällt.

### 1.1 Commits, die nur lokal existieren

`main` selbst ist **2 Commits vor `origin/main`**:

```
7e4b5c0 fix(scripts): review base was self-referential on --continue/--review-only
054e698 fix(scripts): headless review used Plan Mode, which waits for a human
```

Branches **ohne jedes Remote-Gegenstück** (existieren ausschließlich lokal):

| Branch | Commits über `main` | Letzter Commit | Bewertung |
|---|---|---|---|
| `findings/04-infrastructure-hardening-s2` | 12 | 2026-07-28 | **Aktive Arbeit. Höchste Priorität.** |
| `backup/04-s1-vor-umbau` | 7 | 2026-07-28 | Sicherungspunkt vor dem s1-Umbau |
| `demo/employee-lab-values-dashboard` | 27 | 2026-07-06 | Demo-Referenz, in Jira mehrfach zitiert |
| `Fix_Checkin_Manager_Company_Admin` | 2 | 2026-06-11 | Alt, vermutlich überholt |
| `eval/codex-system-measure-template-builder-v1` | 2 | 2026-06-11 | Eval-Artefakt |
| `Expand_Measurement_Handling` | 0 | 2026-06-11 | Enthält nichts über `main` — löschbar |

Zusätzlich nicht gepusht: `demo/gamification-badges` (2 Commits, u. a.
`Inproved AGENTS.md`).

**Zu tun:**

```bash
git push origin main
git push -u origin findings/04-infrastructure-hardening-s2
git push -u origin backup/04-s1-vor-umbau
git push -u origin demo/employee-lab-values-dashboard
git push -u origin Fix_Checkin_Manager_Company_Admin
git push -u origin eval/codex-system-measure-template-builder-v1
git push origin demo/gamification-badges
```

`Expand_Measurement_Handling` kann ersatzlos gelöscht werden.

### 1.2 Nicht gemergte Branches mit Remote

Diese sind gesichert, aber unentschieden. Jeder braucht eine Entscheidung:

| Branch | Commits | Letzter Commit | Empfehlung |
|---|---|---|---|
| `findings/04-infrastructure-hardening-s1` | 9 | 2026-07-28 | Vorstufe zu s2 — mit s2 zusammen entscheiden |
| `elyo-91/15a-runtime-boundary-defects` | 2 | 2026-07-26 | Runtime-Boundary-Defekte, ADR-003-Anpassung. Prüfen und mergen |
| `demo-ui` | 24 | 2026-07-19 | Demo-Strang |
| `demo/gamification-badges-redesign` | 20 | 2026-07-05 | Demo-Strang, Referenz für ELYO-158 |
| `demo/gamification-badges` | 19 | 2026-07-05 | Demo-Strang |
| `demo/new-employee-features` | 17 | 2026-07-05 | Demo-Strang |
| `demo/new-employer-dashboard` | 2 | 2026-07-04 | Demo-Strang |

Die `demo/*`-Branches sind laut ELYO-154 bis ELYO-158 ausdrücklich **keine
Produktionsbasis**, sondern UX-/Scope-Referenz. Sie sollten deshalb *nicht*
nach `main` gemergt, aber auch nicht gelöscht werden, solange die
Scope-Entscheidungen offen sind.

---

## 2. Sachlicher Befund: fehlerhafter Deviation-Record

`docs/ai-results/2026-07-28-04-s1-etappe1-deviations.md` behauptet:

> **Ist-Zustand:** Es gibt kein `.github`-Verzeichnis im Repository, an keiner
> Stelle. Kein CI-Workflow, keine `privacy.yml`, nichts zu ändern.

**Das ist falsch.** `.github/workflows/privacy.yml` ist versioniert und auf
*allen* relevanten Refs vorhanden — auch auf `56b4a53`, gegen das der Record
geschrieben wurde. Eingeführt mit Commit `d5e8fe6 test(privacy): add regression
suite`.

**Folge:** Die Etappe A9 hat `api-tooling` hinter das Compose-Profil `tools`
gelegt. Das `Makefile` wurde nachgezogen (`docker compose --profile tools up -d
api-tooling`). Der CI-Workflow wurde **nicht** angefasst, weil der Bearbeiter
ihn für nicht existent hielt.

**Einschätzung:** Der Workflow ruft
`docker compose up -d --build postgres redis api-tooling` — Compose aktiviert
das Profil eines Services, der explizit auf der Kommandozeile genannt wird.
Der Workflow läuft daher *vermutlich* weiter. Verifiziert wurde das nie.

**Zu tun:** Einen CI-Lauf auf `main` beobachten oder den Workflow zur
Sicherheit auf `--profile tools` umstellen. Den Deviation-Record korrigieren
(passiert mit diesem Commit).

---

## 3. Wo welches Wissen liegt

Confluence enthält die *Systemdokumentation* (36 Seiten, Space `ELYO`).
Das Repository enthält das *Arbeitswissen*. Beides zusammen ergibt erst das
Bild — wer nur Confluence liest, versteht nicht, wie hier gearbeitet wurde.

| Ort | Inhalt | Wichtig für |
|---|---|---|
| Confluence Space `ELYO`, Root-Seite 6488066 | Systemdoku Kap. 1–15 | Architekturverständnis |
| `docs/confluence-doku-index.md` | Seitenindex + Fortschreibungsregeln | Doku aktuell halten |
| `AGENTS.md` (Repo-Root, 12 KB) | Arbeitsregeln für AI-Agenten | **Der Einstiegspunkt** |
| `docs/ai-tasks/` (108 Dateien) | Aufgabenpakete, je mit Kontext, Regeln, Abnahmekriterien | Was warum gemacht wurde |
| `docs/ai-tasks/TEMPLATE.md` | Vorlage für neue Pakete | Weiterarbeiten |
| `docs/ai-prompts/` | Codex-Betriebsmodi (plan / patch / review) | AI-Workflow reproduzieren |
| `docs/ai-results/` | Ergebnis- und Abweichungsprotokolle der Läufe | Nachvollziehbarkeit |
| `docs/ai-reviews/` | Codex-Review-Ausgaben | Qualitätsnachweis |
| `docs/ai-context/` | Dauerhafte Leitplanken (Health-Data, API-Contract, Rollen) | **Vor jeder Änderung lesen** |
| `docs/adr-documents/` | ADR-001 bis ADR-003 | Verbindliche Architekturentscheidungen |
| `docs/decisions/`, `docs/further_docs/` | Entscheidungsbögen, Jira-Kommentarvorlagen | Historie zu ELYO-99/100/101/102 |
| `docs/privacy/`, `docs/security/` | DSFA-Vorprüfung, Tenant-Scope-Audit | Datenschutz-Nachweise |
| `scripts/run-ai-task.sh` (~1000 Zeilen) | Treiber für die AI-Läufe | Workflow ausführen |

### Der AI-Workflow in Kurzform

1. Ein Aufgabenpaket wird als Datei in `docs/ai-tasks/` beschrieben
   (Vorlage: `TEMPLATE.md`). Es enthält Kontext, Scope-Grenzen, Arbeitsregeln
   und Abnahmekriterien.
2. `scripts/run-ai-task.sh` startet den Agenten mit diesem Paket und einem
   Betriebsmodus aus `docs/ai-prompts/` (plan, patch oder review).
3. Der Agent arbeitet auf einem eigenen Branch (`findings/…`, `elyo-91/…`).
4. Abweichungen zwischen Paketbeschreibung und Realität werden **verpflichtend**
   in `docs/ai-results/` protokolliert, statt stillschweigend gelöst
   (siehe „Regel 2" in den Paketen).
5. Ein Codex-Review-Lauf erzeugt `docs/ai-reviews/…`.
6. Erst danach PR nach `main`.

Wer das nicht fortführen will, kann die Pakete trotzdem als Spezifikation
lesen — sie sind bewusst werkzeugunabhängig geschrieben.

---

## 4. Offene Fäden in Jira

Stand 31.07.2026. Details stehen als Kommentar am jeweiligen Vorgang.

| Vorgang | Status | Problem |
|---|---|---|
| ELYO-91 (Epic) | In Bearbeitung | Health Data Model Hardening. Kinder ELYO-104…111. Braucht neuen Owner. |
| ELYO-107 | In Überprüfung | Audit-Logging-Konzept. Konzept liegt in `docs/further_docs/audit-logging-concept.md`. Review offen. |
| ELYO-108 | In Überprüfung | Retention-/Löschkonzept. Retention-Matrix: `docs/further-docs/retention-matrix.md` (Bindestrich, siehe Abschnitt 6). Review offen. |
| ELYO-100 | In Überprüfung | Privacy-Zielarchitektur, bei Marc Sund. |
| ELYO-154…158 | Sprint Backlog | Scope-Entscheidungen, warten seit Wochen auf Reviewer-Feedback (Katharina Pietschke). Blockieren Sprint 3/4. |
| ELYO-162 | Produkt Backlog | Health-Dokumente Storage-Hardening. Kein Assignee. |

Die fünf Scope-Entscheidungen ELYO-154 bis ELYO-158 sind der kritischste
Block: sie sind als „DRAFT — bitte bestätigen" formuliert und ohne Bestätigung
kann die Umsetzung in Sprint 3/4 nicht starten.

---

## 5. Doku-Stand — besser als es aussieht, aber mit falschem Kopf

Der erste Eindruck („Doku steht auf `56b4a53`, main ist 13 Commits weiter")
täuscht. Nachgeprüft:

**Inhaltlich ist die Confluence-Doku aktuell.** Zwischen dem Doku-Merge
`a00ee63` (27.07.2026, 13:04) und `main` liegen 8 Commits, die
**ausschließlich** `docs/ai-tasks/`, `scripts/run-ai-task.sh`, `.gitignore` und
`docs/confluence-doku-index.md` berühren. Das sind alles Bereiche, die die
Root-Seite ausdrücklich aus dem Doku-Umfang ausschließt. Am dokumentierten
Code hat sich seit dem Doku-Abschluss **nichts** geändert.

**Aber die Kopfzeilen sind falsch.** Alle Seiten nennen als „Zuletzt
analysierter Commit" `56b4a53` (26.07., 21:08). Der Doku-Branch lief danach
aber noch weiter:

```
56b4a53  26.07. 21:08  docs(tasks): record task 17 validation blocker   ← zitiert
9f4473d  27.07. 13:01  docs(api): restore laravel and openapi operation parity
e4c0fba  27.07. 13:01  docs(elyo-91): close epic documentation and verification
a00ee63  27.07. 13:04  Merge pull request #33                            ← tatsächlicher Stand
```

`9f4473d` hat 13 Operationen zur OpenAPI ergänzt und 7 entfernt (54 → 59
Pfade). Die Doku beschreibt bereits den **neuen** Stand — Kapitel 6.1
dokumentiert `/health`, `/auth/logout`, `/partner/register` und
`/partner/documents`, die es auf `56b4a53` in der OpenAPI noch gar nicht gab.
Auch die Kennzahl „59 OpenAPI-Pfade" auf der Root-Seite passt zu `a00ee63`,
nicht zu `56b4a53`.

**Konsequenz:** Wer die Doku gegen den zitierten Commit prüft, findet
scheinbare Fehler, die keine sind. Der Kopf muss auf `a00ee63` korrigiert
werden. Die Root-Seite wurde am 31.07.2026 entsprechend angepasst; die
übrigen 35 Unterseiten tragen weiterhin `56b4a53` im Kopf.

**Echter künftiger Rückstand:** Der noch nicht gemergte Branch
`findings/04-infrastructure-hardening-s2` ändert `docker-compose.yml`,
`.env.example`, `Makefile` und `infra/postgres/initdb/`. Das betrifft
Kapitel 9 (Umgebungsvariablen) und 12 (Deployment) — **sobald gemergt**.

Die Fortschreibungsregeln stehen in `docs/confluence-doku-index.md`.

---

## 6. Bekannte Altlast: doppeltes Doku-Verzeichnis

`docs/further-docs/` (Bindestrich, enthält nur `retention-matrix.md`) und
`docs/further_docs/` (Unterstrich, 10 Dateien) existieren nebeneinander.

Das ist **bekannt und bereits entschieden**: Entscheidung U8 vom 27.07.2026
(`docs/ai-tasks/2026-07-27-entscheidungen.md`) legt fest:
`further-docs` → `further_docs`, `decisions` → `adr-documents`, Rest bleibt.

Umgesetzt wird das in **Paket 04, Etappe 6/8**
(`docs/ai-tasks/2026-07-27-04-infrastructure-hardening.md`, Befund B12).
Ich habe die Verschiebung bewusst **nicht** vorgezogen, damit sie im regulären
Paketlauf mit Deviation-Record und Review passiert und keinen Konflikt erzeugt.

Achtung beim Umsetzen: `docs/ai-tasks/2026-07-27-17-push-notifications.md`
(Zeile 320 f.) referenziert die Retention-Matrix noch unter dem alten Pfad.
