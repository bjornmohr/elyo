# Task: Measure Domain Concept v1

Date: 2026-06-10

## Goal

Analyze and define the target domain model for ELYO measures before implementing the Measures Hub, QR verification, or persona-based recommendations.

The current implementation already supports company-created measures, employee listing, self-reported participation, points, and company participation summary. However, the model is not yet sufficient for personalized recommendations, guided remote measures, persona targeting, structured problem signals, or verified participation.

This task must produce a fachlich-technisches concept document and a low-risk implementation recommendation. It must not implement the full feature.

## Current Context

The existing measures slice appears to cover:

- Company-created measures
- Employee listing of active company/team measures
- Employee participation via self-report
- Points for measure participation
- Company participation summary with privacy threshold

Open questions remain around:

- What exactly is a measure?
- How are company measures different from individual recommendations?
- How should guided remote measures be represented?
- How should onsite/event measures be represented?
- How should later QR/admin/partner verification fit in?
- How should personas and health paths from the future anamnesis/screening logic connect?
- Which recommendation inputs should exist?
- Which fields are stable enough to implement now?
- Which logic should explicitly wait until the final questionnaire/persona concept is available?

## Scope

Analyze the current codebase and produce a concept proposal for the future measure and recommendation domain model.

Inspect at least:

- apps/api-laravel/routes/api.php
- apps/api-laravel/app/Http/Controllers/Employee/EmployeeController.php
- apps/api-laravel/app/Http/Controllers/Company
- apps/api-laravel/app/Models
- apps/api-laravel/app/Services
- apps/api-laravel/database/migrations
- apps/api-laravel/tests/Feature
- apps/web-angular/src/app/features/employee
- apps/web-angular/src/app/features/company
- docs/api/openapi.yaml
- docs/ai-context if present
- AGENTS.md if present

## Required Analysis Sections

Create or update a documentation file under:

docs/ai-context/measure-domain-concept-v1.md

The document must include:

### 1. Current State

Describe what currently exists technically and fachlich:

- Existing measure table/model
- Existing participation table/model
- Existing company APIs
- Existing employee APIs
- Existing frontend flows
- Existing points behavior
- Existing participation summary behavior
- Existing privacy/scoping constraints

### 2. Problem Statement

Explain why the current model is not enough for:

- Measures Hub
- Individual recommendations
- Persona-based targeting
- Guided remote sessions
- Structured issue-based recommendations
- QR/admin/partner verified participation
- Differentiated points logic

### 3. Target Measure Types

Define the relevant future measure types, for example:

- Company-created measure
- ELYO template measure
- Individual recommendation
- Guided remote session
- Self-reported habit/action
- Onsite/event participation
- Company challenge

Explain whether these should be represented by one table with type fields or by separate template/instance tables.

### 4. Proposed Domain Fields

Propose stable fields and enums, such as:

- origin
- delivery_type
- execution_type
- verification_mode
- scheduling fields
- location fields
- instructions
- capacity
- points behavior
- visibility/scope
- target personas
- target health paths
- target issue categories

Separate fields into:

- safe to add now
- should wait
- should not be hardcoded

### 5. Recommendation Inputs

Describe which future signals should feed recommendations:

- Anamnesis / screening
- Persona
- Health paths
- Daily check-in
- Structured issue capture
- Surveys
- Measure history
- Participation history

Clarify that the final persona/scoring logic must wait until the questionnaire/persona concept is final.

### 6. Check-in Impact

Explain whether and how the Daily Check-in must evolve.

Include a proposal for structured issues, but do not implement it.

Example fields:

- issue_category
- body_area
- intensity
- recurring/frequency
- wants_recommendation
- red_flags
- optional private note

### 7. Migration Strategy

Propose a safe implementation path:

1. Domain concept
2. Stable measure fields
3. Participation verification foundation
4. QR/admin confirmation
5. Measures Hub v0
6. Persona placeholder foundation
7. Final recommendation engine after questionnaire/persona concept is finalized

### 8. Recommended Next Implementation Slice

Recommend the next actual coding task after this concept.

The recommendation should be specific and low-risk.

Likely candidate:

Measure Domain Fields v1

Do not recommend implementing the full Measures Hub or final recommendation engine as the immediate next coding step unless the analysis strongly proves otherwise.

## Out of Scope

Do not implement:

- Database migrations
- API changes
- Angular changes
- QR code generation
- Admin confirmation
- Persona scoring
- Recommendation engine
- Questionnaire logic
- AI/video generation logic

This task is analysis and architecture documentation only.

## Constraints

- Do not propose destructive database resets.
- Do not use migrate:fresh, db:wipe, docker compose down -v, or similar destructive commands.
- Preserve existing company/user scoping assumptions.
- A user belongs to exactly one company.
- Do not introduce support for users without a company.
- Keep privacy threshold and aggregation principles intact.
- Do not expose individual participation data to company users unless explicitly justified and scoped.
- Keep future persona logic pluggable.
- Avoid hardcoding medical/persona rules before the final questionnaire concept is available.

## Validation

Run only non-destructive validation that makes sense for a documentation/concept task.

Suggested validation:

- Confirm referenced files exist.
- If no code is changed, explain why tests were not required.
- If formatting/lint tooling exists for docs, run it if safe.
- Provide a handoff summarizing:
  - files inspected
  - document created/updated
  - key findings
  - recommended next coding task
  - open questions

## Expected Output

A Codex handoff containing:

- Summary
- Files inspected
- Files changed
- Current-state findings
- Proposed target model
- Recommended next task
- Validation performed
- Risks / open questions
