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

## Implementation Plan

Plan date: 2026-06-11. Plan only — no implementation files have been modified yet.

### A. Codebase Findings That Shape the Plan

1. The admin route group already exists in `apps/api-laravel/routes/api.php` (lines 52-70) with `auth:sanctum` + `role:ELYO_ADMIN,ELYO_SUPPORT` middleware and `admin` prefix. New routes go inside this group; no new auth work is needed.
2. The System Exercise Catalog (`SystemExerciseController`, `CreateSystemExerciseRequest`, `SystemExerciseResource`, `AdminSystemExerciseTest`) is the convention template: camelCase API fields mapped to snake_case columns via a `COLUMN_MAP`, lowercase `LIKE` search across title/short_description/description/slug, enum validation via model constants with `Rule::in`, `paginate()->appends()` resource collection (`data`/`links`/`meta`), newest-first sort (`created_at` desc, `id` desc), `generateUniqueSlug()` with numeric suffix, and a POST `/archive` action that sets status and returns the resource.
3. **Schema gap:** `system_measure_templates` (migration `2026_06_10_030000_create_system_measures_tables.php`) has **no `category` and no `is_featured` column**. The task requires both as form fields, list/detail fields, and filters. The existing schema cannot support this, so one small additive migration is justified under the task's migration rule.
4. The template exercise order column is **`position`**, not `sort_order` (`system_measure_template_exercises.position`, with a **unique constraint on `(system_measure_template_id, position)`**). The API will expose `sortOrder` (camelCase, per task) and map it to `position` server-side.
5. The `(template, position)` unique constraint is non-deferrable in PostgreSQL, so reorder must avoid transient collisions: inside the transaction, first shift all affected rows by a large offset (single statement), then write final positions.
6. There is **no unique constraint on `(system_measure_template_id, system_exercise_id)`**, so duplicate prevention must be application-layer validation (task's stated preference applies).
7. `SystemMeasureTemplate::booted()` has a `saving` hook that sets `slug ??= Str::slug(title)` (not unique-safe), `difficulty ??= BEGINNER`, `status ??= ACTIVE`. The controller must generate the unique slug explicitly before save (same pattern as `SystemExerciseController::generateUniqueSlug()`), so the hook fallback never produces collisions. Model/schema status default is `ACTIVE`, so per the task ("default `DRAFT` or existing schema/model default") the create default is **`ACTIVE`**, consistent with the exercise catalog.
8. `system_measure_templates` also has `goal_summary`, `recommended_frequency`, `default_points`, `streak_enabled`, `requires_feedback`. These are **not exposed or accepted** in v1: the task's field lists omit them, and the acceptance criteria forbid point/streak logic. They keep their DB defaults. (Intentional deviation noted in handoff.)
9. Factories: `SystemMeasureTemplateFactory` exists (with `withExercises()` and `draft()` states). There is **no `SystemMeasureTemplateExerciseFactory`** — add one for tests (test support, not a data-model change).
10. Angular conventions: standalone components with inline templates under `features/admin/pages/<feature>/`, `ApiClient`-based services under `features/admin/services/`, interfaces under `features/admin/models/`, lazy routes in `app.routes.ts` under the `admin` shell, nav links in `shared/shells/admin-shell.component.ts`, German UI labels, signals + `ReactiveFormsModule`. `PaginatedResponse<T>` already exists in `admin-system-exercise.models.ts` and will be reused.
11. OpenAPI (`docs/api/openapi.yaml`, ~2357 lines) already documents `/admin/system-exercises*` with reusable schemas (`SystemExerciseStatus`, `AdminSystemExercise`, `AdminSystemExerciseListResponse`, etc.). New template schemas/paths follow that exact style.

### B. Step 1 — Additive migration + model update (category, isFeatured)

New file: `apps/api-laravel/database/migrations/2026_06_11_000000_add_category_and_is_featured_to_system_measure_templates_table.php`

- `up()`: add `category` (string, default `'MIXED'`) and `is_featured` (boolean, default `false`) to `system_measure_templates`; add index on `category`.
- `down()`: drop both columns. Fully additive and reversible; no destructive change; user snapshot tables untouched.

Update `app/Models/SystemMeasureTemplate.php`:

- Add the fixed category constants from the task's Final Decisions, exactly: `CATEGORY_MOBILITY`, `CATEGORY_STRENGTH`, `CATEGORY_BREATHING`, `CATEGORY_MINDFULNESS`, `CATEGORY_EDUCATION`, `CATEGORY_REFLECTION`, `CATEGORY_MIXED` (values `MOBILITY`, `STRENGTH`, `BREATHING`, `MINDFULNESS`, `EDUCATION`, `REFLECTION`, `MIXED`). No other values (`STRESS_RELIEF`, `SLEEP`, `GENERAL` are explicitly excluded).
- Add `category`, `is_featured` to `$fillable`; add `'is_featured' => 'boolean'` cast; add `category ??= self::CATEGORY_MIXED` to the `saving` hook (default `MIXED` when not provided) for consistency with existing defaults.

Update `database/factories/SystemMeasureTemplateFactory.php`: include `category` and `is_featured` in `definition()`, add `featured()` and `archived()` states.

New file: `database/factories/SystemMeasureTemplateExerciseFactory.php` (template/exercise FKs, sequential `position`, `is_required` true, null overrides).

### C. Step 2 — Backend routes, requests, resources, controllers

Routes (inside the existing admin group in `routes/api.php`, after the system-exercise block):

```php
Route::get('/system-measure-templates', [SystemMeasureTemplateController::class, 'index']);
Route::post('/system-measure-templates', [SystemMeasureTemplateController::class, 'store']);
Route::get('/system-measure-templates/{systemMeasureTemplate}', [SystemMeasureTemplateController::class, 'show']);
Route::patch('/system-measure-templates/{systemMeasureTemplate}', [SystemMeasureTemplateController::class, 'update']);
Route::post('/system-measure-templates/{systemMeasureTemplate}/archive', [SystemMeasureTemplateController::class, 'archive']);
Route::post('/system-measure-templates/{systemMeasureTemplate}/exercises', [SystemMeasureTemplateExerciseController::class, 'store']);
Route::patch('/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}', [SystemMeasureTemplateExerciseController::class, 'update']);
Route::delete('/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}', [SystemMeasureTemplateExerciseController::class, 'destroy']);
Route::post('/system-measure-templates/{systemMeasureTemplate}/exercises/reorder', [SystemMeasureTemplateExerciseController::class, 'reorder']);
```

No DELETE route for templates (archive only). Child ownership (`templateExercise` belongs to `systemMeasureTemplate`) is enforced with an explicit check in the controller returning 404 on mismatch (explicit check preferred over implicit `scopeBindings()` for testability and parity with existing explicit style).

New controllers:

1. `app/Http/Controllers/Admin/SystemMeasureTemplateController.php`
   - `index`: validate `search`, `status`, `category`, `difficulty`, `isFeatured` (boolean), `perPage` (1-100, default 25), `page`; build query with `withCount('templateExercises as exercise_count')`; lowercase `LIKE` search over `title`, `short_description`, `description`, `slug`; newest-first sort; return `SystemMeasureTemplateResource::collection($query->paginate($perPage)->appends($request->query()))`.
   - `store`: `CreateSystemMeasureTemplateRequest`; map camelCase→snake_case via `COLUMN_MAP`; `slug = generateUniqueSlug(title)` (fallback base `'template'` per task); `status` default `ACTIVE`, `is_featured` default `false`, `category` default `MIXED`; `created_by_user_id = $request->user()?->id`; return resource with 201.
   - `show`: eager-load `templateExercises.exercise.tags` (ordered by `position` via existing relation) and `withCount`; return detail resource with nested exercises.
   - `update`: `UpdateSystemMeasureTemplateRequest`; apply only present fields; **never touch `slug`**; return updated resource (loaded like `show`).
   - `archive`: set `status = ARCHIVED`, save, return resource. Idempotent by construction (re-setting `ARCHIVED` is a no-op); does not touch template exercises.
   - `generateUniqueSlug()`: `Str::slug($title)`, fallback `'template'`, numeric suffix starting at 2 (`stress-reset`, `stress-reset-2`, …) — same as `SystemExerciseController`.
2. `app/Http/Controllers/Admin/SystemMeasureTemplateExerciseController.php`
   - `store`: `CreateSystemMeasureTemplateExerciseRequest`; reject archived exercises with 422 (validation error on `systemExerciseId`, task-preferred); reject duplicates (exercise already in this template) with 422 at application layer (finding A6); if `sortOrder` missing, append at `max(position) + 1`; if provided and already taken, insert by shifting subsequent rows (offset-then-renumber inside a transaction to respect the unique constraint) — simplest compliant alternative: treat provided `sortOrder` colliding with an existing position as 422 and document it; decision in Open Questions, default to **append-or-explicit-free-position with 422 on collision** to keep v1 controlled. Returns created `SystemMeasureTemplateExerciseResource` (with nested exercise) and 201.
   - `update`: `UpdateSystemMeasureTemplateExerciseRequest`; verify child ownership (404 otherwise); update overrides + `is_required`; `sortOrder` change follows the same collision rule as `store`; never writes to the underlying `system_exercises` row; returns updated resource.
   - `destroy`: verify ownership; delete the pivot-style row only; 204. Underlying `SystemExercise` untouched.
   - `reorder`: `ReorderSystemMeasureTemplateExercisesRequest`; **requires the complete set of the template's exercise IDs** and rejects partial payloads before any write. Exact check order:
     1. Validation (form request): `items` shape, distinct `items.*.id`, distinct `items.*.sortOrder` → 422 on duplicate sortOrder values.
     2. Fetch all existing template exercise IDs for `{systemMeasureTemplate}`; fetch submitted IDs from the payload.
     3. If any submitted ID does not belong to this template (foreign or unknown) → **404**.
     4. If the submitted ID set does not exactly match the full existing ID set (missing IDs / partial payload) → **422**.
     5. Only after all checks pass: inside `DB::transaction()`, single statement shifting all the template's rows by a large offset (e.g. `position + 100000`), then per-item final `position` writes (avoids transient unique-constraint violations, finding A5).
     No positions are written if any check fails. Returns the full ordered exercise list (`data: [...]`) so the UI can re-render.

New form requests (all `authorize(): true` — route middleware owns authorization, matching existing admin requests):

- `CreateSystemMeasureTemplateRequest`: `title` required string max:255; `shortDescription`/`description` nullable string; `category` sometimes + `Rule::in` (model constants); `difficulty` sometimes + `Rule::in(BEGINNER, INTERMEDIATE, ADVANCED)`; `estimatedDurationMinutes` nullable integer min:1 max:100000; `status` sometimes + `Rule::in(DRAFT, ACTIVE, ARCHIVED)`; `isFeatured` sometimes boolean.
- `UpdateSystemMeasureTemplateRequest`: same rules, all `sometimes`; no `slug` field accepted.
- `CreateSystemMeasureTemplateExerciseRequest`: `systemExerciseId` required integer `exists:system_exercises,id`; `sortOrder` sometimes integer min:1; `customTitle` nullable string max:255; `customInstructions`/`customFeedbackPrompt` nullable string; `customDurationMinutes`/`customSets`/`customRepetitions`/`customHoldSeconds` nullable integer min:1 max:100000; `isRequired` sometimes boolean (default true server-side). Archived-exercise and duplicate checks live in the controller/`withValidator` so they produce 422 with field-level errors.
- `UpdateSystemMeasureTemplateExerciseRequest`: same as create minus `systemExerciseId`, all optional.
- `ReorderSystemMeasureTemplateExercisesRequest`: `items` required array min:1; `items.*.id` required integer; `items.*.sortOrder` required integer min:1; `distinct` on both ids and sortOrders (duplicate `sortOrder` → 422 via validation). The complete-set and ownership checks (404 for foreign IDs, 422 for partial payloads) run in the controller before any write — see the `reorder` action above.

New resources:

- `app/Http/Resources/Admin/SystemMeasureTemplateResource.php`: `id`, `slug`, `title`, `shortDescription`, `description`, `category`, `difficulty`, `estimatedDurationMinutes`, `status`, `isFeatured`, `exerciseCount` (`whenCounted`), `exercises` (`SystemMeasureTemplateExerciseResource::collection($this->whenLoaded('templateExercises'))` — present on detail, absent on list), `createdAt`, `updatedAt`. One resource serves list and detail via conditional fields, mirroring `SystemExerciseResource` usage.
- `app/Http/Resources/Admin/SystemMeasureTemplateExerciseResource.php`: `id`, `systemExerciseId`, `sortOrder` (from `position`), `customTitle`, `customInstructions`, `customDurationMinutes`, `customSets`, `customRepetitions`, `customHoldSeconds`, `customFeedbackPrompt`, `isRequired`, `exercise` (nested, `whenLoaded`): `id`, `slug`, `title`, `shortDescription`, `exerciseType`, `difficulty`, `defaultDurationMinutes`, `status`, `tags` (reuse `SystemExerciseTagResource`).

### D. Step 3 — OpenAPI (`docs/api/openapi.yaml`)

Add component schemas following the existing `AdminSystemExercise*` style:

- `SystemMeasureTemplateStatus` (`DRAFT|ACTIVE|ARCHIVED`), `SystemMeasureTemplateCategory` (new constants), reuse `SystemExerciseDifficulty` for difficulty (same values) or add `SystemMeasureTemplateDifficulty` if separation is preferred — plan: reuse `SystemExerciseDifficulty` to avoid duplicate enums, note in handoff.
- `AdminSystemMeasureTemplate` (list shape incl. `exerciseCount`), `AdminSystemMeasureTemplateDetail` (extends with `exercises`), `AdminSystemMeasureTemplateExercise` (incl. nested `exercise`), `AdminSystemMeasureTemplateCreatePayload`, `AdminSystemMeasureTemplateUpdatePayload`, `AdminSystemMeasureTemplateExerciseCreatePayload`, `AdminSystemMeasureTemplateExerciseUpdatePayload`, `AdminSystemMeasureTemplateExercisesReorderPayload`, `AdminSystemMeasureTemplateListResponse` (`data`/`links`/`meta`).

Add paths (all tagged `Admin`, documenting 401 unauthenticated, 403 forbidden for company/employee roles, 404 not found / wrong-parent child, 422 validation incl. archived-exercise and duplicate cases, enum values, pagination params):

- `GET|POST /admin/system-measure-templates`
- `GET|PATCH /admin/system-measure-templates/{systemMeasureTemplate}`
- `POST /admin/system-measure-templates/{systemMeasureTemplate}/archive`
- `POST /admin/system-measure-templates/{systemMeasureTemplate}/exercises`
- `PATCH|DELETE /admin/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}` (DELETE → 204)
- `POST /admin/system-measure-templates/{systemMeasureTemplate}/exercises/reorder` — the description and 422 response must explicitly document that the payload requires the **complete set** of the template's exercise IDs: partial payloads (missing IDs) and duplicate `sortOrder` values return 422 with no positions changed; IDs not belonging to the template return 404.

No user-facing, assignment, or recommendation endpoints are documented.

### E. Step 4 — Angular admin UI

New files (mirroring the system-exercises feature):

1. `apps/web-angular/src/app/features/admin/models/admin-system-measure-template.models.ts`
   - `SystemMeasureTemplateStatus`, `SystemMeasureTemplateCategory`, reuse `SystemExerciseDifficulty` import for difficulty; `AdminSystemMeasureTemplate`, `AdminSystemMeasureTemplateExercise` (with nested exercise summary), `ListSystemMeasureTemplatesParams`, `CreateSystemMeasureTemplatePayload`, `UpdateSystemMeasureTemplatePayload`, `CreateSystemMeasureTemplateExercisePayload`, `UpdateSystemMeasureTemplateExercisePayload`, `ReorderSystemMeasureTemplateExercisesPayload`. Reuse `PaginatedResponse<T>` from `admin-system-exercise.models.ts`.
2. `apps/web-angular/src/app/features/admin/services/admin-system-measure-template.service.ts`
   - `ApiClient`-based methods: `listTemplates(filters)`, `getTemplate(id)`, `createTemplate(payload)`, `updateTemplate(id, payload)`, `archiveTemplate(id)`, `addExercise(templateId, payload)`, `updateTemplateExercise(templateId, templateExerciseId, payload)`, `removeTemplateExercise(templateId, templateExerciseId)`, `reorderExercises(templateId, payload)`. No direct `fetch`.
3. `apps/web-angular/src/app/features/admin/pages/system-measure-templates/admin-system-measure-templates.component.ts`
   - Standalone, inline template, signals + reactive forms, German labels, same visual style as `admin-system-exercises.component.ts`.
   - List view: search box; status/category/difficulty/featured filters; paginated table with `title`, `slug`, `category`, `difficulty`, `estimatedDurationMinutes`, `status`, `isFeatured`, `exerciseCount`; create button; per-row edit + archive (with confirm) actions.
   - Create/edit form: title, short description, description, category, difficulty, estimated duration minutes, status, featured checkbox; slug shown read-only when editing; no slug input.
   - Detail/exercise management section (expanded inline for the selected template, same single-page pattern the admin area already uses — no new routing concepts): ordered exercise list by `sortOrder`; up/down buttons calling `reorderExercises` with the full recomputed `items` array (no drag-and-drop); "add exercise" select fed by `AdminSystemExerciseService.listExercises({ status: 'ACTIVE' })` (reused service, active-only); per-row override edit form (custom title/instructions/duration/sets/repetitions/hold seconds/feedback prompt, required flag); remove button (relationship delete, with confirm). No affordance to create or edit underlying exercises; no assignment/recommendation/points UI.
4. Routing/nav:
   - `app.routes.ts`: add `{ path: 'system-measure-templates', loadComponent: ... }` under the existing admin shell children (after `system-exercises`).
   - `shared/shells/admin-shell.component.ts`: add a `System-Templates` sidebar link next to the existing `System-Übungen` link (straightforward and consistent, so included).

### F. Step 5 — Tests

Backend — new `apps/api-laravel/tests/Feature/AdminSystemMeasureTemplateTest.php` (and optionally a separate `AdminSystemMeasureTemplateExerciseTest.php` if it grows large), following `AdminSystemExerciseTest` style (`RefreshDatabase`, `User::factory()->platformAdmin()`, `actingAs`):

- Authorization: platform admin (ELYO_ADMIN) can list; ELYO_SUPPORT can list (matches existing admin group middleware); unauthenticated → 401; EMPLOYEE/COMPANY_ADMIN/COMPANY_MANAGER → 403 (list and at least one write endpoint).
- List: expected fields incl. `exerciseCount`; pagination shape `data`/`links`/`meta`; newest-first; each filter (`search` across title/shortDescription/description/slug, `status`, `category`, `difficulty`, `isFeatured`).
- Create: 201 + fields; slug generated server-side (payload slug ignored/absent); slug collision → `-2`/`-3` suffix; empty-slug title falls back to `template`; invalid enum → 422; invalid numeric → 422; `created_by_user_id` set.
- Update: fields updated; **slug stable when title changes**; invalid values → 422.
- Archive: sets `ARCHIVED`; idempotent (second call 200, still `ARCHIVED`); template row still exists; template exercises still attached (count unchanged).
- Template exercises: add active exercise (appends at end when `sortOrder` omitted); adding archived exercise → 422; duplicate exercise in same template → 422; update overrides; updating a template exercise via another template's URL → 404; remove → 204, underlying `system_exercises` row still exists.
- Reorder (all "unchanged" assertions verify every row's `position` in the DB after the call):
  - valid complete reorder (all template exercise IDs present) succeeds and applies the new order;
  - **partial payload** (subset of the template's exercise IDs) → 422, positions unchanged;
  - **duplicate `sortOrder` values** → 422 (validation), positions unchanged;
  - **foreign template exercise ID** (belongs to another template or does not exist) → 404, positions unchanged.

Angular — `admin-system-measure-template.service.spec.ts` and `admin-system-measure-templates.component.spec.ts`, following the existing exercise spec style (HttpClientTesting / service spies, no brittle DOM assertions):

- Service methods hit expected URLs/verbs/payloads (all nine methods).
- List page loads templates on init; filter submit triggers reload with params.
- Create/edit flows call service with expected payload; archive action calls `archiveTemplate`.
- Detail view renders exercises in `sortOrder` order; add/update-override/remove/reorder flows call service with expected payloads.
- Exercise selection calls `AdminSystemExerciseService.listExercises` with `status: 'ACTIVE'`.
- No create-exercise affordance exists in the template builder (assert absence of such an action/button hook).

### G. Step 6 — Validation (non-destructive only)

- `docker compose exec api php artisan test --filter='AdminSystemMeasureTemplate'` (plus `AdminSystemExerciseTest|SystemMeasureDataModelTest` as regression guard)
- `docker compose exec api php artisan migrate` (additive migration only — **no** `migrate:fresh` / `db:wipe`)
- Angular tests for the new admin specs via the project's existing test runner
- `docker compose exec web npm run build`
- `git diff --check`, `git status --short`

### H. Confirmed Decisions / Remaining Notes

1. **Category enum values (confirmed by user, 2026-06-11)** — exactly `MOBILITY`, `STRENGTH`, `BREATHING`, `MINDFULNESS`, `EDUCATION`, `REFLECTION`, `MIXED`; default `MIXED`. `STRESS_RELIEF`, `SLEEP`, and `GENERAL` must not be used.
2. **`sortOrder` collision on add/update (non-reorder paths) — confirmed** — an explicitly provided colliding `sortOrder` returns 422; rows are never auto-shifted. The reorder endpoint is the only way to change multiple positions.
3. **Reorder requires the complete ID set (confirmed by user, 2026-06-11)** — partial payloads → 422, foreign IDs → 404, duplicate sortOrders → 422 via validation; all checked before any write; positions only updated inside a transaction after all checks pass.
4. **Difficulty enum reuse in OpenAPI/Angular** — plan reuses the existing `SystemExerciseDifficulty` enum (identical values) instead of duplicating; a separate `SystemMeasureTemplateDifficulty` alias type will be exported in Angular per the task's suggested type list.
5. **Hidden existing columns** (`goal_summary`, `recommended_frequency`, `default_points`, `streak_enabled`, `requires_feedback`) stay unexposed in v1 (see Finding A8).

### I. Explicitly Out of Scope (per task)

- User assignment of templates, snapshot tables, recommendation engine, points/streak logic, employee/company-facing endpoints or UI, hard deletes of templates or exercises, drag-and-drop reordering, changes to existing company measure / QR check-in / survey behavior, changes to `../ELYO`.

### J. File Inventory (planned changes)

Backend (new): migration `2026_06_11_000000_add_category_and_is_featured_to_system_measure_templates_table.php`; `SystemMeasureTemplateController`; `SystemMeasureTemplateExerciseController`; 5 form requests (`CreateSystemMeasureTemplateRequest`, `UpdateSystemMeasureTemplateRequest`, `CreateSystemMeasureTemplateExerciseRequest`, `UpdateSystemMeasureTemplateExerciseRequest`, `ReorderSystemMeasureTemplateExercisesRequest`); 2 resources (`SystemMeasureTemplateResource`, `SystemMeasureTemplateExerciseResource`); `SystemMeasureTemplateExerciseFactory`; `tests/Feature/AdminSystemMeasureTemplateTest.php`.

Backend (modified): `routes/api.php` (9 routes in existing admin group); `app/Models/SystemMeasureTemplate.php` (category/is_featured fillable, cast, constants, saving default); `database/factories/SystemMeasureTemplateFactory.php` (new columns + states).

Frontend (new): `admin-system-measure-template.models.ts`; `admin-system-measure-template.service.ts` + spec; `pages/system-measure-templates/admin-system-measure-templates.component.ts` + spec.

Frontend (modified): `app.routes.ts` (one lazy route); `shared/shells/admin-shell.component.ts` (one nav link).

Contract (modified): `docs/api/openapi.yaml` (9 paths + ~10 schemas).
