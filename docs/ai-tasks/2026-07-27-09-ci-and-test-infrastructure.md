# Paket 09: CI und Testinfrastruktur

**Priorität:** 2 · **Bereich:** CI + Tests · **Etappen:** 5
**Befunde:** B5, J34, K1, K2, K3

```ai-run
complexity:        niedrig
implement_tier:    standard
implement_effort:  medium
review_tier:       standard
review_effort:     low
blocked_by:        -
depends_on:        04
```

## Goal

Die vorhandene, sehr gute Testinfrastruktur automatisiert ausführen — sie ist heute weitgehend
wirkungslos, weil sie nicht läuft.

## Context

Die Testqualität ist stellenweise vorbildlich: Die Boundary-Suite baut **echte
PostgreSQL-Verbindungen unter fremden Rollen** auf und prüft mit `SELECT current_user` gegen; die
Privacy-Suite enthält **Meta-Tests, die ihre eigenen Leak-Detektoren prüfen**; und
`CompanyAdminRoutePrivacyTest` bricht, sobald eine neue Company-Route ohne Testdefinition
hinzukommt.

**Nur läuft davon fast nichts.** `.github/workflows/privacy.yml` ist die einzige Pipeline und
führt aus:

```yaml
docker compose exec -T api-tooling php artisan test --testsuite=privacy
```

Das sind 41 von etwa 540 Testmethoden. Nicht in der CI:

| Nicht ausgeführt | Umfang |
|---|---|
| Suite `Unit` | 19 Methoden |
| Suite `Feature` | 405 Methoden |
| Suite `boundary` | 18 Methoden |
| `composer deptrac` | 8 Layer-Regeln |
| `check-grants.sh` | Rollen-Grenzen |
| `smoke-runtime-split.sh` | Runtime-Split |
| `verify-migration-restructure.sh` | Schema-/Routenparität |
| Laravel Pint | Codeformat |
| **Sämtliche Angular-Tests** | 17 Spec-Dateien |
| Angular-Build | — |

Ein Pull Request, der die Feature-Suite bricht, Deptrac verletzt oder das Frontend nicht mehr
kompilieren lässt, wird **nicht erkannt**.

## Umsetzung in Etappen

### Etappe 1 — Backend-Suiten und Deptrac aufnehmen (B5, K2)

- Suiten `Unit`, `Feature`, `boundary` ergänzen.
- `composer deptrac` als eigenen Schritt — es ist der statische Architekturwächter.
- Entscheiden: ein Workflow mit mehreren Jobs oder mehrere Workflows. Die Privacy-Stufe hat heute
  ein Timeout von 20 Minuten; die Laufzeit der übrigen Suiten ist zu ermitteln.
- **Achtung:** Paket 04, Etappe 1 legt `api-tooling` hinter das Profil `tools`. Falls bereits
  umgesetzt, muss die CI `--profile tools` verwenden.
- **Abnahme:** Alle vier Suiten und Deptrac laufen; Laufzeit je Job dokumentiert.

### Etappe 2 — Skript-basierte Prüfungen aufnehmen (B5)

- `infra/postgres/check-grants.sh` — braucht nur den Postgres-Container, der ohnehin startet.
- Laravel Pint als eigener, schneller Job (`--test`, **kein** Auto-Fix in der CI).
- `infra/smoke-runtime-split.sh` bewerten: Es braucht den **vollständigen** Stack inklusive nginx
  und aller drei Runtime-Container. Falls die Laufzeit zu hoch ist, als nicht blockierenden Job
  oder als geplanten Lauf einrichten — Entscheidung begründen.
- `tests/scripts/verify-migration-restructure.sh` bewerten: Es vergleicht gegen den
  Vor-Restrukturierungs-Commit `10cd1c6`. Prüfen, ob das dauerhaft sinnvoll ist oder ob das Skript
  seinen Zweck erfüllt hat.
- **Abnahme:** Jede Prüfung läuft oder ist mit Begründung ausgenommen.

### Etappe 3 — Angular-Tests und Build aufnehmen (K3)

- Job mit `npm ci` und `npm test` in `apps/web-angular`. Node-Version an
  `apps/web-angular/Dockerfile` (`node:22`) ausrichten.
- **Zuerst verifizieren, dass die 17 vorhandenen Specs überhaupt grün sind** — sie liefen nie
  automatisiert. Fehlschlagende Specs **auflisten, nicht löschen oder überspringen**.
- `ng build` als Schritt aufnehmen, damit Kompilierfehler auffallen. Die Budgets aus `angular.json`
  (500 kB Warnung / 1 MB Fehler) gelten dabei.
- **Abnahme:** Ergebnis des ersten Laufs dokumentiert; Build läuft.

### Etappe 4 — Fehlende Factories ergänzen (J34)

Zwölf Modelle nutzen `HasFactory`, haben aber **keine Factory**:

`Partner`, `InviteToken`, `UserPoints`, `PointTransaction`, `PointSetting`, `PushSubscription`,
`NotificationPreference`, `Health\AnamnesisProfile`, `Health\UserDocument`, `Health\HealthDocument`,
`Health\WearableConnection`, `Health\WearableSync`

Tests erzeugen diese Datensätze heute per `::create()`, was die Testlesbarkeit senkt.

- Factories für die Modelle ergänzen, die tatsächlich getestet werden. Für ruhende Modelle
  (`WearableConnection`, `WearableSync`, `HealthDocument`, `PushSubscription`,
  `NotificationPreference`) erst nach der Entscheidung aus Paket 02 bzw. 15 — sonst entstehen
  Factories für Code, der entfernt wird.
- **`PartnerFactory` ist Voraussetzung für Paket 06, Etappe 1** — dort mit anlegen oder hier
  vorziehen.
- **Achtung:** Health-Factories müssen auf der Connection `health` arbeiten und ein
  `HealthSubject` voraussetzen. Vorbild: `database/factories/Health/WellbeingEntryFactory.php`.
- **Abnahme:** Keine Factory für Code, der zur Entfernung ansteht; bestehende Tests nutzen sie.

### Etappe 5 — Contract-Test einhängen (K4)

- Die neue Suite aus Paket 08, Etappe 7 in die CI aufnehmen.
- Falls Paket 08 noch nicht umgesetzt ist: diese Etappe zurückstellen und im Handoff vermerken.
- **Abnahme:** Contract-Test läuft in der CI.

## Out of Scope

- Neue fachliche Tests schreiben — die gehören zu den jeweiligen Fachpaketen
- Deployment-Pipeline (Paket 04, Etappe 8)
- Testabdeckung des Partner-Subsystems (Paket 06, Etappe 1)

## Hard constraints

- **Die bestehende Privacy-Stufe darf nicht schwächer werden**
- `docker compose down --volumes` im `always()`-Schritt beibehalten
- Fehlschlagende Bestandstests werden **gemeldet, nicht entfernt oder übersprungen**
- Kein `migrate:fresh` außerhalb der Testinfrastruktur

## Review-Checkliste

- [ ] Alle vier Backend-Suiten laufen
- [ ] Deptrac läuft
- [ ] Angular-Tests und Build laufen
- [ ] Ergebnis des ersten Angular-Laufs ist dokumentiert, keine Spec wurde stillschweigend entfernt
- [ ] Laufzeit je Job ist dokumentiert
- [ ] Ausgenommene Prüfungen sind begründet
- [ ] Keine Factory für Code, der zur Entfernung ansteht
- [ ] Abhängigkeit zu Paket 04, Etappe 1 (`--profile tools`) berücksichtigt
- [ ] Kapitel 11 und 12.6 der Dokumentation aktualisiert

## Expected output

- Neue und geänderte Workflow-Dateien
- Gemessene Laufzeit je Job
- Ergebnis des ersten Angular-Testlaufs: welche Specs grün, welche rot
- Liste der Komponenten ohne Test als Grundlage für Folgearbeit
- Entscheidungen zu `smoke-runtime-split.sh` und `verify-migration-restructure.sh`
- Neue Factories
