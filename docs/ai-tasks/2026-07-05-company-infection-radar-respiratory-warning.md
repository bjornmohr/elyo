# Company Infektionsradar respiratory early-warning demo

## Goal

Sharpen the Company Infektionsradar concept into an aggregate-only respiratory early-warning demo.

## Scope

- Angular Company dashboard Infektionsradar summary
- Angular Company Infektionsradar detail page
- Company insights frontend contract
- Laravel demo infection radar payload/provider
- OpenAPI infection radar schema

## Constraints

- Keep the module focused on Atemwegserkrankungen.
- Do not expose individual employee health data.
- Keep production provider null until real aggregation and external RKI/ARE integration exist.
- Preserve existing routes and feature flag behavior.
- Use demo data only for the richer concept payload.

## Validation

- `docker compose exec web npm run build` passed.
- `docker compose exec api php artisan test` passed with 378 passed, 1 skipped.
- Targeted tiny-text grep for changed Company radar/dashboard files passed with no matches.
- Broad Company tiny-text grep still reports pre-existing matches in unrelated concept pages.
