# API Contract Rules

## General

The API contract lives in:

    docs/api/openapi.yaml

Update OpenAPI when:
- a route is added
- a route is removed
- request body changes
- response body changes
- error behavior changes
- auth/role behavior changes

## Error Format

Use a consistent error format:

    {
      "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation failed.",
        "details": {}
      }
    }

## API Design

- Use Laravel Resources for response shape.
- Use Form Requests for validation.
- Use middleware, policies or gates for role checks.
- Do not leak internal exception details.
- Use stable response keys.
