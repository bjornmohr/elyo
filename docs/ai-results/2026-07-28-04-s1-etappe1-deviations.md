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

**Ist-Zustand:** Es gibt kein `.github`-Verzeichnis im Repository, an keiner
Stelle. Kein CI-Workflow, keine `privacy.yml`, nichts zu ändern. Datei nie
angelegt oder bereits entfernt — genauer Zeitpunkt aus dem Diff nicht
rekonstruierbar, da kein Commit im sichtbaren Log ein `.github/` je
berührt.

**Konsequenz:** Dieser Teil der Etappe entfällt ersatzlos. Keine
Ersatzhandlung vorgenommen (Regel 2 — kein Ersatzproblem gesucht).

## Verifiziertes Verhalten (Achtung-Punkt)

`docker compose run` aktiviert das Profil des Zielservices automatisch;
`exec` und `up` (ohne `--profile`) tun das nicht. Bestätigt per:

- Isoliertem Test mit einem Scratch-`docker-compose.yml` (zwei Services, einer
  mit `profiles: ["tools"]"`): `up -d` ohne Profil-Flag ließ den gated Service
  aus; `exec` darauf schlug mit `service "tooled" is not running` fehl;
  `run --rm` startete ihn ohne Flag.
- Live am echten Stack: `api-tooling` gestoppt, `docker compose up -d`
  ausgeführt → `api-tooling` blieb `exited`, alle anderen Services liefen.
  `make test` (nutzt jetzt `docker compose --profile tools up -d api-tooling`
  vor dem `exec`) startete `api-tooling` und lief durch.

Damit war die im Handoff geforderte Verifikation nötig — reines Vertrauen auf
die Paket-Aussage hätte den Makefile-Fix nicht erzwungen, ohne den `make test`
nach der Profil-Änderung kaputt gewesen wäre.
