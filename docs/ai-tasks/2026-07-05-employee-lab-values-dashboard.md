# Employee Lab Values Dashboard

## Scope

Implement the Employee-facing Laborwerte dashboard from the handoff package `ELYO Laborwerte Dashboard.zip`.

## Source Of Truth

- `docs/laborwerte-handoff.md`
- `employee-lab-data.json`
- `database/seeders/LabValueDemoSeeder.php`
- `Laborwerte-Dashboard.dc.html`
- `Uebersicht-Dashboard.dc.html`

## Implementation Notes

- Backend persistence is required through an Employee-only API.
- Demo marker values come from the handoff data and are seeded for `employee1@demo.de` through `employee6@demo.de`.
- Individual lab markers are only returned for the authenticated Employee.
- Company/Admin/Employer endpoints must not expose individual lab values.
- UI wording follows the handoff guardrails and uses only the soft status labels `unter Bereich`, `im Orientierungsbereich`, and `über Bereich`.

## Validation

- Laravel tests
- Fresh migration and demo seed
- Angular build
- Tiny typography grep
- Forbidden wording search against changed frontend lab/dashboard files
