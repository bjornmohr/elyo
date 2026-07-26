# Task: Fix lab-marker validation review findings

## Goal

Return the documented coded validation envelope for every invalid lab-marker
value and represent the accepted numeric bounds in the binding OpenAPI contract.

## Functional Behavior

- `POST /employee/lab-markers` rejects negative values, values with more than
  four decimal places, and values above `99999999.9999`.
- Every rejection returns HTTP 422 with `error.code = VALIDATION_ERROR` and a
  `value` entry in `error.details`.
- Valid values remain between `0` and `99999999.9999`, inclusive, with at most
  four decimal places.
- Service-level validation remains in place for non-HTTP callers.

## Test Seam

Use the employee lab-marker POST endpoint as the public seam. Extend its feature
test data provider with `-0.0001`, `1.12345`, and `100000000`.

## Scope

- `StoreLabMarkerReadingRequest`
- `LabMarkerEndpointTest`
- `docs/api/openapi.yaml`

No authorization, privacy, route, schema, frontend, or marker-specific
plausibility changes.

## Validation

Run:

    docker compose exec api php artisan test --filter=LabMarkerEndpointTest
    docker compose exec api php artisan test --filter=LabMarker
    docker compose exec api php artisan test
    git diff --check

