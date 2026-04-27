# ELYO Current System Map

## UI Pages Grouped by Role

### Auth
- `src/app/page.tsx`: Landing page / Entry point
- `src/app/auth/login/page.tsx`: Main login page
- `src/app/auth/register/page.tsx`: Main registration page
- `src/app/auth/forgot-password/page.tsx`: Password recovery
- `src/app/auth/reset-password/page.tsx`: Password reset
- `src/app/auth/invite/[token]/page.tsx`: Invite acceptance page
- `src/app/(onboarding)/onboarding/page.tsx`: Onboarding flow
- `src/app/(onboarding)/onboarding/company/page.tsx`: Company-specific onboarding

### Employee
- `src/app/(employee)/employee/dashboard/page.tsx`: Employee main dashboard
- `src/app/(employee)/employee/checkin/page.tsx`: Mental wellbeing check-in
- `src/app/(employee)/employee/history/page.tsx`: Historical check-in data
- `src/app/(employee)/employee/profile/page.tsx`: User profile settings
- `src/app/(employee)/employee/profile/anamnesis/page.tsx`: Health anamnesis questionnaire
- `src/app/(employee)/employee/settings/page.tsx`: Notification and app settings
- `src/app/(employee)/employee/surveys/page.tsx`: List of available surveys
- `src/app/(app)/checkin/page.tsx`: Duplicate/Alternative check-in entry? (legacy/unclear)
- `src/app/(app)/dashboard/page.tsx`: Legacy/Alternative dashboard? (legacy/unclear)
- `src/app/(app)/profile/page.tsx`: Legacy/Alternative profile? (legacy/unclear)

### Company (Admin/Manager)
- `src/app/(company)/company/dashboard/page.tsx`: Company analytics dashboard
- `src/app/(company)/company/measures/page.tsx`: Management of health measures
- `src/app/(company)/company/reports/page.tsx`: ESG and health reporting
- `src/app/(company)/company/settings/page.tsx`: Company tenant settings
- `src/app/(company)/company/surveys/page.tsx`: Survey creation and management
- `src/app/(company)/company/teams/page.tsx`: Team and member management

### Partner
- `src/app/partner/login/page.tsx`: Partner portal login
- `src/app/partner/register/page.tsx`: Partner registration/application
- `src/app/partner/dashboard/page.tsx`: Partner management dashboard
- `src/app/partner/documents/page.tsx`: Partner document upload (verification)

### Admin (ELYO Central)
- `src/app/admin/partners/page.tsx`: Central partner management
- `src/app/admin/partners/[id]/page.tsx`: Partner detail view and verification

### Legacy / Unclear
- `src/app/(app)/partners/page.tsx`: Public partner directory?
- `src/app/(app)/level/page.tsx`: Gamification/Level details
- `src/app/(app)/partner/page.tsx`: Singular partner view?

---

## API Routes Grouped by Domain

### Auth
- `src/app/api/auth/[...nextauth]/route.ts`:
  - Methods: `GET`, `POST`
  - Auth: Public (handlers)
  - Models: `User`, `Account`, `Session`, `VerificationToken`, `Company`, `Team`
  - Lib: `next-auth`, `bcryptjs`
- `src/app/api/auth/register/route.ts`:
  - Methods: `POST`
  - Auth: Public
  - Models: `User`
  - Lib: `prisma`, `RegisterSchema`, `ratelimit`
- `src/app/api/auth/invite/accept/route.ts`:
  - Methods: `POST`
  - Auth: Public (via token)
  - Models: `User`, `InviteToken`
  - Lib: `prisma`, `verifyInviteToken`, `ratelimit`
- `src/app/api/auth/invite/verify/route.ts`:
  - Methods: `GET`
  - Auth: Public (via token)
  - Models: `InviteToken`
  - Lib: `verifyInviteToken`, `ratelimit`
- `src/app/api/auth/mobile-login/route.ts`:
  - Methods: `POST`
  - Auth: Public (External/App bridge)
  - Models: N/A (Session proxy)
  - Lib: `fetch`

### Employee
- `src/app/api/employee/dashboard/route.ts`:
  - Methods: `GET`
  - Auth: `EMPLOYEE`
  - Models: `WellbeingEntry`, `UserPoints`, `Survey`
  - Lib: `auth`
- `src/app/api/employee/checkin/route.ts`:
  - Methods: `POST`
  - Auth: `EMPLOYEE`
  - Models: `WellbeingEntry`, `UserPoints`
  - Lib: `auth`, `points`
- `src/app/api/employee/profile/route.ts`:
  - Methods: `PUT`
  - Auth: `EMPLOYEE`
  - Models: `User`
  - Lib: `auth`
- `src/app/api/employee/history/route.ts`:
  - Methods: `GET`
  - Auth: `EMPLOYEE`
  - Models: `WellbeingEntry`
  - Lib: `auth`
- `src/app/api/employee/surveys/route.ts`:
  - Methods: `GET`
  - Auth: `EMPLOYEE`
  - Models: `Survey`
  - Lib: `auth`
- `src/app/api/employee/surveys/[surveyId]/respond/route.ts`:
  - Methods: `POST`
  - Auth: `EMPLOYEE`
  - Models: `SurveyResponse`, `SurveyAnswer`
  - Lib: `auth`

### Company
- `src/app/api/company/dashboard/route.ts`:
  - Methods: `GET`
  - Auth: `COMPANY_ADMIN` / `COMPANY_MANAGER`
  - Models: `Company`, `WellbeingEntry`, `Team`
  - Lib: `auth`, `anonymize`
- `src/app/api/company/analytics/heatmap/route.ts`:
  - Methods: `GET`
  - Auth: `COMPANY_ADMIN`
  - Models: `WellbeingEntry`
  - Lib: `auth`, `anonymize`
- `src/app/api/company/reports/route.ts`:
  - Methods: `GET`
  - Auth: `COMPANY_ADMIN`
  - Models: `WellbeingEntry`, `Measure`
  - Lib: `auth`, `esgReport`
- `src/app/api/company/surveys/route.ts`:
  - Methods: `GET`, `POST`
  - Auth: `COMPANY_ADMIN`
  - Models: `Survey`, `SurveyQuestion`
  - Lib: `auth`
- `src/app/api/company/teams/route.ts`:
  - Methods: `GET`, `POST`
  - Auth: `COMPANY_ADMIN`
  - Models: `Team`
  - Lib: `auth`

### Partner
- `src/app/api/partner/login/route.ts`:
  - Methods: `POST`
  - Auth: Public
  - Models: `Partner`
  - Lib: `bcryptjs`, `signPartnerSession`
- `src/app/api/partner/me/route.ts`:
  - Methods: `GET`
  - Auth: Partner (Cookie)
  - Models: `Partner`
  - Lib: `verifyPartnerSession`
- `src/app/api/partner/documents/route.ts`:
  - Methods: `POST`
  - Auth: Partner (Cookie)
  - Models: `Partner`
  - Lib: `put` (@vercel/blob)

### Health & Wearables
- `src/app/api/wearables/google/connect/route.ts`:
  - Methods: `GET`
  - Auth: `EMPLOYEE`
  - Models: `User`
  - Lib: `googleHealth`
- `src/app/api/wearables/terra/connect/route.ts`:
  - Methods: `GET`
  - Auth: `EMPLOYEE`
  - Models: `WearableConnection`
  - Lib: `terra/client`
- `src/app/api/webhooks/terra/route.ts`:
  - Methods: `POST`
  - Auth: Public (Signature verification)
  - Models: `WearableConnection`, `WearableSync`
  - Lib: `terra/webhook`

### System / Misc
- `src/app/api/cron/route.ts`:
  - Methods: `POST`
  - Auth: Secret Header (`CRON_SECRET`)
  - Models: `User`, `Company`, `WellbeingEntry`, `WearableConnection`, `UserPoints`
  - Lib: `email`, `anonymize`, `googleHealth`, `measureEngine`, `points`
- `src/app/api/push/subscribe/route.ts`:
  - Methods: `POST`, `DELETE`
  - Auth: `EMPLOYEE`
  - Models: `PushSubscription`
  - Lib: `auth`

---

## Prisma Models & Business Meaning

- `Company`: Represents a corporate client (tenant). Stores settings like anonymity thresholds and branding.
- `User`: Central entity representing employees, company admins, and elyo admins. Linked to a company and optionally a team.
- `Team`: Sub-units within a company for granular reporting and management.
- `WellbeingEntry`: The core data point. Daily/weekly mood, stress, and energy scores submitted by employees.
- `Survey`, `SurveyQuestion`, `SurveyResponse`, `SurveyAnswer`: Custom questionnaire system for companies to poll employees.
- `AnamnesisProfile`: Health background data for employees, collected in waves.
- `HealthDocument`: Metadata for uploaded health-related files (legacy/limited use).
- `UserPoints`, `PointTransaction`: Gamification system tracking employee engagement and awarding levels.
- `InviteToken`: Manages the onboarding of new users into companies.
- `WearableConnection`: Stores credentials/IDs for external health data sources (Google Health, Terra).
- `WearableSync`: Aggregated metrics synced from wearables (steps, heart rate, sleep).
- `UserDocument`: Generic file storage for users (via Vercel Blob).
- `Measure`: Recommended or active health interventions suggested to companies based on wellbeing data.
- `PushSubscription`: VAPID credentials for web push notifications.
- `NotificationPreference`: Opt-in/out settings for different notification types.
- `Partner`: External health/wellness providers.
- `Account`, `Session`, `VerificationToken`: Standard NextAuth models for authentication.

---

## External Integrations

- **SMTP (Nodemailer)**: Used for sending invite emails, check-in reminders, and weekly digests to admins.
- **Vercel Blob**: Used for storing user documents and partner verification documents.
- **Web Push**: Used for browser notifications (reminders, updates).
- **Google Health (Google Fit API)**: Integration via OAuth2 to sync steps, sleep, and heart rate.
- **Terra**: Multi-wearable aggregator (Oura, Garmin, etc.) used via their widget and webhooks.
- **Cron**: Automated tasks for reminders, weekly digests, wearable sync, and the "Measure Engine". Secured via `CRON_SECRET`.

---

## Duplicate or Legacy Route Structures

- **Check-in/Dashboard**: There is a split between `src/app/(employee)/employee/...` and `src/app/(app)/...`. It appears `(employee)` is the newer, role-scoped structure, while `(app)` contains older or redundant routes like `(app)/dashboard` and `(app)/checkin`.
- **Partners**: `src/app/api/partners` (plural) seems to be a public/general list, while `src/app/api/partner` (singular) is the partner portal backend.
- **Admin**: `src/app/admin` exists outside of the `(company)` or `(employee)` groups, correctly separating central ELYO administration from tenant-level administration.
- **Anamnesis**: `src/app/api/anamnesis` exists as a top-level API domain while most employee actions are under `src/app/api/employee`. This might be due to its specific "Wave" based implementation.
- **Measures**: `src/app/api/measures` is top-level, while it's primarily used by company admins.

---

## Unknowns
- `src/app/api/auth/mobile-login/route.ts`: Uses `INTERNAL_BASE`. It's unclear what this environment variable points to in production, but it seems to proxy session requests from a mobile app.
- `src/lib/anonymize.ts`: Implementation details of the "Anonymity Threshold" (e.g., how it handles small teams) should be reviewed for compliance.
- `Google Health`: It's unclear if the Google Cloud project is configured for restricted health scopes or if it uses the standard Fitness API.
