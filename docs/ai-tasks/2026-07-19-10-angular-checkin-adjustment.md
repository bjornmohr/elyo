# Task: Angular check-in adjustment — 1–5 scale, remove note

## Goal

Adapt the existing Angular employee check-in and wellbeing displays to the new backend contract (prompt 08): inputs 1–5, no note field, score display on 1–5. Approved exception to the "no Angular changes" rule — strictly limited to this contract change.

## Context

Relevant files:

- apps/web-angular/src/app/** (employee check-in feature folder, dashboard/history wellbeing displays, API service for checkin)
- docs/api/openapi.yaml (updated in prompt 08)
- docs/migration/angular-visual-qa-checklist.md
- ELYO-102 §3 (B3/B4/B5), ADR-003 (D3)

Background:

- Backend now rejects mood/energy/stress outside 1–5 and rejects `note`. The current UI offers 1–10 and a note textarea — it would 422 on every submit.
- The company dashboard pending-state tolerance was handled in prompt 09; do not restyle it here.

## Scope

Change only:

- Check-in form component(s): scale inputs 1–5, remove note control + its model/service plumbing
- Wellbeing display components (dashboard score/sparkline, history entries): value/score scale labels 1–5, remove note rendering
- The Angular API service types for checkin/wellbeing
- Related component specs

Do not change:

- Any other feature, routing, styling system
- Company portal components
- environment/base-URL config (prompt 15 handles routing if needed)

## Requirements

1. Form validation mirrors the contract: required ints 1–5; submit payload contains exactly mood/energy/stress.
2. All note references removed from templates, models, services, specs (grep `note` in the employee wellbeing scope).
3. Score displays (including any /10 hardcoding) show the 1–5 semantics; history entries created pre-rebuild don't exist (fresh DB), so no dual-scale handling.
4. Specs updated; `npm run build` and existing test command green.
5. Walk the relevant items of docs/migration/angular-visual-qa-checklist.md for check-in + dashboard and report results.

## Constraints

- Minimal diff; no visual redesign, no new components beyond what the removal requires.
- No new npm packages.

## Privacy and Security Requirements

- No health values in console logs or error toasts.

## Validation

Run:

    docker compose exec web npm run build
    docker compose exec web npm test -- --watch=false   # or the project's configured runner
    docker compose exec api php artisan test

Expected result:

- Build + specs green; manual check-in against the dev backend succeeds end-to-end (mood/energy/stress 1–5).

## Output Required

1. Files changed
2. Visual QA checklist results (relevant items)
3. Commands run and results
4. Open questions

## Review Checklist

- Does submit work end-to-end against prompt-08 backend?
- Is every note remnant gone (template, model, service, spec)?
- Zero changes outside the employee wellbeing scope?
