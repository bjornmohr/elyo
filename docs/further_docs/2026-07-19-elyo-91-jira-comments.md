# ELYO-91: Jira-Kommentare, Abweichungen und Abschlussnachweise

Stand: 2026-07-26. Reviewed Ausgangsstand:
`56b4a53` auf `elyo-91/17-docs-closure-and-verification`; umgesetzt wurden die
Prompts 01–16 und die zwei dokumentierten Task-17-Fix-forwards. Alle Ausgaben
sind sanitisiert: keine Credentials, personenbezogenen Daten oder Subject-IDs.

## Ready-to-paste: ELYO-91

> Die ELYO-91-Umsetzungsserie 01–16 ist technisch abgeschlossen und auf dem
> Runtime-Split end-to-end verifiziert. Vier getrennte PostgreSQL-Datenbanken,
> verschlüsseltes Mapping, subject-scoped Health-Daten, Runtime-Rollen,
> append-only Audit, Löschung/Retention, Laborwert-API und Privacy-Suite sind
> umgesetzt; Company-Wellbeing bleibt bewusst `reporting_pending`.
>
> | Akzeptanzkriterium | Nachweis |
> |---|---|
> | Health-Daten nur am `health_subject_id` | `HealthSchemaBoundaryTest`; Prompts 08/08a/11; `162bc85`, `efab7ca`, `6ca5a26` |
> | Mapping nicht trivial joinbar | `MappingService`, verschlüsselte/HMAC-Felder; `SourceBoundaryTest`, `MappingNonJoinabilityPrivacyTest`; `2b73ba2`, `f58c16e` |
> | Demo-Laborschema ausgeschlossen | produktive `LabMarker`-/`LabMarkerReading`-Modelle in Health; `LabMarkerSchemaTest`; `6ca5a26` |
> | Audit, Löschung, Retention und Freitext geregelt | `DatabaseAuditLoggerTest`, `AccountDeletionServiceTest`, `EnforceRetentionDeletionTest`; `note` mit 422 verboten; `1cfcc37`, `8a7ec90`, `04bb057` |
> | Blocker gelöst oder explizit offen | DSFA R1–R3 technisch grün; DSB-Kenntnisnahme nach ADR-002 §2.7 bleibt formaler Human-Gate |
>
> Abschlusslauf: 593 Tests/7618 Assertions, Boundary 23/111, Privacy 71/371,
> Deptrac 0/0/0, Runtime-Smoke grün, Angular-Build grün und OpenAPI-Parität
> 77/77. Bewusste Abweichungen D3/D4/D5/D7/D8 sind in ADR-003 und im
> Abschlussregister dokumentiert. Offen bleiben Reporting/2 Mapping-Operationen,
> Document-Storage-Hardening, Punkte/Surveys, rechtliche Retention und ELYO-144.

## Ready-to-paste: ELYO-104

> ELYO-104 ist umgesetzt: Postgres stellt getrennte Datenbanken und
> least-privilege Rollen bereit; Laravel nutzt domänengetrennte Connections und
> Migrationen. `MappingService` provisioniert nach dem Identity-Commit
> idempotent Subject plus Mapping; `elyo:provision-subjects` repariert
> Teilfehler ohne Identifikatoren in Logs.
>
> Kernevidenz: `infra/postgres/initdb/01-databases-and-roles.sh`,
> `ElyoMigrateFresh`, `MappingServiceTest`,
> `ProvisionMissingHealthSubjectsTest` und
> `DemoDataSeederSubjectProvisioningTest`; commits `f91567d`, `498125e`,
> `2b73ba2`, `2e1a125`. Der Fresh-Migrate/Seed-Lauf und die komplette Suite sind
> grün. Bewusste Grenze: nur drei der fünf identifiertragenden
> Mapping-Operationen sind aktiv; Reporting/DSR bleiben guard-gesichert offen
> (ADR-003 D5).

## Ready-to-paste: ELYO-105

> ELYO-105 ist einschließlich der mit ADR-003 D4 erweiterten HTTP-Schnittstelle
> umgesetzt. Laborwertkatalog und Messhistorie liegen in `elyo_health` am
> `health_subject_id`; Mitarbeiter können eigene Marker listen, Historie lesen,
> manuelle Messungen anlegen und eigene Messungen löschen.
>
> Kernevidenz: `LabMarkerService`, `LabMarkerSchemaTest`,
> `LabMarkerServiceTest`, `LabMarkerEndpointTest` und OpenAPI; commits
> `6ca5a26`, `ca54aa0`, `2bf197e`. Fremde Ressourcen liefern 404,
> Company/Admin/Partner 403; die Privacy-Suite leak-scannt alle vier Routen.
> Offene Provenienz- und fachliche Retention-Entscheidungen bleiben
> Folgethemen; Laborwerte sind nicht reportbar.

## Ready-to-paste: ELYO-106

> ELYO-106 ist umgesetzt: Quellcode-, Dependency-, DB-Rollen- und
> Runtime-Grenzen verhindern Identity↔Health-Joins und Company-Zugriff auf
> Mapping/Health. Ein Image startet als Identity-, Employee- oder
> Company-Runtime; nginx routet Pfade ohne Gateway oder Runtime-zu-Runtime-HTTP.
>
> Kernevidenz: `SourceBoundaryTest`, `PostgresRoleBoundaryTest`,
> `DeptracBoundaryTest`, `RuntimeProfileBootTest` und
> `infra/smoke-runtime-split.sh`; commits `1d3730a`, `63c7f0a`, `8370a59`,
> `e62628b`. Abschluss: Boundary 23/111, Deptrac 0/0/0 und Runtime-Smoke grün.
> Der Employee-Runtime wurde im Task-17-Fix-forward ausschließlich UPDATE auf
> Sanctum-Usage-Zeitstempel gewährt; alle anderen Identity-Schreibpfade bleiben
> negativ getestet.

## Ready-to-paste: ELYO-107

> ELYO-107 ist umgesetzt: Mapping-Zugriffe und Privacy-Lifecycle-Aktionen
> schreiben in eine separate Audit-Datenbank; Runtime-Rollen besitzen dort nur
> INSERT. Ein Event darf nie Identity- und Subject-Referenz gemeinsam
> enthalten, und ein Audit-Fehler lässt sicherheitskritische Mapping-Aktionen
> fail-closed scheitern.
>
> Kernevidenz: `DatabaseAuditLogger`, Audit-Migration,
> `DatabaseAuditLoggerTest`, `AuditInvariantPrivacyTest` und
> `PostgresRoleBoundaryTest`; commit `1cfcc37`. Full-, Boundary- und
> Privacy-Suite sind grün. Fachliche Aufbewahrung bleibt gemäß ADR-001 bei zwei
> Jahren; operative Archivierung ist außerhalb dieses Tickets.

## Ready-to-paste: ELYO-108

> ELYO-108 ist umgesetzt: Eine versionierte Retention-Matrix, ein dry-run-first
> Retention-Command und ein wiederholbarer Account-Löschdienst decken die
> subject-scoped Health-Bestände ab. Die Löschreihenfolge entfernt Health und
> Identity vor dem endgültigen Mapping-Tombstone; Fehler bleiben reparierbar
> und werden ohne Identifikatoren berichtet.
>
> Kernevidenz: `AccountDeletionService`,
> `AccountDeletionServiceTest`, `EnforceRetention`,
> `EnforceRetentionDeletionTest` und `docs/further-docs/retention-matrix.md`;
> commit `8a7ec90`. Abschlusslauf grün. Offen ist die rechtliche/fachliche
> Festlegung der Fristen je Datenkategorie; vorhandene Werte sind technische
> Defaults, keine Rechtsentscheidung.

## Ready-to-paste: ELYO-109

> Entscheidung umgesetzt: Check-in-`note` ist vollständig entfernt und wird
> bei jeder Mitlieferung mit 422/`prohibited` abgelehnt. So kann ein Alt-Client
> Freitext weder speichern noch scheinbar erfolgreich und unbemerkt verlieren.
>
> Kernevidenz: `CheckinRequest`, `EmployeeTest`, Health-Schema, Angular
> Check-in-Spec und OpenAPI; commits `162bc85`, `04bb057`, `c8475ce`.
> Entscheidungsnachweis:
> `docs/further_docs/elyo-109-checkin-note-decision.md`. Eine spätere
> Wiedereinführung ist nur additiv nach Zweck-/Minimierungsentscheidung,
> Privacy-Re-Review, Vertrag und negativen Company-Leak-Tests zulässig.

## Ready-to-paste: ELYO-110

> ELYO-110 ist gemäß ADR-003 D3 implementiert statt nur bewertet.
> `wellbeing_entries` liegt vollständig in `elyo_health` am
> `health_subject_id`, verwendet mood/energy/stress 1–5, berechnet den Score auf
> derselben Skala und enthält keinen Freitext.
>
> Kernevidenz: `HealthSchemaBoundaryTest`, `EmployeeTest`, Angular-Specs und
> `docs/further_docs/elyo-110-wellbeing-health-subject-migration.md`; commits
> `162bc85`, `187fc41`, `04bb057`, `efab7ca`, `c8475ce`. Streaks bleiben
> funktionsfähig; Punkte bleiben Identity-seitig. Company-Wellbeing liefert
> ausschließlich `reporting_pending`; Punkte/Surveys und Document-Storage-
> Hardening sind bewusst als Folgeentscheidungen offen.

## Ready-to-paste: ELYO-111

> ELYO-111 ist umgesetzt: Die eigenständige PostgreSQL-basierte Privacy-Suite
> entdeckt Company/Admin-Routen dynamisch, verlangt pro Route einen echten 2xx,
> scannt auch Fehlerantworten zuerst und verweigert Health-, Freitext-, Lab-,
> Subject- und nicht freigegebene Aggregate-Muster.
>
> Kernevidenz: 24 Company- plus 24 Admin-Routen, vier Lab-Routen,
> `CompanyAdminRoutePrivacyTest`, `HealthLeakAssertionsTest`,
> `LabAccessPrivacyTest`, `MappingNonJoinabilityPrivacyTest` und
> `CompanyWellbeingPrivacyTest`; commits `d5e8fe6` bis `7693691`.
> Abschluss: 71 Tests/371 Assertions grün. ELYO-144 bleibt für Reporting-Worker,
> künftige allowlisted Metriken und die vollständige Snapshot-Privacy-Abdeckung
> offen.

## Validierungsbatterie

| Befehl | Exit | Sanitisiertes, entscheidendes Terminal-Ergebnis |
|---|---:|---|
| `docker compose ps` | 0 | `10 required services Up; postgres/mailpit healthy` |
| `docker compose config` | 0 | `Compose configuration rendered successfully; values omitted.` |
| `docker compose run --rm migrate` | 0 | `All four databases migrated fresh and seeded.` |
| `docker compose exec api-tooling php artisan test` | 0 | `Tests:    593 passed (7618 assertions)` |
| `docker compose exec api-tooling php artisan test --testsuite=boundary` | 0 | `Tests:    23 passed (111 assertions)` |
| `docker compose exec api-tooling php artisan test --testsuite=privacy` | 0 | `Tests:    71 passed (371 assertions)` |
| `docker compose exec api-tooling composer deptrac` | 0 | `Violations 0, Warnings 0, Errors 0` |
| `bash infra/smoke-runtime-split.sh` | 0 | `runtime split smoke test passed` |
| `docker compose exec web npm run build` | 0 | `Application bundle generation complete. [2.027 seconds]` |
| `docker compose exec api-tooling php artisan route:list` | 0 | `Showing [82] routes` |
| Operation-Level Laravel/OpenAPI-Audit | 0 | `Laravel API operations: 77; OpenAPI operations: 77; Missing from OpenAPI: 0; Stale in OpenAPI: 0` |
| Semantischer OpenAPI-Audit | 0 | `OpenAPI semantic schema audit: pass` |

Reproduzierbare Audit-Befehle:

```bash
docker compose exec api-tooling php artisan route:list --json |
  ruby -ryaml -rjson -e 'routes=JSON.parse(STDIN.read); spec=YAML.load_file(ARGV[0]); canon=->(p){"/"+p.sub(%r{^/?api/?},"").split("/").reject(&:empty?).map{|s| s.match?(/^\{[^}]+\}$/) ? "{}" : s}.join("/")}; laravel={}; routes.each{|r| next unless r["uri"].start_with?("api/"); r["method"].split("|").reject{|m| m=="HEAD" || m=="OPTIONS"}.each{|m| laravel[[m.downcase,canon.call(r["uri"])]]="#{m} /#{r["uri"]}"}}; operations=%w[get post put patch delete options head trace]; openapi={}; spec.fetch("paths",{}).each{|path,item| item.each_key{|m| next unless operations.include?(m.to_s.downcase); openapi[[m.to_s.downcase,canon.call(path)]]="#{m.to_s.upcase} #{path}"}}; missing=laravel.keys-openapi.keys; stale=openapi.keys-laravel.keys; puts "Laravel API operations: #{laravel.length}"; puts "OpenAPI operations: #{openapi.length}"; puts "Missing from OpenAPI: #{missing.length}"; puts "Stale in OpenAPI: #{stale.length}"; exit(missing.empty? && stale.empty? ? 0 : 1)' docs/api/openapi.yaml

ruby -ryaml -e 's=YAML.load_file(ARGV[0]); c=s.fetch("components").fetch("schemas"); w=c.fetch("WellbeingEntry"); %w[mood stress energy].each{|k| p=w.fetch("properties").fetch(k); raise k unless p["minimum"]==1 && p["maximum"]==5}; raise "response note" if w.fetch("properties").key?("note"); q=s.fetch("paths").fetch("/employee/checkin").fetch("post").fetch("requestBody").fetch("content").fetch("application/json").fetch("schema"); %w[mood stress energy].each{|k| p=q.fetch("properties").fetch(k); raise k unless p["minimum"]==1 && p["maximum"]==5}; raise "note not prohibited" unless q.fetch("properties").fetch("note").key?("not"); labs=s.fetch("paths").select{|path,_| path.start_with?("/employee/lab-markers")}.sum{|_,item| item.keys.count{|m| %w[get post delete].include?(m)}}; raise "lab operations" unless labs==4; raise "pending" unless c.fetch("ReportingPendingBlock").fetch("properties").fetch("status").fetch("enum")==["reporting_pending"]; %w[WellbeingEntry EmployeeDocument LabMarkerReading LabMarkerHistoryEntry].each{|name| id=c.fetch(name).fetch("properties").fetch("id"); raise "#{name} id" unless id["type"]=="string" && id.fetch("description","").include?("ULID")}; puts "OpenAPI semantic schema audit: pass"' docs/api/openapi.yaml
```

Der OpenAPI-Audit prüfte zusätzlich explizit: Check-in-Skala 1–5,
`note`-Entfernung, vier Lab-Operationen, `reporting_pending`-Blöcke und opaque
ULID-IDs. Der erste semantische Audit-Aufruf enthielt einen lokalen
Ruby-Syntaxfehler; der korrigierte, in der Tabelle dokumentierte Aufruf lief
unmittelbar danach erfolgreich und änderte kein Produktartefakt.

## Register bewusster Abweichungen

| Referenz | Abweichung | Begründung |
|---|---|---|
| ADR-003 D3 | ELYO-110 wurde implementiert statt nur bewertet. | Das frische Vorproduktionsschema erlaubte die vollständige, konsistente Health-Migration ohne Legacy-Datenpfad. |
| ADR-003 D4 | ELYO-105 umfasst auch vier Employee-Lab-HTTP-Operationen. | Modell und bindender Client-Vertrag wurden in einem subject-scoped Slice geliefert. |
| ADR-003 D5 | Drei von fünf Mapping-Operationen sind aktiv; Reporting/DSR werfen einen Guard. | Noch nicht existierende Runtimes erhalten keinen vorgezogenen Identifier-Zugriff. |
| ADR-003 D7 | Company-Wellbeing liefert `reporting_pending` statt Live-Aggregaten. | Ein Interim-Lesepfad aus Health würde ADR-001 §2.5 und die Runtime-Grenze brechen. |
| ADR-003 D8 | Anamnese, Dokumentmetadaten und Wearables wurden jetzt verschoben; Punkte/Surveys nicht. | Health-Tabellen wurden im Fresh-Schema konsistent, während Punkte/Surveys wegen Company-Abhängigkeiten einen eigenen Domain-Entscheid brauchen. |
| Task 17 / `AGENTS.md` | Neue Closure-Dokumente liegen in `docs/further_docs/` statt im Task-Pfad `docs/further-docs/`. | Die aktuelle repository-weite Ablagekonvention ist höherrangig; es wurde kein zweiter Dokumentationsbaum erzeugt. |
| Task-17-Fix-forward | Regression an Sanctum-Token-Touch wurde test-first in Migration und Boundary-Test behoben. | Der geforderte Runtime-Smoke deckte einen echten 500er auf; nur zwei Usage-Zeitstempelspalten erhielten UPDATE. |
| Task-17-Fix-forward | OpenAPI wurde von 71 auf 77 aktuelle Laravel-Operationen synchronisiert. | Die verpflichtende bidirektionale Parität war rot; ein eigenes Micro-Task dokumentiert die rein vertragliche Reparatur. |

## Follow-up-Issue-Drafts

### Reporting-Epic: verbleibende Mapping-Operationen und Quartals-Snapshots

Implementiere `resolveReportingCohort` zusammen mit Reporting-Worker,
`reporting_tenant_id` und unveränderlichen Quartals-Snapshots. Die Operation
darf nur einen suppressionsfähigen Kohortenbezug liefern, benötigt Purpose-Code,
Audit und eine eigene Runtime-Rolle ohne Identity- oder Company-API-Umweg.
Ersetze `reporting_pending` erst nach Boundary-, Privacy- und
Suppression-Tests.

### Health-Dokumente: Storage-Hardening nach ADR-001 §2.9

Isoliere Health-Dokumente in einem Bucket mit eigenen Credentials und
pseudonymen Pfaden. Ergänze Metadatenbereinigung, Typ-Allowlist, Virenscan und
kurzlebige signierte URLs, ohne Namen oder E-Mail-Adressen in Pfaden oder Logs.
Beweise Owner-Isolation, Ablauf der URLs, fehlerbereinigte Löschung und
Nichtzugriff durch Company/Admin.

### Punkte und Surveys: Domain-Entscheid nach ADR-001 §2.6

Entscheide gemeinsam für Punkte, Streaks, Badges und Survey-Antworten, welche
Verhaltensdaten in die Health-Domäne wechseln. Berücksichtige bestehende
Company-Aggregationen, Membership-Zeitlogik und das Verbot direkter
Health-Lesepfade aus Company. Liefere ADR, Migrationsplan, OpenAPI-Auswirkung und
negative Boundary-/Privacy-Tests vor Implementierung.

### Retention: rechtliche Fristen je Datenkategorie festlegen

Lasse Laborwerte, Check-ins, Anamnese, Dokumente, Wearables, Mapping, Audit und
Backups fachlich-rechtlich bewerten. Ersetze technische Platzhalter nur durch
freigegebene Fristen und dokumentiere Trigger, Sperren, Export und Löschreihenfolge.
Aktualisiere Retention-Matrix, DSFA, Commands und deterministische
Zeitgrenzen-Tests gemeinsam.

### ELYO-144: Privacy-Suite für Reporting vollständig machen

Erweitere die Privacy-Suite mit dem realen Reporting-Worker und den
unveränderlichen Quartals-Snapshots. Jede neue Metrik benötigt explizite
Allowlist, globale Schwelle, metrikspezifische Schwelle, Bucket-Suppression und
Response-State-Prüfung. Halte Laborwerte, Freitext, Individualdatensätze und
Subject-Identifier ohne Ausnahme nicht reportbar.

## DSB-Übergabe

Der technische Teil von ADR-002 §2.7 ist für DSFA R1–R3 reproduzierbar grün.
Der Blocker bleibt dennoch formal offen, bis die Datenschutz-Verantwortliche
die DSFA-Vorprüfung ohne Veto zur Kenntnis genommen und der Übergabevermerk
Datum, Beteiligte sowie gegebenenfalls terminierte Bedingungen enthält. Das ist
der einzige verbleibende Blocker für die DSB-Handover-Closure; die rechtlichen
Entscheidungen in den Follow-ups bleiben davon getrennte offene Arbeit.
