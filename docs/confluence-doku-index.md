# Confluence: Technische Dokumentation — Seitenindex

Space **ELYO** · Site `https://elyo.atlassian.net/wiki`
Erstellt: 26./27.07.2026 · 36 Seiten

## Dokumentierter Commit — korrigiert am 31.07.2026

**Tatsächlich dokumentierter Stand:** `a00ee63` (Merge PR #33, 27.07.2026 13:04)
bzw. inhaltlich `e4c0fba`.

**In den Seitenköpfen genannt:** `56b4a53148d2ab9c59a74c0396b486671958fc49`
(26.07.2026 21:08) — **veraltet**.

Der Doku-Branch `elyo-91/17-docs-closure-and-verification` lief nach `56b4a53`
noch weiter. Insbesondere hat `9f4473d docs(api): restore laravel and openapi
operation parity` die OpenAPI von 54 auf 59 Pfade gebracht (13 ergänzt, 7
entfernt). Die Confluence-Seiten beschreiben bereits diesen **neuen** Stand —
Kapitel 6.1 dokumentiert `/health`, `/auth/logout`, `/partner/register` und
`/partner/documents`, die auf `56b4a53` in der OpenAPI noch fehlten.

Wer die Doku gegen `56b4a53` prüft, findet daher scheinbare Fehler, die keine
sind. Immer gegen `a00ee63` prüfen.

Die Root-Seite (6488066) wurde am 31.07.2026 korrigiert. Die übrigen 35
Unterseiten tragen weiterhin `56b4a53` im Kopf — offene Aufgabe.

Zwischen `a00ee63` und `main` (`7e4b5c0`) liegen 8 Commits, die ausschließlich
`docs/ai-tasks/`, `scripts/run-ai-task.sh`, `.gitignore` und diese Datei
berühren — alles außerhalb des Doku-Umfangs. **Am dokumentierten Code hat sich
seither nichts geändert.**

Siehe `docs/UEBERGABE-2026-07-31.md`, Abschnitt 5.

## Struktur

| Kapitel | Seite | ID | Link |
|---|---|---|---|
| — | Technische Dokumentation (Root) | 6488066 | /spaces/ELYO/pages/6488066 |
| 1 | Systemüberblick | 6488087 | /spaces/ELYO/pages/6488087 |
| 2 | Repository-Struktur | 6258690 | /spaces/ELYO/pages/6258690 |
| 3 | Laufzeitarchitektur | 8945666 | /spaces/ELYO/pages/8945666 |
| 4 | Backend | 8978433 | /spaces/ELYO/pages/8978433 |
| 4.1 | Einstiegspunkte, Routing und HTTP-Lifecycle | 8945687 | |
| 4.2 | Authentifizierung und Autorisierung | 9011201 | |
| 4.3 | Modul: Auth und Einladungen | 9043969 | |
| 4.4 | Modul: Employee-Portal | 9076737 | |
| 4.5 | Modul: Company-Portal | 9109505 | |
| 4.6 | Modul: Platform-Admin | 9076757 | |
| 4.7 | Modul: Partner | 9142273 | |
| 4.8 | Modul: Privacy und Mapping-Domäne | 9175041 | |
| 4.9 | Modul: Health-Domäne | 9207809 | |
| 4.10 | Hintergrundverarbeitung, Scheduler und CLI | 8978454 | |
| 4.11 | Persistenz und Datenbankzugriff | 9240577 | |
| 4.12 | Externe Integrationen | 8978474 | |
| 5 | Frontend | 9273345 | |
| 5.1 | Feature-Seiten: Employee | 9732097 | |
| 5.2 | Feature-Seiten: Company | 9240598 | |
| 5.3 | Feature-Seiten: Admin, Auth und Partner | 9764865 | |
| 6 | API-Referenz | 9011221 | |
| 6.1 | Endpunkte: Auth, Health und Partner | 9797633 | |
| 6.2 | Endpunkte: Employee | 9601034 | |
| 6.3 | Endpunkte: Company | 9797653 | |
| 6.4 | Endpunkte: Platform-Admin | 9109543 | |
| 7 | Datenbank und Datenmodell | 9207829 | |
| 7.1 | Identity-Domäne | 9306140 | |
| 7.2 | Health-Domäne | 9207857 | |
| 7.3 | Mapping- und Audit-Domäne | 9371695 | |
| 8 | Events, Jobs und asynchrone Flows | 9306113 | |
| 9 | Konfiguration und Umgebungsvariablen | 8978494 | |
| 10 | End-to-End-Feature-Flows | 9338881 | |
| 11 | Tests und Validierung | 9043989 | |
| 12 | Deployment und lokale Entwicklung | 9371649 | |
| 13 | Querschnittsthemen | 9338901 | |
| 14 | Bekannte Inkonsistenzen und technische Risiken | 6455299 | |
| 15 | Dokumentations-Coverage-Report | 9404417 | |

## Fortschreibung

| Auslöser | Zu aktualisieren |
|---|---|
| Neue Route | 6.1–6.4, ggf. 4.x, 15 |
| Neue Tabelle oder Migration | 7.1–7.3, ggf. 4.11 |
| Neue Umgebungsvariable | 9 |
| Neue Frontend-Seite | 5.1–5.3, ggf. 6.x |
| Behobener Befund aus Kapitel 14 | 14 + betroffene Fachseite |
| Neuer Commit auf main | Commit-Hash in allen Kopfzeilen |

Die drei überschriebenen Vorgängerseiten (Stand ELYO-91 Task 04, 19.07.2026) sind über die
Confluence-Versionshistorie von 6488066, 6258690, 6488087 und 6455299 erreichbar.
