# Task: Fix Measure OpenAPI Error Contract

Date: 2026-06-10

## Goal

Fix the remaining OpenAPI contract issue from the Measure Domain Fields v1 review.

This is a documentation/API-contract patch only. Do not change Laravel runtime behavior, Angular behavior, database migrations, participation behavior, points behavior, QR/admin/partner flows, recommendations, templates, Measures Hub behavior, questionnaire/check-in behavior, or participation verification fields.

## Review Finding

OpenAPI under-documents actual company measure error behavior.

### Create Measure

`CreateMeasureRequest` can return Laravel validation `422` for invalid domain fields, invalid date ranges, invalid integer values, unsupported verification requirements, and invalid team payloads.

OpenAPI currently only references `TeamLayerDisabled` for `422`.

### Patch Measure

`PatchMeasureRequest` can return Laravel validation `422`.

`MeasureController` can still return `400 invalid_transition`.

OpenAPI currently documents only `200` and `403` for `PATCH /company/measures/{id}`.

## Required Changes

Update only:

- `docs/api/openapi.yaml`

### POST /company/measures

Document all actual response behavior:

- `201` created
- `403` company portal forbidden / team layer disabled where applicable
- `422` validation error

The `422` documentation must cover Laravel validation errors for:

- invalid enum values
- unsupported `verificationRequirement`
- invalid date ranges
- invalid integer values
- invalid team payloads / team layer disabled if this is currently returned as validation-style `422`

If the OpenAPI file already has a reusable validation error response/schema, reuse it.

If `TeamLayerDisabled` is currently the only referenced `422`, keep that behavior documented but do not make it look like the only possible `422`.

### PATCH /company/measures/{id}

Document all actual response behavior:

- `200` updated
- `400` invalid transition
- `403` company portal forbidden / team layer disabled where applicable
- `422` validation error

The `422` documentation must cover Laravel validation errors for:

- invalid enum values
- unsupported `verificationRequirement`
- invalid partial date ranges against persisted values
- invalid integer values

The `400` response must document the existing invalid transition behavior returned by `MeasureController`.

## Constraints

- Do not edit Laravel code.
- Do not edit Angular code.
- Do not edit migrations.
- Do not edit tests unless OpenAPI documentation tests exist and fail.
- Do not add unimplemented QR/admin/partner behavior.
- Do not document participation verification fields.
- Do not document unsupported request-side `verificationRequirement` values.
- Do not expose individual employee health data or identifiable participation data.

## Validation

Run non-destructive validation only:

- `git diff --check`
- OpenAPI validation command if one exists
- If no OpenAPI validation command exists, explicitly state that no project command was found
- Optional: `rg`/manual inspection to confirm `POST /company/measures` and `PATCH /company/measures/{id}` document the expected responses

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

## Expected Handoff

Final handoff must include:

- Summary
- Files changed
- Exact OpenAPI responses added/updated
- Validation commands run
- Whether an OpenAPI validation command exists
- Remaining risks/open questions

## Implementation Plan

1. Inspect the current OpenAPI measure endpoints in `docs/api/openapi.yaml`.
   - Locate `POST /company/measures` and `PATCH /company/measures/{id}`.
   - Identify existing reusable error response components or schemas, especially validation, forbidden, and generic error envelopes.
   - Check whether `TeamLayerDisabled` is modeled as a reusable response, schema, or inline response.

2. Cross-check the documented responses against the existing Laravel behavior without changing runtime code.
   - Review `CreateMeasureRequest` and `PatchMeasureRequest` only to confirm validation failure cases and response status expectations.
   - Review `MeasureController` only to confirm the existing `400 invalid_transition` response for invalid status transitions.
   - Do not change controllers, requests, resources, services, tests, migrations, frontend, or config files.

3. Update `POST /company/measures` in `docs/api/openapi.yaml`.
   - Keep the existing `201` created response.
   - Ensure `403` documents company portal forbidden and team-layer access failures where applicable.
   - Replace the narrow `422` documentation with validation-error documentation that covers invalid enum values, unsupported `verificationRequirement`, invalid date ranges, invalid integer values, and invalid team payloads.
   - Preserve `TeamLayerDisabled` coverage if it is currently returned as validation-style `422`, but do not present it as the only possible `422`.
   - Reuse existing reusable validation error components if available.

4. Update `PATCH /company/measures/{id}` in `docs/api/openapi.yaml`.
   - Keep the existing `200` updated response.
   - Add or verify `403` for company portal forbidden and team-layer access failures where applicable.
   - Add `400` for the existing `invalid_transition` controller response.
   - Add `422` validation-error documentation covering invalid enum values, unsupported `verificationRequirement`, invalid partial date ranges against persisted values, and invalid integer values.
   - Reuse existing reusable error components where possible and avoid introducing new response shapes unless no reusable option exists.

5. Keep the contract scope narrow.
   - Do not document unimplemented QR/admin/partner behavior.
   - Do not document participation verification fields.
   - Do not document unsupported request-side `verificationRequirement` values as accepted values.
   - Do not expose individual employee health data, participant identities, raw participation rows, or identifiable timestamps in any example.

6. Validate only with non-destructive checks in the later implementation pass.
   - Run `git diff --check`.
   - Search for an OpenAPI validation command in project scripts/package/composer files; run it only if one exists.
   - If no OpenAPI validation command exists, state that explicitly in the handoff.
   - Optionally use `rg` or manual inspection to confirm both measure endpoints document `400`/`403`/`422` as required.
   - Do not run tests, builds, migrations, destructive Docker commands, or destructive git commands for this documentation-only patch.

7. Final handoff content for the later patch pass.
   - Summarize the OpenAPI-only contract change.
   - List `docs/api/openapi.yaml` as the only changed implementation file.
   - State exact responses added or updated for both endpoints.
   - List validation commands run and results.
   - State whether an OpenAPI validation command was found.
   - Call out any remaining risk if the OpenAPI contract cannot exactly reuse an existing validation error component.

## Final Clarification Before Implementation

- While inspecting `PATCH /company/measures/{id}`, also check whether existing project OpenAPI style documents `404` for missing company-scoped resources. If the route/controller behavior and surrounding OpenAPI conventions document `404` elsewhere, add it consistently. Do not invent a new error shape.
