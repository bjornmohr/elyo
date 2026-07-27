# Paket 03: Frontend-Auslieferung

**Priorität:** 1 · **Bereich:** Frontend + Infra · **Etappen:** 4
**Befunde:** B2, B3, J25, J26

```ai-run
complexity:        mittel
implement_tier:    standard
implement_effort:  medium
review_tier:       standard
review_effort:     low
blocked_by:        U7
depends_on:        -
```

## Goal

Das Frontend produktiv ausliefern können. Derzeit ist das technisch nicht möglich.

## Context

**Zwei harte Blocker:**

1. **Die API-Basis-URL ist hartcodiert.** Beide Environment-Dateien enthalten
   `apiBaseUrl: 'http://localhost:8080/api'`. `docker-compose.yml` reicht zwar `NG_APP_API_URL`
   an den `web`-Container, aber der Build wertet die Variable **nicht** aus: `angular.json` hat
   kein `fileReplacements`, es gibt keinen Substitutionsmechanismus, und die Variable wird in
   keiner TypeScript-Datei gelesen. Ein Produktionsbuild liefe gegen `localhost`.
2. **Es gibt kein Produktionsimage.** `apps/web-angular/Dockerfile` startet `ng serve` mit Polling.
   Kein Webserver liefert `dist/` aus, kein Build-Schritt existiert in einer Pipeline.

Dazu zwei Befunde, die die SSR-Option betreffen: SSR ist vollständig konfiguriert
(`app.config.server.ts`, `app.routes.server.ts` mit `RenderMode.Prerender`, `src/server.ts` als
Express-Handler), wird aber nicht gestartet — und **wäre sofort fehlerhaft**, weil `AuthStore`
`localStorage` im Feldinitialisierer liest und `AuthService::detectPortalFromHostname()` auf
`window.location` zugreift. Letztere setzt zudem Subdomains (`admin.`, `company.`, `app.`) voraus,
die in keiner Konfiguration des Repositories vorkommen.

## Umsetzung in Etappen

### Etappe 1 — Auslieferungsvariante entscheiden (B3, J25)

- **Blockiert durch offene Frage U7** (Paket 16), sofern SSR erwogen wird.
- **Variante A — statisches Build + nginx:** Ohne Vorarbeit umsetzbar. Erfordert eine
  Rewrite-Regel für clientseitiges Routing. Kein Node zur Laufzeit.
- **Variante B — SSR über `src/server.ts`:** Setzt voraus, dass Etappe 3 zuerst erledigt ist.
- Entscheidung im Handoff begründen. **Variante A ist die naheliegende**, solange kein fachlicher
  SSR-Bedarf (SEO, First Paint) belegt ist — die Anwendung ist vollständig hinter einem Login.
- **Abnahme:** Entscheidung dokumentiert, Abhängigkeiten benannt.

### Etappe 2 — API-Basis-URL konfigurierbar machen (B2)

- Mechanismus wählen:
  - **`fileReplacements`** in `angular.json` — Angular-Standard, buildzeit-gebunden
  - **Laufzeitkonfiguration** über `config.json` + `APP_INITIALIZER` — deploymentfreundlicher,
    ein Image für alle Umgebungen
  - **Relativer Pfad `/api`** — am einfachsten, **wenn** nginx Frontend und API unter derselben
    Origin ausliefert. Das ist bei Variante A aus Etappe 1 der Fall und macht den Rest überflüssig.
- `environment.ts` und `environment.development.ts` konsistent machen.
- `NG_APP_API_URL` entweder tatsächlich auswerten **oder** aus `docker-compose.yml` und
  `.env.example` entfernen — sie steht derzeit als verwaiste Variable in Kapitel 9.6.
- **Abnahme:** Entwicklungsbetrieb (`npm start`, Port 4200 gegen 8080) funktioniert unverändert;
  ein Build mit abweichender URL erzeugt nachweislich abweichende Artefakte.

### Etappe 3 — SSR-Blocker beheben oder SSR entfernen (J25, J26)

- **Nur nötig, wenn Etappe 1 Variante B gewählt hat.** Sonst: SSR-Dateien entfernen oder als
  bewusst ungenutzt kennzeichnen.
- Bei Variante B:
  - `AuthStore`: `localStorage`-Zugriff aus dem Feldinitialisierer in eine plattformabhängige
    Initialisierung verschieben (`isPlatformBrowser`).
  - `AuthService::detectPortalFromHostname()`: `window`-Zugriff absichern.
  - **`detectPortalFromHostname()` grundsätzlich hinterfragen (J26):** Sie rät das Portal aus dem
    Hostnamen und setzt Subdomains voraus, die es nicht gibt. `LoginComponent` fängt einen
    Fehlgriff bereits mit einem automatischen Retry ohne `requested_portal` ab. Die Methode ist
    damit ein Mechanismus, der nur Kosten und keinen Nutzen hat — Entfernung prüfen.
- **Abnahme:** Bei Variante B läuft ein SSR-Start ohne Fehler; bei Variante A ist der SSR-Pfad
  entfernt oder eindeutig als ungenutzt markiert.

### Etappe 4 — Produktionsimage (B3)

- Multi-Stage-Dockerfile: Build-Stage mit Node (`node:22`, passend zum Entwicklungsimage),
  Runtime-Stage mit nginx bei Variante A.
- Entwicklungsimage erhalten — als zweite Stage oder eigener Dockerfile.
- `docker-compose.yml`: Produktionsprofil erwägen.
- **Budgets beachten:** `angular.json` setzt 500 kB Warnung / 1 MB Fehler für `initial`. Reißt der
  Build sie, im Handoff benennen statt sie stillschweigend zu erhöhen.
- **Abnahme:** Produktionsbuild läuft durch; Entwicklungsbetrieb über `docker compose up`
  unverändert; Image enthält keine Entwicklungsabhängigkeiten.

## Out of Scope

- CI-Pipeline für den Build (Paket 09)
- Frontend-Konsolidierung, Inline-Templates, Service-Struktur (Paket 15)
- Backend-Deployment (Paket 04)

## Hard constraints

- Der lokale Entwicklungsbetrieb muss nach jeder Etappe funktionieren
- Kein `docker compose down -v`
- Budgets aus `angular.json` nicht stillschweigend erhöhen

## Review-Checkliste

- [ ] Auslieferungsvariante ist begründet, nicht implizit gewählt
- [ ] `NG_APP_API_URL` ist entweder ausgewertet oder entfernt — nicht beides halb
- [ ] Ein Build mit abweichender URL erzeugt nachweislich andere Artefakte
- [ ] SSR ist entweder lauffähig oder eindeutig als ungenutzt markiert
- [ ] `detectPortalFromHostname()`: Entscheidung getroffen
- [ ] Produktionsimage enthält keine Dev-Abhängigkeiten
- [ ] Entwicklungsbetrieb unverändert
- [ ] Kapitel 5, 9.7 und 12 der Dokumentation aktualisiert

## Expected output

- Gewählte Auslieferungsvariante mit Begründung
- Gewählter Konfigurationsmechanismus mit Begründung
- Entscheidung zu SSR und `detectPortalFromHostname()`
- Geänderte und neue Dateien
- Nachweis, dass Produktionsbuild und Entwicklungsbetrieb funktionieren
