# Prisma to Eloquent Mapping

This document maps the legacy Prisma models from the ELYO Next.js codebase to Laravel Eloquent models and migrations for the new architecture.

## Global Design Decisions

- **Primary Keys**: All models will use `string` IDs mapping to `uuid` or `cuid` (as per legacy). In Laravel, these will be defined as `$keyType = 'string'` and `$incrementing = false`.
- **Enums**: PostgreSQL Native Enums will be used in migrations. Laravel models will use [Casts](https://laravel.com/docs/11.x/eloquent-mutators#enum-casting) to PHP Enums (located in `app/Enums`).
- **Timestamps**: Standard Laravel `created_at` and `updated_at` (aliased where Prisma used `createdAt`/`updatedAt`).
- **Soft Deletes**: Not explicitly present in legacy, but recommended for `User`, `Company`, and `Partner`.
- **Foreign Keys**: Cascading rules will be preserved.

---

## Model Mapping

| Prisma Model | Laravel Model | Table Name | Notes |
| :--- | :--- | :--- | :--- |
| `Company` | `Company` | `companies` | Tenant model. |
| `User` | `User` | `users` | Core user entity. |
| `Team` | `Team` | `teams` | |
| `WellbeingEntry` | `WellbeingEntry` | `wellbeing_entries` | Check-in data. |
| `Survey` | `Survey` | `surveys` | |
| `SurveyQuestion` | `SurveyQuestion` | `survey_questions` | |
| `SurveyResponse` | `SurveyResponse` | `survey_responses` | |
| `SurveyAnswer` | `SurveyAnswer` | `survey_answers` | |
| `AnamnesisProfile`| `AnamnesisProfile`| `anamnesis_profiles` | Sensitive health data. |
| `HealthDocument` | `HealthDocument` | `health_documents` | Metadata for health files. |
| `UserPoints` | `UserPoints` | `user_points` | Current balance/streak. |
| `PointTransaction`| `PointTransaction`| `point_transactions`| Points history. |
| `InviteToken` | `InviteToken` | `invite_tokens` | |
| `WearableConnection`| `WearableConnection`| `wearable_connections`| OAuth tokens (encrypted). |
| `WearableSync` | `WearableSync` | `wearable_syncs` | Aggregated health metrics. |
| `UserDocument` | `UserDocument` | `user_documents` | General documents. |
| `Measure` | `Measure` | `measures` | Suggestion engine output. |
| `PushSubscription`| `PushSubscription`| `push_subscriptions`| WebPush endpoints. |
| `NotificationPreference` | `NotificationPreference` | `notification_preferences` | |
| `Partner` | `Partner` | `partners` | Marketplace providers. |

---

## Detailed Model Definitions

### User
- **Table**: `users`
- **Fields**: `id`, `email`, `name`, `avatar_url`, `role`, `password_hash`, `is_active`, `last_login_at`, `company_id`, `team_id`.
- **Casts**: 
    - `role` => `App\Enums\Role`
    - `is_active` => `boolean`
    - `last_login_at` => `datetime`
- **Relationships**:
    - `company` => `BelongsTo`
    - `team` => `BelongsTo`
    - `managedTeams` => `HasMany` (via `manager_id`)
- **NextAuth Migration**: The `Account` and `Session` models from NextAuth will be dropped. Laravel Sanctum will handle API tokens, and session management will be handled by Laravel's native drivers.

### AnamnesisProfile (Sensitive)
- **Table**: `anamnesis_profiles`
- **Fields**: `id`, `user_id`, `completion_pct`, `birth_year`, `biological_sex`, `activity_level`, `sleep_quality`, `stress_tendency`, `smoking_status`, `nutrition_type`, `chronic_patterns` (JSON/Array), `has_medication`.
- **PostgreSQL Specific**: `chronic_patterns` should be `jsonb` or `text[]`.
- **Sensitive Fields**: All fields except `completion_pct` are PII/Health data. Encryption at rest for specific columns recommended.

### WearableConnection (Encrypted)
- **Table**: `wearable_connections`
- **Fields**: `access_token`, `refresh_token`.
- **Implementation**: Use Laravel's `Encrypted` cast.

---

## Enum Definitions

The following enums should be created as PHP Enums in `app/Enums`:

1. **Role**: `COMPANY_ADMIN`, `COMPANY_MANAGER`, `EMPLOYEE`, `ELYO_ADMIN`
2. **SurveyStatus**: `DRAFT`, `ACTIVE`, `CLOSED`
3. **QuestionType**: `SCALE`, `MULTIPLE_CHOICE`, `TEXT`, `YES_NO`
4. **CheckinFrequency**: `DAILY`, `WEEKLY`
5. **PartnerVerificationStatus**: `PENDING_DOCS`, `PENDING_REVIEW`, `VERIFIED`, `SUSPENDED`, `REJECTED`

---

## Migration Order

To respect foreign key constraints:

1. `companies`
2. `users` (references `companies`)
3. `teams` (references `companies`, `users` as manager)
4. Update `users` table to add `team_id` FK.
5. `wellbeing_entries`
6. `surveys`
7. `survey_questions`
8. `survey_responses`
9. `survey_answers`
10. `anamnesis_profiles`
11. `health_documents`
12. `user_points`
13. `point_transactions`
14. `invite_tokens`
15. `wearable_connections`
16. `wearable_syncs`
17. `user_documents`
18. `measures`
19. `push_subscriptions`
20. `notification_preferences`
21. `partners`

---

## Sensitive Data Strategy

1. **Health Data**: `AnamnesisProfile` fields and `WellbeingEntry` notes.
    - *Proposed*: Encrypt `note` in `wellbeing_entries` and health-specific columns in `anamnesis_profiles`.
2. **Tokens**: `accessToken` and `refreshToken` in `wearable_connections`.
    - *Proposed*: Use `$casts = ['access_token' => 'encrypted', 'refresh_token' => 'encrypted']`.
3. **Files**: `HealthDocument` and `UserDocument` stored in Vercel Blob (or S3 equivalent).
    - *Proposed*: Ensure URLs are signed and not public.

---

## Open Decisions

1. **ID Format**: Should we stick to `cuid` or move to standard `uuid` (v4/v7)? Legacy uses `cuid`.
2. **Soft Deletes**: Should we enable them globally?
3. **Prisma Arrays**: `chronic_patterns` in `AnamnesisProfile` and `categories` in `Partner` are `String[]` in Prisma. Laravel works best with `jsonb` or a separate pivot table.
4. **Auth**: Confirm that `Account` and `Session` (NextAuth) are definitely not needed for OAuth in Laravel (Laravel Socialite usually handles this differently).
