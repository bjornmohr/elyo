# Paket 12: Teams und Manager-Berechtigungen

**Priorität:** 3 · **Bereich:** Backend + Frontend · **Etappen:** 6
**Befunde:** D3, D4, D7, D8, F1, F2, F8, H11, I9

```ai-run
complexity:        mittel
implement_tier:    standard
implement_effort:  high
review_tier:       standard
review_effort:     medium
blocked_by:        U4
depends_on:        -
```

## Goal

Die siebenfach duplizierte Manager-Prüfung auf eine Implementierung zurückführen und die
widersprüchlichen Annahmen über Manager-Teams auflösen.

**Dieses Paket zuerst innerhalb Priorität 3** — die entduplizierte Prüfung wird von den
Paketen 11, 13 und 14 verwendet.

## Context

**Dieselbe Regel steht siebenmal im Code:**

```php
$user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER])
```

| Ort | Form |
|---|---|
| `TeamLayerGuard::isManagerOnly()` | kanonisch, mit `loadMissing('roles')` |
| `InviteTeamValidator::isManagerOnly()` | wortgleich, **ohne** `loadMissing` |
| `CompanySurveyController::isManagerOnly()` | privat, wortgleich |
| `CompanyController::dashboard()` | inline |
| `ReportController::index()` | inline |
| `TeamController` | 4× inline |
| `MeasureController` | 4× inline |
| `CompanyInvitationsComponent::isManagerOnly()` | TypeScript-Portierung |

**Und der Code widerspricht sich, ob ein Manager mehrere Teams verwalten darf:**

- `CompanyController` und `MeasureController` gehen von **genau einem** aus
  (`Team::where('manager_id',…)->first()` bzw. `->value('id')`)
- `CompanySurveyController` und `InviteTeamValidator` arbeiten mit einer **Liste**
  (`managedTeams()->pluck('id')`)

Dazu **drei verschiedene Reaktionen auf denselben Zustand** „Manager ohne Team":
`CompanyController::dashboard` → 403 mit deutschem String, `MeasureController::index` → leere
Liste, `MeasureController::store` → `abort(403)`.

## Umsetzung in Etappen

### Etappe 1 — Manager-Prüfung entduplizieren (F1)

- `TeamLayerGuard::isManagerOnly()` als kanonische Implementierung festlegen — sie ruft bereits
  `loadMissing('roles')` und ist injizierbar.
- Alle sieben Backend-Vorkommen darauf umstellen.
- **Achtung:** `InviteTeamValidator::isManagerOnly()` ruft **kein** `loadMissing` — die Aufrufer
  laden die Relation selbst. Beim Umstellen prüfen, dass keine zusätzliche Query entsteht oder
  eine fehlende Relation zu falschem Verhalten führt.
- Die TypeScript-Portierung im Frontend bleibt (getrennte Laufzeit), sollte aber kommentiert
  auf die Backend-Regel verweisen.
- **Abnahme:** Eine Implementierung im Backend; alle Tests grün.

### Etappe 2 — Team-Ebenen-Abfrage zentralisieren (F2)

- `company()->value('team_layer_enabled')` wird an **vier** Stellen eigenständig abgefragt:
  `TeamLayerGuard::enabledFor()`, `Employee\SurveyController::teamLayerEnabled()`,
  `CompanyInvitationService`, `User::canUseCompanyPortal()`.
- Jede erzeugt eine eigene Query — auch wenn die Relation bereits geladen ist.
- Auf `TeamLayerGuard::enabledFor()` vereinheitlichen oder eine Memoisierung auf dem `User`-Modell
  einführen.
- **Achtung:** `User::canUseCompanyPortal()` prüft zuerst `relationLoaded('company')` und nutzt
  dann die geladene Relation — dieses Muster ist bereits optimiert und sollte die Vorlage sein.
- **Abnahme:** Ein Zugriffspfad; messbar weniger Queries.

### Etappe 3 — Mehrere Teams je Manager entscheiden (D7)

- **Blockiert durch offene Frage U4** (Paket 16).
- Nach der Entscheidung **alle** Stellen angleichen — entweder überall Einzelteam oder überall Liste.
- **Achtung:** Die Datenbank erlaubt heute mehrere (`teams.manager_id` ohne Unique-Constraint).
  Bei der Entscheidung „genau ein Team" wäre ein partieller Unique-Index angebracht;
  **partielle Indizes nur per `DB::statement()`** (siehe Migrationskommentar bei
  `measure_checkin_tokens_one_active_per_measure`).
- **Abnahme:** Kein Widerspruch mehr; Test mit einem Manager, der zwei Teams verwaltet.

### Etappe 4 — Reaktion auf „Manager ohne Team" vereinheitlichen (D8)

- Eine Semantik festlegen und überall anwenden. Naheliegend: leere Liste bei Leseoperationen,
  403 bei Schreiboperationen — das entspricht dem heutigen Verhalten von `MeasureController`,
  aber nicht dem von `CompanyController::dashboard`.
- Die deutschsprachige unstrukturierte Fehlermeldung
  (`{"error":"Kein Team zugewiesen. Bitte wenden Sie sich an Ihren Administrator."}`) in das
  Codeformat überführen (Abstimmung mit Paket 08, Etappe 3).
- **Abnahme:** Ein Verhalten je Operationstyp; Fehlerformat einheitlich.

### Etappe 5 — Team-CRUD schärfen (D3, D4, I9)

- **D4:** `managerId` wird **nicht** auf die Rolle `COMPANY_MANAGER` geprüft. `CreateTeamRequest`
  prüft nur Firma und `status='active'`. Nur das Frontend filtert
  (`CompanyTeamsComponent::managerOptions()`). Ein Employee kann per direktem API-Aufruf
  Teammanager werden. → Regel serverseitig ergänzen.
- **I9:** `PUT /company/teams/{id}` verwendet **`CreateTeamRequest`** statt eines eigenen
  Patch-Requests. Alle Felder sind damit Pflicht, und ein PUT ohne `color` **löscht** die Farbe
  (`$validated['color'] ?? null`). Eigenen Request einführen oder das Verhalten dokumentieren.
- **D3:** `CreateTeamRequest::authorize()` verlangt Admin/Owner, die Route lässt auch Manager
  durch. Hier ist die Verengung vermutlich beabsichtigt — dann im Code begründen statt sie
  implizit zu lassen.
- **Abnahme:** Manager-Rolle wird geprüft; PUT löscht keine Felder unbeabsichtigt;
  Autorisierungsabweichung ist begründet.

### Etappe 6 — Team-Frontend vervollständigen (H11, F8)

- **H11:** Das Backend bietet **sechs** Team-Endpunkte, das Frontend nutzt **zwei** (Liste,
  Anlegen). `GET /{id}`, `PUT`, `DELETE` und `GET /{teamId}/members` haben keinen Aufrufer.
  Zudem ruft `CompanyTeamsComponent` `ApiClient` direkt, obwohl `CompanyTeamsService` existiert —
  der wird nur von `CompanyMeasuresComponent` genutzt.
  → Bearbeiten, Löschen und Mitgliederansicht anbinden; Zugriff über den Service führen.
- **F8:** `TEAM_LAYER_DISABLED` entsteht auf **zwei** Wegen mit **unterschiedlichen Statuscodes**:
  `TeamLayerGuard::abortIfDisabled()` (403, parametrisiert auch 422) und
  `CompanyInvitationService` (403 bzw. 422, eigene Exception). Auf einen Erzeugungsweg führen.
  **Achtung:** Die Unterscheidung 403 („du darfst das nicht") vs. 422 („dein Payload passt nicht
  zur Konfiguration") ist fachlich sinnvoll und soll erhalten bleiben.
- **Abnahme:** Team-Verwaltung ist über die Oberfläche vollständig; ein Erzeugungsweg für den
  Fehlercode.

## Out of Scope

- Portalzugang für Manager ohne Team-Ebene (das ist `User::canUseCompanyPortal()` und korrekt)
- Maßnahmen- und Umfragen-Scoping (Pakete 11 und 13) — konsumieren die hier vereinheitlichte Regel

## Hard constraints

- **Die Team-Ebene bleibt pro Firma abschaltbar**; ein reiner Manager ohne aktive Team-Ebene
  erhält weiterhin keinen Company-Portalzugang
- `GET /company/teams` liefert bei deaktivierter Team-Ebene weiterhin eine **leere Collection**
  statt eines Fehlers — das ist die einzige tolerante Stelle und bewusst so
- Kein `migrate:fresh`; partielle Indizes nur per `DB::statement()`
- `tests/Feature/CompanyTest.php` (69 Tests) und `tests/Feature/TenantScopeTest.php` müssen grün bleiben

## Review-Checkliste

- [ ] Eine Manager-Prüfung im Backend, alle sieben Vorkommen umgestellt
- [ ] Ein Zugriffspfad für die Team-Ebenen-Abfrage
- [ ] Kein Widerspruch mehr bei mehreren Teams je Manager
- [ ] Eine Semantik für „Manager ohne Team" je Operationstyp
- [ ] `managerId` wird serverseitig auf die Rolle geprüft
- [ ] PUT löscht keine Felder unbeabsichtigt
- [ ] Team-Verwaltung im Frontend vollständig, über den Service
- [ ] Ein Erzeugungsweg für `TEAM_LAYER_DISABLED`, 403/422-Unterscheidung erhalten
- [ ] Kapitel 4.2, 4.5, 5.2 und 6.3 der Dokumentation aktualisiert

## Expected output

- Geänderte Dateien je Etappe
- Entscheidung zu U4 mit Begründung
- Gewählte Semantik für „Manager ohne Team"
- Ob eine Migration nötig war
- Neue Tests und Specs
- Messbare Query-Reduktion aus Etappe 2
