# Task: Platform Admin System Exercise Catalog v1

Date: 2026-06-11

## Context

We are working in the ELYO MVP.

The System Measurements data model already exists and has been cleaned up.

The current System Measurement domain is separate from existing company-created measures and QR check-in flows.

Existing company measures / QR check-in / employee participation behavior must not be refactored or changed in this task.

This task implements the first platform-admin management surface for the System Exercise Catalog.

Read and follow:

- `AGENTS.md`
- existing Laravel conventions
- existing Angular conventions
- existing OpenAPI contract rules
- existing admin portal route/auth/role patterns
- existing test style

Do not modify legacy `../ELYO`.

## Goal

Implement a platform-admin-only System Exercise Catalog management slice.

This includes:

- Laravel Admin API for System Exercises
- Laravel Admin API for reading System Exercise Tags
- Angular Admin UI for listing/filtering/creating/editing/archiving System Exercises
- OpenAPI documentation for all new/changed API endpoints
- Backend and Angular tests

This is intentionally a larger slice to test the coding agent, but it must remain focused on the System Exercise Catalog.

## Product Decisions

Use these decisions as fixed requirements.

### Scope

Implement API and Angular Admin UI for System Exercises.

### Tags

System Exercise Tags are read-only in this slice.

The Admin UI may assign existing tags to exercises.

Do not implement tag create/update/delete UI or API in this task.

Tags are managed by seed/catalog data for now.

### Delete behavior

Do not hard-delete System Exercises.

Use archive behavior:

- `status = ARCHIVED`

Existing/future templates and user assignments must not break because an exercise was removed.

### Slug behavior

Slug is generated server-side from title.

The Admin UI should not require slug input.

If title changes, keep the slug stable unless the current project has an established slug-regeneration convention.

For v1, prefer:

- generate slug on create
- do not automatically change slug on update

### OpenAPI

Any new API endpoint must be documented in `docs/api/openapi.yaml`.

No API change without OpenAPI update.

## Existing Data Model

Use the existing System Measurement tables/models from the current branch.

Expected relevant models/tables include:

- `system_exercises`
- `system_exercise_tags`
- `system_exercise_tag`
- `system_measure_templates`
- `system_measure_template_exercises`
- `user_system_measures`
- `user_system_measure_exercises`
- `user_system_measure_exercise_completions`

This task should primarily touch:

- `SystemExercise`
- `SystemExerciseTag`
- their pivot relation
- admin controllers/requests/resources
- Angular admin feature area
- OpenAPI
- tests

Do not change the data model unless a small missing index/cast/fillable issue is found and clearly justified.

## Backend Requirements

### 1. Admin Routes

Add platform-admin-only routes under the existing admin portal route group.

Use existing route naming/style.

Suggested endpoints:

- `GET /api/admin/system-exercises`
- `POST /api/admin/system-exercises`
- `GET /api/admin/system-exercises/{systemExercise}`
- `PATCH /api/admin/system-exercises/{systemExercise}`
- `POST /api/admin/system-exercises/{systemExercise}/archive`
- `GET /api/admin/system-exercise-tags`

Do not add a hard delete endpoint.

Do not expose these endpoints to company or employee users.

### 2. Authorization / Portal Scope

Only platform/admin users should access these endpoints.

Use the existing admin portal middleware/role conventions.

Add tests proving:

- platform admin can access
- company admin cannot access
- company manager cannot access
- employee cannot access
- unauthenticated user cannot access

Do not invent a new auth system.

### 3. Exercise List Endpoint

`GET /api/admin/system-exercises`

Support filtering:

- `search`
- `status`
- `exerciseType`
- `difficulty`
- `tagCategory`
- `tagKey`

Support pagination using existing project style.

Sort default:

- newest first or existing admin-list convention

Response should include enough fields for list UI:

- `id`
- `slug`
- `title`
- `shortDescription`
- `exerciseType`
- `difficulty`
- `defaultDurationMinutes`
- `status`
- `tags`
- `createdAt`
- `updatedAt`

Search should match at least:

- title
- short description
- description
- slug

Tag filtering should work through the pivot table.

### 4. Exercise Detail Endpoint

`GET /api/admin/system-exercises/{systemExercise}`

Return full editable detail:

- `id`
- `slug`
- `title`
- `shortDescription`
- `description`
- `exerciseType`
- `difficulty`
- `defaultDurationMinutes`
- `defaultSets`
- `defaultRepetitions`
- `defaultHoldSeconds`
- `instructions`
- `safetyNotes`
- `contraindications`
- `defaultFeedbackPrompt`
- `requiresFeedback`
- `status`
- `tags`
- `createdAt`
- `updatedAt`

### 5. Create Exercise Endpoint

`POST /api/admin/system-exercises`

Request fields:

- `title` required
- `shortDescription` nullable
- `description` nullable
- `exerciseType` required
- `difficulty` required
- `defaultDurationMinutes` nullable integer
- `defaultSets` nullable integer
- `defaultRepetitions` nullable integer
- `defaultHoldSeconds` nullable integer
- `instructions` nullable
- `safetyNotes` nullable
- `contraindications` nullable
- `defaultFeedbackPrompt` nullable
- `requiresFeedback` boolean, default true
- `status` optional, default `DRAFT` or `ACTIVE` based on existing catalog conventions
- `tagIds` array of existing tag IDs

Server behavior:

- generate unique slug from title
- set `created_by_user_id` from authenticated admin if available
- validate enum values
- validate numeric ranges
- validate tags exist
- sync tags exactly to provided tag IDs
- return created resource with `201`

Allowed `exerciseType`:

- `MOBILITY`
- `STRENGTH`
- `BREATHING`
- `MINDFULNESS`
- `EDUCATION`
- `REFLECTION`

Allowed `difficulty`:

- `BEGINNER`
- `INTERMEDIATE`
- `ADVANCED`

Allowed `status`:

- `DRAFT`
- `ACTIVE`
- `ARCHIVED`

Do not allow arbitrary types/status values.

### 6. Update Exercise Endpoint

`PATCH /api/admin/system-exercises/{systemExercise}`

Behavior:

- update editable fields
- do not regenerate slug automatically
- validate enum values
- validate numeric ranges
- validate tags exist
- if `tagIds` is present, sync tags exactly
- return updated resource

Do not hard-delete existing exercises.

Do not break existing templates or user snapshots.

### 7. Archive Endpoint

`POST /api/admin/system-exercises/{systemExercise}/archive`

Behavior:

- set `status = ARCHIVED`
- return updated resource
- do not detach tags
- do not remove from templates
- do not remove user snapshot references
- idempotent if already archived

Add tests proving archiving does not delete the exercise or detach relationships.

### 8. Tag List Endpoint

`GET /api/admin/system-exercise-tags`

Tags are read-only.

Support filtering:

- `category`
- `isActive`
- `search`

Response fields:

- `id`
- `category`
- `key`
- `label`
- `description`
- `sortOrder`
- `isActive`

Sort:

- category
- sortOrder
- label

Do not implement tag mutation endpoints.

## Backend Implementation Notes

Use existing project structure and conventions.

Likely additions:

- `App\Http\Controllers\Admin\SystemExerciseController`
- `App\Http\Controllers\Admin\SystemExerciseTagController`
- `App\Http\Requests\Admin\CreateSystemExerciseRequest`
- `App\Http\Requests\Admin\UpdateSystemExerciseRequest`
- `App\Http\Resources\Admin\SystemExerciseResource`
- `App\Http\Resources\Admin\SystemExerciseTagResource`

Names can be adapted to existing style.

Use services only if existing admin features use service layers. Do not over-engineer.

Business rules that must be server-side:

- admin-only access
- slug generation
- archive instead of delete
- enum validation
- tag existence validation
- no company/employee access

## Angular Requirements

### 1. Admin Navigation / Routing

Add an Admin route/page for System Exercises using existing Angular admin portal routing style.

Suggested route:

- `/admin/system-exercises`

Do not create new portal concepts.

Use existing layout/navigation conventions.

If the admin nav has a menu/sidebar, add a link such as:

- `System-Übungen`

Only if adding navigation is straightforward and consistent.

### 2. Admin System Exercises Page

Create an Angular page/component for managing System Exercises.

Features:

- list exercises
- search
- filters:
  - status
  - exerciseType
  - difficulty
  - tag category/key or tag selector
- create exercise
- edit exercise
- archive exercise
- assign existing tags to exercise

Keep UI simple.

No drag-and-drop.

No template builder in this task.

No user assignment UI.

No user-facing exercise display.

### 3. Form Fields

Admin form should support:

- title
- short description
- description
- exercise type
- difficulty
- default duration minutes
- default sets
- default repetitions
- default hold seconds
- instructions
- safety notes
- contraindications
- default feedback prompt
- requires feedback
- status
- tags

Slug should be displayed as read-only after create/update if useful, but not manually required.

### 4. Tags in UI

Tags are read-only catalog entries.

Admin can assign existing tags to an exercise.

Use a simple multi-select, checkbox list, grouped list, or existing project UI pattern.

Do not implement tag creation/editing.

### 5. Archive UI

Use archive action instead of delete.

Confirm action if existing UI patterns support confirmations.

Archived exercises should remain visible when status filter includes `ARCHIVED`.

### 6. Angular Services

All API calls must go through Angular services/API wrapper pattern.

Do not call `fetch` directly.

Do not place raw API calls directly in components if existing `AGENTS.md` discourages this.

Suggested service:

- `AdminSystemExerciseService`

Methods:

- `listExercises(filters)`
- `getExercise(id)`
- `createExercise(payload)`
- `updateExercise(id, payload)`
- `archiveExercise(id)`
- `listTags(filters)`

Use existing ApiClient conventions.

### 7. Angular Types

Add TypeScript interfaces matching OpenAPI/runtime shape.

Suggested types:

- `AdminSystemExercise`
- `AdminSystemExerciseTag`
- `SystemExerciseType`
- `SystemExerciseDifficulty`
- `SystemExerciseStatus`
- `ListSystemExercisesParams`
- `CreateSystemExercisePayload`
- `UpdateSystemExercisePayload`

Do not include admin/employee/company data not returned by API.

## OpenAPI Requirements

Update `docs/api/openapi.yaml`.

Document all new endpoints:

- `GET /admin/system-exercises`
- `POST /admin/system-exercises`
- `GET /admin/system-exercises/{id}`
- `PATCH /admin/system-exercises/{id}`
- `POST /admin/system-exercises/{id}/archive`
- `GET /admin/system-exercise-tags`

Document:

- request schemas
- response schemas
- pagination/list shape
- validation errors
- unauthenticated/forbidden errors
- not found errors
- enum values

Do not document endpoints that are not implemented.

Do not document tag mutation endpoints.

Do not document user-facing exercise endpoints.

Do not document recommendation endpoints.

## Tests

### Backend tests

Add Laravel feature tests for admin API.

Cover:

- platform admin can list exercises
- unauthenticated user is rejected
- employee/company user is forbidden
- list filters:
  - search
  - status
  - exerciseType
  - difficulty
  - tagCategory/tagKey
- platform admin can create exercise
- slug is generated server-side
- invalid enum values are rejected
- invalid tag IDs are rejected
- platform admin can update exercise
- slug remains stable on title update
- tag assignment syncs exactly
- platform admin can archive exercise
- archive is idempotent
- archived exercise is not deleted
- archive does not detach tags
- platform admin can list tags
- tag filters work

Also add or keep tests proving company/employee roles cannot access these admin endpoints.

### Angular tests

Add focused Angular tests for:

- service methods call expected endpoints
- list page loads exercises
- filters trigger list reload
- create flow calls service with expected payload
- edit flow updates existing exercise
- archive action calls archive service
- tag assignment is included in payload
- read-only tags are displayed/selectable but not creatable

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
- exercise filters implemented
- tag read-only behavior
- archive behavior
- slug behavior
- tests run
- build result
- validation commands
- open questions
- intentional deviations

## Acceptance Criteria

The task is complete when:

- platform admin can list, filter, create, edit, and archive System Exercises through API
- platform admin can list System Exercise Tags through API
- Angular admin UI exists for System Exercise Catalog management
- tags are read-only but assignable to exercises
- no hard delete exists
- slug is generated server-side and remains stable on update
- OpenAPI documents all new endpoints
- company users and employees cannot access the admin endpoints
- existing company measure / QR behavior is untouched
- no user assignment UI exists
- no recommendation engine exists
- no point/streak logic is implemented
- tests and build pass

## Implementation Plan

Plan created 2026-06-11 after inspecting the current branch. No production code has been changed yet.

### Codebase facts the plan is grounded on

- Admin routes live in `apps/api-laravel/routes/api.php` inside `Route::middleware('role:ELYO_ADMIN,ELYO_SUPPORT')->prefix('admin')->group(...)` (line 50). This is the existing "admin portal" convention.
- `SystemExercise` and `SystemExerciseTag` models already exist with the exact enum constants required by this task (`TYPE_*`, `DIFFICULTY_*`, `STATUS_*`, `CATEGORY_*`), correct `fillable`/`casts`, a `tags()` BelongsToMany over pivot `system_exercise_tag`, and a `saving` hook that backfills `slug` from `title` only when slug is null. The hook does **not** ensure slug uniqueness; the DB has a unique index on `system_exercises.slug`.
- Factories exist: `SystemExerciseFactory`, `SystemExerciseTagFactory`. Seeder exists: `SystemExerciseSeeder`.
- Existing admin controllers (`AdminCompanyController`, `AdminPartnerController`, `AdminPointsController`) use inline validation and raw JSON, but `AGENTS.md` mandates Form Requests + Resources, and the Company namespace follows that. New code will use Form Requests and Resources (first entries in the `Admin` resource namespace). `App\Http\Requests\Admin` already exists.
- The only paginated admin endpoint (`AdminPartnerController::index`) uses `$query->paginate(50)` and returns the raw paginator.
- Resources use camelCase keys (see `App\Http\Resources\Company\MeasureResource`).
- Backend tests run on **sqlite in-memory** (`phpunit.xml`), so search filtering must avoid Postgres-only `ILIKE`; use `LOWER(column) LIKE ?` instead.
- Angular: standalone components with inline templates + signals (see `features/admin/pages/points/admin-points.component.ts`), `ApiClient` wrapper in `core/services/api-client.service.ts`, feature services pattern in `features/company/services/company-measures.service.ts`, admin routes in `app.routes.ts` under the `admin` path with `portalGuard('admin')`, sidebar nav in `shared/shells/admin-shell.component.ts`. Angular tests use TestBed specs (see `admin-points.component.spec.ts`).
- `docs/api/openapi.yaml` already has an `/admin/*` paths section (from line 1796) and an `Admin` tag; new paths/schemas go next to those.

### Step 1 — Backend: routes

File: `apps/api-laravel/routes/api.php`

Add inside the existing admin group (no new middleware, no new auth concepts):

- `GET /admin/system-exercises` → `SystemExerciseController@index`
- `POST /admin/system-exercises` → `SystemExerciseController@store`
- `GET /admin/system-exercises/{systemExercise}` → `SystemExerciseController@show` (route-model binding)
- `PATCH /admin/system-exercises/{systemExercise}` → `SystemExerciseController@update`
- `POST /admin/system-exercises/{systemExercise}/archive` → `SystemExerciseController@archive`
- `GET /admin/system-exercise-tags` → `SystemExerciseTagController@index`

No DELETE route.

### Step 2 — Backend: Form Requests

New files in `apps/api-laravel/app/Http/Requests/Admin/`:

- `CreateSystemExerciseRequest`
  - `title`: `required|string|max:255`
  - `shortDescription`, `description`, `instructions`, `safetyNotes`, `contraindications`, `defaultFeedbackPrompt`: `nullable|string`
  - `exerciseType`: `required` + `Rule::in` over the `SystemExercise::TYPE_*` constants
  - `difficulty`: `required` + `Rule::in` over `DIFFICULTY_*`
  - `status`: `sometimes` + `Rule::in` over `STATUS_*` (server default `ACTIVE` — matches migration/factory/model-hook default, i.e. the existing catalog convention)
  - `defaultDurationMinutes`, `defaultSets`, `defaultRepetitions`, `defaultHoldSeconds`: `nullable|integer|min:1|max:100000` (columns are unsigned)
  - `requiresFeedback`: `sometimes|boolean` (server default `true`)
  - `tagIds`: `sometimes|array`, `tagIds.*`: `integer|exists:system_exercise_tags,id`
  - No `slug` input accepted.
- `UpdateSystemExerciseRequest` — same rules with `sometimes` on everything (PATCH semantics); still no `slug` input.

Requests use camelCase keys (matching existing API style); the controller maps to snake_case columns.

### Step 3 — Backend: Resources

New files in `apps/api-laravel/app/Http/Resources/Admin/`:

- `SystemExerciseResource` — camelCase: `id`, `slug`, `title`, `shortDescription`, `description`, `exerciseType`, `difficulty`, `defaultDurationMinutes`, `defaultSets`, `defaultRepetitions`, `defaultHoldSeconds`, `instructions`, `safetyNotes`, `contraindications`, `defaultFeedbackPrompt`, `requiresFeedback`, `status`, `tags` (via `whenLoaded('tags')`, each item rendered with `SystemExerciseTagResource`), `createdAt`, `updatedAt`. One resource serves both list and detail (list always eager-loads `tags`, so the fields the list UI ignores are simply present — keeps the contract simple and avoids a second resource).
- `SystemExerciseTagResource` — `id`, `category`, `key`, `label`, `description`, `sortOrder`, `isActive`.

### Step 4 — Backend: controllers

New files in `apps/api-laravel/app/Http/Controllers/Admin/`:

`SystemExerciseController`:

- `index(Request)`:
  - Base query `SystemExercise::with('tags')`, default sort `orderBy('created_at', 'desc')` (matches `AdminCompanyController::index`).
  - Filters (all optional query params): `search` → `LOWER(title|short_description|description|slug) LIKE %term%` grouped in one `where(fn)`; `status`, `exerciseType`, `difficulty` → exact match, validated against model constants (ignore or 422 invalid values — prefer 422 via inline validation with `Rule::in`); `tagCategory`, `tagKey` → single `whereHas('tags', ...)` applying whichever of the two is present (combined, so `tagCategory=BODY_REGION&tagKey=neck` means one tag matching both).
  - Pagination: `paginate($perPage)` with `perPage` query param (default 25, max 100) and standard `page` param; return `SystemExerciseResource::collection($paginator)` which yields Laravel's standard `data`/`links`/`meta` shape. (Existing admin precedent is the raw paginator in `AdminPartnerController`; the resource-collection shape is equivalent but resource-driven per `AGENTS.md` — documented in OpenAPI either way. See open questions.)
- `store(CreateSystemExerciseRequest)`: build attributes (camelCase → snake_case), generate unique slug (Step 5), set `created_by_user_id = $request->user()->id`, default `status`/`requires_feedback` when absent, `create`, then `tags()->sync($tagIds ?? [])`, reload `tags`, return resource with `201`.
- `show(SystemExercise)`: `load('tags')`, return resource.
- `update(UpdateSystemExerciseRequest, SystemExercise)`: update only provided fields; never touch `slug`; `tags()->sync()` only when `tagIds` key is present in the request; return resource.
- `archive(SystemExercise)`: set `status = ARCHIVED` and save (idempotent — saving an already-archived row is a no-op state-wise); do not touch tags/templates/snapshots; return resource with `200`.

`SystemExerciseTagController`:

- `index(Request)`: filters `category` (exact, `Rule::in` over `CATEGORY_*`), `isActive` (boolean), `search` (`LOWER(key|label|description) LIKE`); sort `category asc, sort_order asc, label asc`; tags catalog is small, so return all matching rows un-paginated as `['data' => SystemExerciseTagResource::collection(...)]`.

No service classes: existing admin features have no service layer and the logic is thin (per task: "Do not over-engineer").

### Step 5 — Backend: slug generation

Private helper in `SystemExerciseController` (or small dedicated support class if it stays in one place):

- `Str::slug($title)`; if empty result, fall back to `'exercise'`.
- While `system_exercises.slug` exists (including archived rows), append `-2`, `-3`, … .
- Only used on create. On update the model hook never overwrites an existing slug, and the requests don't accept `slug`, so slugs stay stable — matching the task's v1 rule.

No model/migration changes — the data model already fits. (If implementation reveals a missing cast/index, justify it in the handoff per task rules.)

### Step 6 — OpenAPI

File: `docs/api/openapi.yaml`

- Add paths next to the existing `/admin/*` block, tag `[Admin]`, `security: [{ Sanctum: [] }]`:
  - `/admin/system-exercises` (get with `search`, `status`, `exerciseType`, `difficulty`, `tagCategory`, `tagKey`, `page`, `perPage` query params; post)
  - `/admin/system-exercises/{systemExercise}` (get, patch; integer path param)
  - `/admin/system-exercises/{systemExercise}/archive` (post)
  - `/admin/system-exercise-tags` (get with `category`, `isActive`, `search`)
- Add component schemas: `AdminSystemExercise`, `AdminSystemExerciseTag`, `AdminSystemExerciseCreatePayload`, `AdminSystemExerciseUpdatePayload`, plus reusable enum definitions (`SystemExerciseType`, `SystemExerciseDifficulty`, `SystemExerciseStatus`, tag category enum) and the paginated list response shape (`data` + `links` + `meta`).
- Document error responses: `401` unauthenticated, `403` forbidden (non-admin role), `404` not found, `422` validation error — following the existing error-format conventions in the file.
- Do **not** document tag mutation, hard delete, or any user-facing endpoints.

### Step 7 — Angular: types and service

New files:

- `apps/web-angular/src/app/features/admin/models/admin-system-exercise.models.ts` — `SystemExerciseType`, `SystemExerciseDifficulty`, `SystemExerciseStatus` (string-literal unions), `AdminSystemExerciseTag`, `AdminSystemExercise`, `ListSystemExercisesParams`, `CreateSystemExercisePayload`, `UpdateSystemExercisePayload`, plus the paginated response interface matching the OpenAPI list shape. Only fields the API returns.
- `apps/web-angular/src/app/features/admin/services/admin-system-exercise.service.ts` — `@Injectable({ providedIn: 'root' })`, injects `ApiClient` (pattern: `CompanyMeasuresService`). Methods: `listExercises(filters)` → `GET /admin/system-exercises`, `getExercise(id)`, `createExercise(payload)`, `updateExercise(id, payload)`, `archiveExercise(id)` → `POST .../archive` with `{}` body, `listTags(filters)` → `GET /admin/system-exercise-tags`. No direct `fetch`/`HttpClient` in components.

### Step 8 — Angular: route, nav, page

- `apps/web-angular/src/app/app.routes.ts`: add `{ path: 'system-exercises', loadComponent: ... AdminSystemExercisesComponent }` under the existing admin children (already guarded by `authGuard` + `portalGuard('admin')`).
- `apps/web-angular/src/app/shared/shells/admin-shell.component.ts`: add sidebar link `System-Übungen` → `/admin/system-exercises` (same markup pattern as the existing three links — straightforward and consistent, so per task it should be added).
- New component `apps/web-angular/src/app/features/admin/pages/system-exercises/admin-system-exercises.component.ts` (standalone, inline template, signals, ReactiveForms — style of `admin-points.component.ts` / `companies-create.component.ts`):
  - List table: title, slug, type, difficulty, duration, status, tag labels, updatedAt; pagination controls (prev/next from `meta`).
  - Filter bar: search input (debounced or applied on submit), selects for status / exerciseType / difficulty, tag filter via tag category + key selects (options from `listTags()`); changes re-trigger `listExercises`.
  - Create/edit form (inline panel or simple toggle section, no new portal concepts): all fields from the task's form list; enums as `<select>`; `requiresFeedback` checkbox defaulting true; tags as checkbox list grouped by category (read-only catalog, assignment only); slug shown read-only in edit mode; no slug input.
  - Archive action per row with `confirm()` guard (simple, consistent with keeping UI minimal); list keeps archived rows visible when the status filter is empty/`ARCHIVED`.
  - Error/success handling via existing `NotificationService` + inline error blocks, as in `admin-points.component.ts`.
  - Keep it to this one component unless it becomes unwieldy; if split is needed, a child `system-exercise-form.component.ts` in the same folder is the only allowed extra.

### Step 9 — Backend tests

New file `apps/api-laravel/tests/Feature/AdminSystemExerciseTest.php` (style: `RefreshDatabase`, factories, role setup copied from existing feature tests such as `SystemMeasureDataModelTest` / `CompanyTest`). Cover, with one test per behavior:

- Authorization: ELYO_ADMIN gets 200; unauthenticated 401; EMPLOYEE 403; COMPANY_ADMIN 403; COMPANY_MANAGER 403 — for the exercise list, and a spot-check on create + tag list.
- List: returns expected fields incl. tags; default sort newest first; each filter (`search` across title/shortDescription/description/slug, `status`, `exerciseType`, `difficulty`, `tagCategory`, `tagKey`) includes matching and excludes non-matching rows; pagination meta present.
- Create: 201 with resource; slug generated server-side from title; slug collision gets suffix; `created_by_user_id` set; invalid `exerciseType`/`difficulty`/`status` → 422; negative/zero numeric fields → 422; non-existent `tagIds` → 422; tags synced exactly.
- Update: editable fields updated; title change leaves slug unchanged; `tagIds` present → exact sync (removes unsent tags); `tagIds` absent → tags untouched; invalid enums → 422.
- Archive: sets `ARCHIVED`, returns resource; idempotent (second call 200, still `ARCHIVED`); row still in DB; pivot rows still present; `system_measure_template_exercises` reference still present (use `SystemMeasureTemplateFactory` + attach).
- Tag list: returns fields; `category`/`isActive`/`search` filters; sort category → sortOrder → label; confirm no `POST/PATCH/DELETE /admin/system-exercise-tags` routes exist (405/404 assertion).

### Step 10 — Angular tests

New specs (TestBed + `provideHttpClientTesting`, style of `admin-points.component.spec.ts`):

- `admin-system-exercise.service.spec.ts`: each method hits the expected URL/method/params/payload (HttpTestingController).
- `admin-system-exercises.component.spec.ts` (focused, not brittle DOM tests): loads exercises on init; changing a filter triggers reload with params; create flow calls `createExercise` with form payload incl. `tagIds`; edit flow calls `updateExercise`; archive action calls `archiveExercise`; tags rendered from `listTags` as selectable options with no create/edit affordance.

### Step 11 — Validation (non-destructive only)

- `docker compose exec api php artisan test --filter=AdminSystemExercise`
- `docker compose exec api php artisan route:list` (verify new admin routes + absence of DELETE)
- `docker compose exec web npm test -- --watch=false` scoped to new specs if supported, else relevant suite
- `docker compose exec web npm run build`
- `git diff --check`, `git status --short`

No `migrate:fresh`, no `db:wipe`, no `docker compose down -v`, no destructive git commands. No migration is planned, so no schema commands at all.

### Suggested commit slicing

1. Backend: routes + requests + resources + controllers + feature tests.
2. OpenAPI documentation.
3. Angular: types + service + specs.
4. Angular: route + nav + page component + specs.

(Or a single PR with these as ordered commits — patches stay reviewable per `AGENTS.md`.)

### Open questions

1. **ELYO_SUPPORT access**: the existing admin route group allows `ELYO_ADMIN,ELYO_SUPPORT`. The task says "platform admin only". Plan default: place routes in the existing group unchanged (follows "use existing admin portal middleware/role conventions" and "do not invent a new auth system"). Confirm if ELYO_SUPPORT must be excluded — that would need a separate, narrower route group.
2. **Pagination shape**: plan uses `SystemExerciseResource::collection($paginator)` (`data`/`links`/`meta`), which differs cosmetically from `AdminPartnerController`'s raw-paginator shape (`data`/`current_page`/…). Both exist in spirit in the project; the resource-collection shape is the `AGENTS.md`-conformant one and will be the documented OpenAPI contract. Flagging in case strict consistency with the partner endpoint is preferred.
3. **`tagCategory`+`tagKey` combination semantics**: plan treats them as constraints on a single tag (`whereHas` with both). If they should be independent constraints (any tag with that category AND any tag with that key), say so — test expectations change.

### Intentional deviations

- None from the task. Within the codebase: new Form Requests/Resources in the `Admin` namespace where existing admin controllers use inline validation/raw JSON — mandated by `AGENTS.md` and explicitly allowed by the task's implementation notes.

## Final Decisions Before Implementation

Use these decisions to resolve the open questions from the implementation plan.

### Admin role access

Use the existing admin route group convention unchanged.

System Exercise Catalog admin endpoints are accessible to:

- ELYO_ADMIN
- ELYO_SUPPORT

They must not be accessible to:

- COMPANY_OWNER
- COMPANY_ADMIN
- COMPANY_MANAGER
- EMPLOYEE
- unauthenticated users

Do not create a new auth concept or a separate narrower route group in this task.

### Pagination response shape

Use Laravel resource collection pagination shape:

- data
- links
- meta

Document this exact shape in OpenAPI.

Do not copy the older raw paginator shape from AdminPartnerController for this new API.

### Combined tag filter semantics

When both `tagCategory` and `tagKey` are provided, they must match the same tag row through one `whereHas('tags', ...)` condition.

Example:

- `tagCategory=BODY_REGION`
- `tagKey=BACK`

means the exercise must have a tag where:

- category = BODY_REGION
- key = BACK

Do not interpret this as independent "has any BODY_REGION tag and has any BACK tag" semantics.

### Slug uniqueness

The model backfills a slug when missing, but it does not guarantee uniqueness.

The create endpoint must generate a unique slug server-side before saving.

If the generated slug already exists, append a numeric suffix such as:

- back-mobility
- back-mobility-2
- back-mobility-3

Do not regenerate the slug on update.

### System tag mutation

System Exercise Tags remain read-only in this slice.

Do not add tag create/update/delete endpoints or UI affordances.
