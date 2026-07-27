# Paket 06: Partner-Subsystem

**Priorität:** 2 · **Bereich:** Backend + Frontend · **Etappen:** 7
**Befunde:** A3, A4, C10, C11, D1, D6, H1, H2, H14, H15, J30, J33, K1

```ai-run
complexity:        mittel
implement_tier:    standard
implement_effort:  medium
review_tier:       standard
review_effort:     medium
blocked_by:        U1, U3, U6, U14
depends_on:        07
```

## Goal

Das einzige fachlich unvollständige und vollständig ungetestete Subsystem funktionsfähig machen —
oder bewusst zurückbauen.

## Context

Partner sind ein eigenes Auth-Universum: eigenes Modell, eigene Tabelle, eigenes Guard
(`auth:partner` über den Provider `partners`), eigener Statuslebenszyklus. Der Umfang ist klein
(fünf Routen, davon eine ein Stub) — aber **fachlich gebrochen**.

**Der zentrale Befund (H2): Der Lebenszyklus ist unterbrochen.**

```
 register
    │
    ▼
PENDING_DOCS ──── ??? kein Codepfad ───▶ PENDING_REVIEW
                                              │
                                    approve   │   reject
                                   ┌──────────┴──────────┐
                                   ▼                     ▼
                               VERIFIED ◀──────────▶ REJECTED
                                   │   unsuspend        (Endzustand)
                          suspend  │      ▲
                                   ▼      │
                               SUSPENDED ──┘
```

`register` setzt `PENDING_DOCS`. `AdminPartnerController::TRANSITIONS` kennt nur Übergänge **aus**
`PENDING_REVIEW`, `VERIFIED` und `SUSPENDED`. **Ein registrierter Partner kann nie freigegeben
werden.**

Dazu:

- **Kein Test** berührt das Subsystem funktional. Einzige Ausnahme:
  `tests/Privacy/LabAccessPrivacyTest` prüft die Abweisung auf Lab-Routen.
- **Keine `PartnerFactory`**, obwohl das Modell `HasFactory` nutzt.
- **Kein Frontend.** `PartnerDashboardComponent` ist statischer Text ohne API-Aufruf, und
  `AuthStore` kennt nur den `users`-Login — es gibt keine Anbindung an `POST /partner/login`.
- **Der Portalzweig ist unerreichbar (D6):** `User::canUsePortal('partner')` prüft die Rolle
  `PARTNER` auf einem `User`, die von **keinem** Codepfad vergeben wird.

## Umsetzung in Etappen

### Etappe 1 — Testabdeckung herstellen (K1)

**Zuerst**, damit die folgenden Änderungen abgesichert sind.

- `database/factories/PartnerFactory.php` mit Zuständen je `PartnerVerificationStatus`.
- `tests/Feature/Partner/PartnerAuthTest.php`: Registrierung, Login (Erfolg, falsches Passwort,
  unbekannte E-Mail, `REJECTED`, `SUSPENDED` — alle vier Fehlerfälle liefern dieselbe generische
  Antwort), `me`, `logout`.
- `tests/Feature/Admin/AdminPartnerTest.php`: Liste mit und ohne Filter, alle vier Übergänge,
  jeder unzulässige Ausgangsstatus.
- **Bekannte Fehlverhalten als Ist-Zustand festhalten**, mit Befund-ID im Testkommentar:
  - `ELYO_SUPPORT` erhält 403 (D1)
  - Ungültiger `status`-Filter wird ignoriert (J30)
  - `PENDING_DOCS → PENDING_REVIEW` ist unmöglich (H2)
- **Abnahme:** Suite grün; die drei Fehlverhalten sind als Test dokumentiert, nicht „richtig"
  getestet.

### Etappe 2 — Lebenszyklus schließen (H2, H1, C10)

- **Fachlich entscheiden**, wodurch der Übergang ausgelöst wird:
  - **Variante A:** Durch den Nachweisupload. Dann `POST /partner/documents` implementieren
    (heute eine Closure mit statischer Antwort) und den Status wechseln.
  - **Variante B:** Durch eine Admin-Aktion, z. B. `action: "request_review"`.
  - **Variante C:** `PENDING_DOCS` entfällt; die Registrierung setzt direkt `PENDING_REVIEW`.
- **Prüfen:** `nachweis_url` ist bei der Registrierung bereits `required|url`. Wenn der Nachweis
  damit schon vorliegt, sind Variante B oder C naheliegend und der Upload-Endpunkt entfällt.
- Bei Variante A: Controller statt Closure, FormRequest mit Dateivalidierung.
  **Partnernachweise gehören nicht in `elyo_health`** — sie sind keine Gesundheitsdaten eines
  Beschäftigten. Ablage getrennt, aber ebenfalls nicht öffentlich lesbar (Lehre aus A5).
- `docs/api/openapi.yaml` angleichen (C10 beschreibt den Endpunkt als funktionalen Upload).
- **Abnahme:** Ein registrierter Partner ist freigebbar; Test über den vollständigen Lebenszyklus.

### Etappe 3 — Tokenlebenszyklus (A3, A4)

- **A3:** `register` gibt **sofort ein gültiges Token** aus — ohne E-Mail-Bestätigung, ohne
  Verifikation. Entscheiden: kein Token vor `VERIFIED`, oder ein Token mit eingeschränkten
  Abilities (Abstimmung mit Paket 01, Etappe 3).
- **A4:** Bei `reject`/`suspend` werden bestehende Tokens **nicht** widerrufen. Ein vor der
  Ablehnung ausgestelltes Token bleibt gültig und liefert über `GET /partner/me` den internen
  `rejection_reason` und `reviewed_by_id`.
  → `tokens()->delete()` beim Statuswechsel.
- **Abnahme:** Test belegt, dass ein abgelehnter Partner mit altem Token abgewiesen wird.

### Etappe 4 — Antwortform und Vertrag (C11, J30)

- **`GET /partner/me`** gibt heute `response()->json($request->user())` zurück — das **rohe
  Modell** inklusive `verification_status`, `rejection_reason`, `reviewed_at`, `reviewed_by_id`.
  Eine `PartnerResource` einführen, die interne Prüffelder ausblendet.
- **`GET /admin/partners`** liefert das **rohe Laravel-Paginator-Objekt**
  (`current_page`, `data`, `first_page_url`, `links`, …) — abweichend von allen anderen
  Admin-Listen, die `AnonymousResourceCollection` verwenden. Angleichen.
- **J30:** Ein ungültiger `status`-Filter wird **stillschweigend ignoriert**
  (`if ($statusParam && PartnerVerificationStatus::tryFrom($statusParam))`) statt 422 zu erzeugen.
  Validieren. Zusätzlich: Seitengröße 50 ist hartcodiert.
- **C11 — blockiert durch U14** (Paket 16): OpenAPI beschreibt `PartnerSession` als Cookie-Schema
  (`elyo_partner_session`), implementiert ist ein Bearer-Token über `auth:partner`. Entscheiden,
  welches gilt, und die andere Seite angleichen.
- **Abnahme:** Keine internen Prüffelder mehr in der Partner-Antwort; Filter validiert;
  OpenAPI stimmt mit der Implementierung überein.

### Etappe 5 — Autorisierung angleichen (D1)

- **Blockiert durch offene Frage U3** (Paket 16).
- `AdminPartnerActionRequest::authorize()` verlangt `hasRole(Role::ELYO_ADMIN)`, die Route lässt
  `role:ELYO_ADMIN,ELYO_SUPPORT` durch. Support-Nutzer erhalten damit 403 auf einer Route, für die
  sie autorisiert sind.
- Entweder Route verengen oder Request öffnen — eine der beiden Seiten anpassen, nicht beide.
- **Abnahme:** Route und Request stimmen überein; Test für beide Rollen.

### Etappe 6 — Portalzweig entscheiden (D6)

- **Blockiert durch offene Frage U1** (Paket 16).
- `User::canUsePortal('partner')` prüft `Role::PARTNER` auf einem `User`. **Kein Codepfad vergibt
  diese Rolle** — weder Seeder noch Einladung (`CompanyInvitationController::storeInvitation`
  erlaubt nur `COMPANY_ADMIN`, `COMPANY_MANAGER`, `EMPLOYEE`).
- Entweder den Zweig entfernen (dann auch die Route `/partner` im Frontend und den `portalGuard`)
  oder die Rollenvergabe ergänzen.
- **Abnahme:** Kein unerreichbarer Codepfad mehr.

### Etappe 7 — Frontend und `minimum_level` (H14, H15, J33)

- **Blockiert durch offene Frage U6** für `minimum_level`.
- **H15:** Partner-Login im Frontend anbinden — erfordert eine Entscheidung, ob `AuthStore` zwei
  Prinzipaltypen führt oder ob es einen getrennten Partner-Store gibt.
- **H14:** Admin-Partnerbereich bauen (Liste, Filter, Freigabe/Ablehnung/Sperrung). Die
  Backend-Endpunkte existieren bereits vollständig.
- **J33:** `partners.minimum_level` ist eine **String-Spalte mit Default `'STARTER'`**, wird aber
  als `integer|min:0` validiert. Die Bedeutung ist im Code nirgends ausgewertet. Klären und
  angleichen — Typ, Wertebereich, Zweck.
- **Abnahme:** Admin kann Partner über die Oberfläche freigeben; `minimum_level` hat einen
  dokumentierten Typ und Zweck.

## Out of Scope

- Rate-Limiting auf `/partner/login` und `/partner/register` (Paket 01, Etappe 1)
- E-Mail-Versand für Statusmails (Paket 07) — hier nur die Auslösepunkte vorbereiten
- Der 400-Statuscode bei `invalid_transition` (Paket 08, Etappe 4)

## Hard constraints

- Partnerdokumente **nicht** in `elyo_health` ablegen
- Die generische Fehlermeldung bei Login (`invalid_credentials` für falsches Passwort **und** für
  `REJECTED`/`SUSPENDED`) **bleibt** — sie verhindert Statusenumeration
- Kein `migrate:fresh`; Schemaänderungen nur als neue Migration im Identity-Verzeichnis
- Etappe 1 vor allen anderen

## Review-Checkliste

- [ ] Etappe 1 kam zuerst; die drei bekannten Fehlverhalten sind als Ist-Zustand dokumentiert
- [ ] Ein registrierter Partner ist nachweislich freigebbar
- [ ] Bei `reject`/`suspend` werden Tokens widerrufen
- [ ] `GET /partner/me` gibt keine internen Prüffelder mehr aus
- [ ] Ungültiger `status`-Filter erzeugt 422
- [ ] Route und Request stimmen bei der Autorisierung überein
- [ ] Kein unerreichbarer Portalzweig mehr
- [ ] `minimum_level` hat dokumentierten Typ und Zweck
- [ ] OpenAPI stimmt mit der Implementierung überein
- [ ] Kapitel 4.7, 6.1 und 5.3 der Dokumentation aktualisiert

## Expected output

- Gewählte Variante für den Lebenszyklus mit fachlicher Begründung
- Geänderte und neue Dateien je Etappe
- Anzahl neuer Tests
- Entscheidungen zu U1, U3, U6, U14
- Ob eine Migration nötig war
