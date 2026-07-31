# Paket 04, Etappe 1 (A9) — deviation record

Stand geprüft: aktueller Branch `findings/04-infrastructure-hardening-s1`, gegen
Paket-Befund vom Stand `56b4a53`.

## Befund traf zu

`api-tooling` lief ohne `profiles:`-Eintrag, hielt alle vier Runtime-Rollen plus
`elyo_migrator` plus Mapping-Schlüsselmaterial, startete bei jedem
`docker compose up -d`. Nur ein Kommentar markierte die Absicht
("LOCAL DEVELOPMENT ONLY — never deploy this service."). Wie im Paket
beschrieben — keine Abweichung an dieser Stelle.

## Abweichung von der Paket-Beschreibung (Regel 2)

Paket nennt als mitzuziehenden Aufrufer: `.github/workflows/privacy.yml`.

**Ist-Zustand (urspüngliche, FALSCHE Feststellung):** Es gibt kein
`.github`-Verzeichnis im Repository, an keiner Stelle. Kein CI-Workflow, keine
`privacy.yml`, nichts zu ändern.

**Konsequenz (ursprünglich):** Dieser Teil der Etappe entfällt ersatzlos. Keine
Ersatzhandlung vorgenommen (Regel 2 — kein Ersatzproblem gesucht).

### KORREKTUR 31.07.2026

Die obige Feststellung ist sachlich falsch. `.github/workflows/privacy.yml` ist
versioniert und auf allen relevanten Refs vorhanden — auch auf `56b4a53`, gegen
das dieser Record geschrieben wurde. Eingeführt mit Commit
`d5e8fe6 test(privacy): add regression suite`. Nachprüfbar mit:

```
git ls-tree -r --name-only 56b4a53 -- .github
```

**Tatsächliche Konsequenz:** Der in der Paketbeschreibung genannte Aufrufer
wurde nicht mitgezogen. Der Workflow ruft

```
docker compose up -d --build postgres redis api-tooling
docker compose exec -T api-tooling ...
```

Compose aktiviert das Profil eines Services, der explizit auf der Kommandozeile
genannt wird — der Workflow läuft daher voraussichtlich weiter, weil
`api-tooling` beim `up` namentlich genannt ist und beim `exec` bereits läuft.
**Verifiziert wurde das nie.**

**Offen:** CI-Lauf auf `main` beobachten oder `privacy.yml` zur Sicherheit auf
`--profile tools` umstellen. Siehe `docs/UEBERGABE-2026-07-31.md`, Abschnitt 2.

## Verifiziertes Verhalten (Achtung-Punkt)

`docker compose run` aktiviert das Profil des Zielservices automatisch;
`exec` und `up` (ohne `--profile`) tun das nicht. Bestätigt per:

- Isoliertem Test mit einem Scratch-`docker-compose.yml` (zwei Services, einer
  mit `profiles: ["tools"]`): `up -d` ohne Profil-Flag ließ den gated Service
  aus; `exec` darauf schlug mit `service "tooled" is not running` fehl;
  `run --rm` startete ihn ohne Flag.
- Live am echten Stack: `api-tooling` gestoppt, `docker compose up -d`
  ausgeführt → `api-tooling` blieb `exited`, alle anderen Services liefen.
  `make test` (nutzt jetzt `docker compose --profile tools up -d api-tooling`
  vor dem `exec`) startete `api-tooling` und lief durch.

Damit war die im Handoff geforderte Verifikation nötig — reines Vertrauen auf
die Paket-Aussage hätte den Makefile-Fix nicht erzwungen, ohne den `make test`
nach der Profil-Änderung kaputt gewesen wäre.
