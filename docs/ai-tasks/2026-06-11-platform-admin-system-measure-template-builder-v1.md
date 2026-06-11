# Task: Platform Admin System Measure Template Builder v1

Date: 2026-06-11

## Context

We are working in the ELYO MVP.

The System Measurements data model already exists.

The Platform Admin System Exercise Catalog v1 already exists and allows platform admins to manage system exercises.

This task builds the next layer:

- platform-admin-managed System Measure Templates
- templates are composed from existing System Exercises
- templates define ordered exercise sequences and optional per-template overrides

The current System Measurement domain is separate from existing company-created measures and QR check-in flows.

Existing company measures, QR check-in, employee participation behavior, survey behavior, and company reporting must not be refactored or changed in this task.

Read and follow:

- `AGENTS.md`
- existing Laravel conventions
- existing Angular conventions
- existing OpenAPI contract rules
- existing admin portal route/auth/role patterns
- existing test style
- existing System Exercise Catalog implementation

Do not modify legacy `../ELYO`.

## Goal

Implement a platform-admin-only System Measure Template Builder v1.

This includes:

- Laravel Admin API for System Measure Templates
- Laravel Admin API for managing ordered exercises inside a template
- Angular Admin UI for listing/filtering/creating/editing/archiving templates
- Angular Admin UI for adding/removing/reordering exercises inside templates
- OpenAPI documentation for all new/changed API endpoints
- Backend and Angular tests

This is a benchmark task for coding agents, so keep the implementation complete but controlled.

## Product Decisions

Use these decisions as fixed requirements.

### Scope

Implement API and Angular Admin UI for System Measure Templates.

### Template purpose

A System Measure Template is a reusable platform-admin-defined plan.

It groups multiple System Exercises into a structured sequence.

Later, templates may be assigned to users as personalized system measures.

That future user assignment is not part of this task.

### Exercise source

Templates can only use existing System Exercises from the System Exercise Catalog.

Do not create exercises inside the template builder.

Do not duplicate exercises in the catalog.

### Template exercise snapshots

This task manages template-level exercise overrides, not user assignment snapshots.

The existing user assignment snapshot tables must not be changed.

### Delete behavior

Do not hard-delete System Measure Templates.

Use archive behavior:

- `status = ARCHIVED`

Do not hard-delete System Exercises.

Template exercise rows may be removed from a template, because that only removes the relationship between a template and an exercise.

### Slug behavior

Template slug is generated server-side from title.

The Admin UI should not require slug input.

For v1:

- generate slug on create
- ensure slug is unique
- do not automatically change slug on update

### OpenAPI

Any new API endpoint must be documented in `docs/api/openapi.yaml`.

No API change without OpenAPI update.

## Existing Data Model

Use the existing System Measurement tables/models from the current branch.

Expected relevant tables include:

- `system_exercises`
- `system_exercise_tags`
- `system_measure_templates`
- `system_measure_template_exercises`
- `user_system_measures`
- `user_system_measure_exercises`
- `user_system_measure_exercise_completions`

This task should primarily touch:

- `SystemMeasureTemplate`
- `SystemMeasureTemplateExercise`
- `SystemExercise`
- admin controllers/requests/resources
- Angular admin feature area
- OpenAPI
- tests

Do not change the data model unless a small missing cast/fillable/relation issue is found and clearly justified.

Do not add new migrations unless the existing schema cannot support the required feature.

## Backend Requirements

### 1. Admin Routes

Add platform-admin-only routes under the existing admin portal route group.

Use existing route naming/style.

Suggested endpoints:

- `GET /api/admin/system-measure-templates`
- `POST /api/admin/system-measure-templates`
- `GET /api/admin/system-measure-templates/{systemMeasureTemplate}`
- `PATCH /api/admin/system-measure-templates/{systemMeasureTemplate}`
- `POST /api/admin/system-measure-templates/{systemMeasureTemplate}/archive`
- `POST /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises`
- `PATCH /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}`
- `DELETE /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}`
- `POST /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/reorder`

Do not add hard delete for templates.

Removing a template exercise row is allowed.

Do not expose these endpoints to company or employee users.

### 2. Authorization / Portal Scope

Use the existing admin route group convention.

System Measure Template admin endpoints are accessible to the same roles as the System Exercise Catalog admin endpoints.

Expected:

- ELYO_ADMIN
- ELYO_SUPPORT

They must not be accessible to:

- COMPANY_OWNER
- COMPANY_ADMIN
- COMPANY_MANAGER
- EMPLOYEE
- unauthenticated users

Do not invent a new auth system.

### 3. Template List Endpoint

`GET /api/admin/system-measure-templates`

Support filtering:

- `search`
- `status`
- `category`
- `difficulty`
- `isFeatured`

Support pagination using the same response shape as the System Exercise Catalog admin API.

Use Laravel resource collection pagination shape:

- `data`
- `links`
- `meta`

Sort default:

- newest first or existing admin-list convention

Response should include enough fields for list UI:

- `id`
- `slug`
- `title`
- `shortDescription`
- `category`
- `difficulty`
- `estimatedDurationMinutes`
- `status`
- `isFeatured`
- `exerciseCount`
- `createdAt`
- `updatedAt`

Search should match at least:

- title
- short description
- description
- slug

### 4. Template Detail Endpoint

`GET /api/admin/system-measure-templates/{systemMeasureTemplate}`

Return full editable detail:

- `id`
- `slug`
- `title`
- `shortDescription`
- `description`
- `category`
- `difficulty`
- `estimatedDurationMinutes`
- `status`
- `isFeatured`
- `exercises`
- `createdAt`
- `updatedAt`

Template exercises must be ordered by `sortOrder`.

Each template exercise should include:

- `id`
- `systemExerciseId`
- `sortOrder`
- `customTitle`
- `customInstructions`
- `customDurationMinutes`
- `customSets`
- `customRepetitions`
- `customHoldSeconds`
- `customFeedbackPrompt`
- `isRequired`
- `exercise`

The nested `exercise` should include enough fields for display:

- `id`
- `slug`
- `title`
- `shortDescription`
- `exerciseType`
- `difficulty`
- `defaultDurationMinutes`
- `status`
- `tags`

### 5. Create Template Endpoint

`POST /api/admin/system-measure-templates`

Request fields:

- `title` required
- `shortDescription` nullable
- `description` nullable
- `category` required or optional depending on existing schema; if optional, default sensibly
- `difficulty` required or optional depending on existing schema; if optional, default sensibly
- `estimatedDurationMinutes` nullable integer
- `status` optional, default `DRAFT` or existing schema/model default
- `isFeatured` boolean, default false

Server behavior:

- generate unique slug from title
- set `created_by_user_id` from authenticated admin if the schema supports it
- validate enum values using model constants
- validate numeric ranges
- return created resource with `201`

Do not accept template exercises in the create payload unless the existing codebase strongly favors nested create patterns.

Prefer creating the template first, then adding exercises through the template exercise endpoints.

### 6. Update Template Endpoint

`PATCH /api/admin/system-measure-templates/{systemMeasureTemplate}`

Behavior:

- update editable template fields
- do not regenerate slug automatically
- validate enum values
- validate numeric ranges
- return updated resource

Do not hard-delete existing templates.

Do not touch user assignment snapshot tables.

### 7. Archive Template Endpoint

`POST /api/admin/system-measure-templates/{systemMeasureTemplate}/archive`

Behavior:

- set `status = ARCHIVED`
- return updated resource
- do not detach template exercises
- do not modify system exercises
- do not modify user snapshots
- idempotent if already archived

Add tests proving archiving does not delete the template or detach its exercises.

### 8. Add Exercise To Template Endpoint

`POST /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises`

Request fields:

- `systemExerciseId` required, must exist
- `sortOrder` optional integer
- `customTitle` nullable string
- `customInstructions` nullable string
- `customDurationMinutes` nullable integer
- `customSets` nullable integer
- `customRepetitions` nullable integer
- `customHoldSeconds` nullable integer
- `customFeedbackPrompt` nullable string
- `isRequired` boolean, default true

Behavior:

- attach an existing System Exercise to the template
- if `sortOrder` is missing, append at the end
- validate that the referenced System Exercise exists
- decide whether archived exercises may be added:
  - preferred: reject archived exercises with `422`
- return the created template exercise resource

If the existing DB has a unique constraint preventing the same exercise from appearing twice in one template, respect it and validate duplicates.

If no unique constraint exists, prefer preventing duplicates at the application layer unless existing product logic clearly allows duplicates.

### 9. Update Template Exercise Endpoint

`PATCH /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}`

Request fields:

- `sortOrder` optional integer
- `customTitle` nullable string
- `customInstructions` nullable string
- `customDurationMinutes` nullable integer
- `customSets` nullable integer
- `customRepetitions` nullable integer
- `customHoldSeconds` nullable integer
- `customFeedbackPrompt` nullable string
- `isRequired` optional boolean

Behavior:

- update template-specific overrides
- ensure the template exercise belongs to the given template
- do not update the underlying System Exercise
- return updated template exercise resource

### 10. Remove Template Exercise Endpoint

`DELETE /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}`

Behavior:

- remove the exercise relationship from the template
- ensure the template exercise belongs to the given template
- do not delete the underlying System Exercise
- do not delete the template
- return `204`

This is relationship deletion only and is allowed.

### 11. Reorder Template Exercises Endpoint

`POST /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/reorder`

Request fields:

- `items` required array
- each item:
  - `id` required, template exercise id
  - `sortOrder` required integer

Behavior:

- validate all provided template exercise IDs belong to the given template
- update sort order
- return updated ordered exercise list or updated template detail
- avoid partial updates if validation fails

Use a transaction.

### 12. Slug Generation

Template slug generation must be unique.

Use `Str::slug($title)`, fallback to `template` if empty.

If slug exists, append numeric suffix:

- stress-reset
- stress-reset-2
- stress-reset-3

Only generate slug on create.

Do not regenerate slug on update.

## Backend Implementation Notes

Use existing project structure and conventions.

Likely additions:

- `App\Http\Controllers\Admin\SystemMeasureTemplateController`
- `App\Http\Controllers\Admin\SystemMeasureTemplateExerciseController`
- `App\Http\Requests\Admin\CreateSystemMeasureTemplateRequest`
- `App\Http\Requests\Admin\UpdateSystemMeasureTemplateRequest`
- `App\Http\Requests\Admin\CreateSystemMeasureTemplateExerciseRequest`
- `App\Http\Requests\Admin\UpdateSystemMeasureTemplateExerciseRequest`
- `App\Http\Requests\Admin\ReorderSystemMeasureTemplateExercisesRequest`
- `App\Http\Resources\Admin\SystemMeasureTemplateResource`
- `App\Http\Resources\Admin\SystemMeasureTemplateExerciseResource`

Names can be adapted to existing style.

Use services only if the implementation would otherwise put too much business logic in controllers.

Business rules that must be server-side:

- admin-only access
- slug generation
- archive instead of template deletion
- enum validation
- exercise existence validation
- archived exercise rejection when adding to template
- child row ownership validation
- reorder transaction
- no company/employee access

## Angular Requirements

### 1. Admin Navigation / Routing

Add an Admin route/page for System Measure Templates using existing Angular admin portal routing style.

Suggested route:

- `/admin/system-measure-templates`

Do not create new portal concepts.

Use existing layout/navigation conventions.

If the admin nav has a menu/sidebar, add a link such as:

- `System-Templates`

Only if adding navigation is straightforward and consistent.

### 2. Admin System Measure Templates Page

Create an Angular page/component for managing templates.

Features:

- list templates
- search
- filters:
  - status
  - category
  - difficulty
  - featured
- create template
- edit template
- archive template
- open detail/edit view
- manage ordered exercises inside template

Keep UI simple but usable.

No drag-and-drop required.

Reordering can be done with up/down buttons or numeric order fields.

No user assignment UI.

No employee-facing display.

No recommendation engine UI.

### 3. Template Form Fields

Admin template form should support:

- title
- short description
- description
- category
- difficulty
- estimated duration minutes
- status
- featured flag

Slug should be displayed as read-only after create/update if useful, but not manually required.

### 4. Template Exercise Management UI

Inside the template detail/edit view:

- display ordered exercises
- allow adding an existing active System Exercise from the catalog
- allow editing overrides:
  - custom title
  - custom instructions
  - custom duration minutes
  - custom sets
  - custom repetitions
  - custom hold seconds
  - custom feedback prompt
  - required flag
- allow removing an exercise from the template
- allow reordering exercises

Exercise selection should use existing System Exercise Catalog API.

Prefer filtering/selecting active exercises only.

Do not create exercises from this UI.

Do not update underlying exercises from this UI.

### 5. Angular Services

All API calls must go through Angular services/API wrapper pattern.

Do not call `fetch` directly.

Do not place raw API calls directly in components if existing `AGENTS.md` discourages this.

Suggested services:

- `AdminSystemMeasureTemplateService`
- reuse existing `AdminSystemExerciseService` if available for exercise selection

Methods for templates:

- `listTemplates(filters)`
- `getTemplate(id)`
- `createTemplate(payload)`
- `updateTemplate(id, payload)`
- `archiveTemplate(id)`
- `addExercise(templateId, payload)`
- `updateTemplateExercise(templateId, templateExerciseId, payload)`
- `removeTemplateExercise(templateId, templateExerciseId)`
- `reorderExercises(templateId, payload)`

Use existing ApiClient conventions.

### 6. Angular Types

Add TypeScript interfaces matching OpenAPI/runtime shape.

Suggested types:

- `AdminSystemMeasureTemplate`
- `AdminSystemMeasureTemplateExercise`
- `SystemMeasureTemplateStatus`
- `SystemMeasureTemplateCategory`
- `SystemMeasureTemplateDifficulty`
- `ListSystemMeasureTemplatesParams`
- `CreateSystemMeasureTemplatePayload`
- `UpdateSystemMeasureTemplatePayload`
- `CreateSystemMeasureTemplateExercisePayload`
- `UpdateSystemMeasureTemplateExercisePayload`
- `ReorderSystemMeasureTemplateExercisesPayload`

Do not include admin/employee/company data not returned by API.

## OpenAPI Requirements

Update `docs/api/openapi.yaml`.

Document all new endpoints:

- `GET /admin/system-measure-templates`
- `POST /admin/system-measure-templates`
- `GET /admin/system-measure-templates/{id}`
- `PATCH /admin/system-measure-templates/{id}`
- `POST /admin/system-measure-templates/{id}/archive`
- `POST /admin/system-measure-templates/{id}/exercises`
- `PATCH /admin/system-measure-templates/{id}/exercises/{templateExerciseId}`
- `DELETE /admin/system-measure-templates/{id}/exercises/{templateExerciseId}`
- `POST /admin/system-measure-templates/{id}/exercises/reorder`

Document:

- request schemas
- response schemas
- pagination/list shape
- validation errors
- unauthenticated/forbidden errors
- not found errors
- enum values

Do not document endpoints that are not implemented.

Do not document user-facing template endpoints.

Do not document recommendation endpoints.

Do not document assignment endpoints.

## Tests

### Backend tests

Add Laravel feature tests for admin API.

Cover:

- platform admin can list templates
- ELYO_SUPPORT can list templates if this matches existing admin group behavior
- unauthenticated user is rejected
- employee/company user is forbidden
- list filters:
  - search
  - status
  - category
  - difficulty
  - isFeatured
- platform admin can create template
- slug is generated server-side
- slug collision gets suffix
- invalid enum values are rejected
- invalid numeric values are rejected
- platform admin can update template
- slug remains stable on title update
- platform admin can archive template
- archive is idempotent
- archived template is not deleted
- archive does not detach template exercises
- platform admin can add active exercise to template
- adding archived exercise is rejected
- duplicate exercise handling is validated according to schema/product decision
- platform admin can update template exercise overrides
- updating a template exercise from another template is rejected
- platform admin can remove template exercise relationship
- removing template exercise does not delete underlying System Exercise
- platform admin can reorder template exercises
- reorder rejects IDs that do not belong to the template
- reorder is transaction-safe

### Angular tests

Add focused Angular tests for:

- template service methods call expected endpoints
- list page loads templates
- filters trigger list reload
- create flow calls service with expected payload
- edit flow updates existing template
- archive action calls archive service
- detail view loads ordered exercises
- add exercise flow calls service with expected payload
- update override flow calls service with expected payload
- remove exercise flow calls service
- reorder flow calls service with expected payload
- exercise selection uses existing System Exercise service/API
- no UI affordance exists for creating exercises from the template builder

Use existing project test style.

Do not overbuild brittle DOM tests.

## Validation

Run non-destructive validation only:

- relevant Laravel admin API tests
- relevant Angular tests for new admin components/services
- `docker compose exec web npm run build`
- `git diff --check`
- `git diff --cached --check` if staging is used
- `git status --short`

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

## Expected Handoff

Report:

- summary
- files changed
- API endpoints added
- OpenAPI updates
- Angular routes/pages/services added
- admin authorization behavior
- template filters implemented
- exercise assignment behavior
- archive behavior
- slug behavior
- reorder behavior
- tests run
- build result
- validation commands
- open questions
- intentional deviations

## Acceptance Criteria

The task is complete when:

- platform admin can list, filter, create, edit, and archive System Measure Templates through API
- platform admin can manage ordered exercises inside templates through API
- Angular admin UI exists for System Measure Template management
- Angular admin UI can add/remove/reorder exercises inside templates
- template exercises can have per-template overrides
- no hard delete exists for templates
- removing template exercises does not delete System Exercises
- slug is generated server-side and remains stable on update
- OpenAPI documents all new endpoints
- company users and employees cannot access the admin endpoints
- existing company measure / QR behavior is untouched
- no user assignment UI exists
- no recommendation engine exists
- no point/streak logic is implemented
- tests and build pass
