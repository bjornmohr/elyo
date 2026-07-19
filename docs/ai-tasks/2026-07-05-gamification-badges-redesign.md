# Gamification Badges Redesign

## Scope

Redesign the Employee badge page and dashboard badge block from the `Gamification Badges Concept Review.zip` handoff.

## Test-First Plan

Acceptance criteria covered by tests:
- Next goal selects the in-progress badge with the highest progress.
- Badge benefit copy is available for the detail modal.
- Badge page shows the streak strip, next-goal spotlight, and category collections instead of the old three-list layout.
- Earned, in-progress, locked, spotlight, dashboard challenge, and secondary suggestions can open the centered badge detail modal.
- Category accordions and dashboard prioritisation explainer can be toggled.

## Validation

- `docker compose exec web npm test -- --watch=false`
- `docker compose exec web npm run build`
- Tiny typography grep
- AI-ish / forbidden copy grep
