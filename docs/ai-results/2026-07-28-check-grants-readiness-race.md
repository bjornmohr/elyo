# Fund: `make check-grants` läuft in einen Startup-Wettlauf

**Nicht Teil von Etappe 2 (A14).** A14 ändert nur Passwörter; dieser Fund betrifft
Testinfrastruktur und ist unabhängig davon aufgetreten, als die Passwortänderung einen
Volume-Reset nötig machte.

## Symptom

Nach `docker compose down -v && docker compose up -d postgres` unmittelbar gefolgt von
`make check-grants`: drei falsche `FAIL`-Zeilen bei genau den positiven Prüfungen
(`elyo_mapping_svc can connect to elyo_subject_mapping`, `elyo_employee_rt can connect to
elyo_health`, `elyo_employee_rt can INSERT into audit`). Mit einem manuellen `sleep` vor dem
Check verschwinden alle drei.

## Ursache

`docker compose up -d postgres` kehrt zurück, sobald der Container **startet** — nicht wenn
`infra/postgres/initdb/01-databases-and-roles.sh` fertig gelaufen ist. Das offizielle
Postgres-Image führt `docker-entrypoint-initdb.d`-Skripte über eine temporäre interne Instanz
aus; die reguläre, extern erreichbare Instanz (und damit der `pg_isready`-Healthcheck) startet
erst danach.

`make test` und `make deptrac` sind gegen genau dieses Problem bereits abgesichert:
`api-tooling` hat `depends_on: *api-depends` → `postgres: condition: service_healthy`. Compose
startet `api-tooling` gar nicht, bevor der Healthcheck grün ist. `check-grants` ruft
`docker compose exec -T postgres` jedoch **direkt** auf, ohne über diese Abhängigkeitskette zu
laufen — der einzige Pfad im Makefile, der das tut.

## Fix

`Makefile`, Target `check-grants`:

```diff
 check-grants: ## Assert the PostgreSQL role boundaries created by the initdb script
+	docker compose up -d --wait postgres
 	bash infra/postgres/check-grants.sh
```

`--wait` lässt Compose auf den bereits vorhandenen Healthcheck warten, bevor der Befehl
zurückkehrt. Keine Änderung an `check-grants.sh`, `docker-compose.yml` oder der Grant-Logik
selbst nötig — die Rollen und Grants waren die ganze Zeit korrekt, das Problem lag ausschließlich
im Timing des Aufrufs.

## Abnahme

```
docker compose down -v
docker compose up -d postgres
make check-grants
```

Ergebnis: alle acht Prüfungen `PASS`, ohne manuellen `sleep`.

## Einordnung

Gehört fachlich zu Paket 04 (Infrastruktur- und Betriebshärtung), aber zu keiner der acht
bestehenden Etappen. Als eigener Fund festgehalten statt in Etappe 1 (A9) oder 2 (A14)
gequetscht — beide haben einen anderen, klar abgegrenzten Gegenstand.
