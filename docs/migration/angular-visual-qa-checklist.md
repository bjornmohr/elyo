# Angular Visual QA Checklist

## Design Tokens (from React prototype)

- [x] Primary color: teal (#14b8a6 / #0d9488 gradient)
- [x] Background: hsl(40, 20%, 97%) warm off-white
- [x] Card surfaces: white with subtle borders
- [x] Font display: Fraunces (Georgia serif fallback)
- [x] Font body: DM Sans (system-ui fallback)
- [x] Border radius: 0.75rem (rounded-xl)
- [x] Input style: stone-50 bg, gray-200 border, teal-500 focus
- [x] Button gradient: linear-gradient(135deg, #14b8a6, #0d9488)
- [x] Error state: red-50 bg, red-600 text, red-200 border
- [x] Success state: green-50 bg, green-700 text, green-200 border

## Auth Pages

- [x] Login: split-panel layout matches React prototype
- [x] Login: teal gradient sidebar with Elyo branding
- [x] Login: feature pills on sidebar
- [x] Login: form fields match React styling
- [x] Login: loading spinner on submit
- [x] Login: error message styling
- [x] Login: portal-specific error for forbidden access
- [x] Register: invitation-only message (no public registration)
- [x] Invite: loading state while verifying
- [x] Invite: invalid/expired error state
- [x] Invite: form with email (disabled), name, password, confirm
- [x] Invite: company name display

## Portal Shells

- [x] Admin shell: sidebar with Elyo Admin branding
- [x] Company shell: sidebar with company navigation
- [x] Employee shell: sidebar with employee navigation
- [x] All shells: warm off-white background
- [x] All shells: white sidebar with border
- [x] All shells: teal active state on nav items
- [x] All shells: logout button in footer

## Admin Portal

- [x] Companies list: table with name, slug, status, user count
- [x] Companies list: create button with teal gradient
- [x] Create company: form with name, slug, admin email
- [x] Create company: invite token display after creation
- [x] Users page: placeholder state

## Company Portal

- [x] Dashboard: placeholder/empty state
- [x] Users: table with name, email, roles, status
- [x] Invitations: inline invite form with role selector
- [x] Invitations: table with email, role, status, expiry
- [x] Invitations: revoke action for pending invites
- [x] Teams/Surveys/Measures/Reports: placeholder states

## Employee Portal

- [x] Dashboard: existing implementation preserved
- [x] Check-in/History/Profile/Surveys: existing implementations preserved

## Remaining Visual Gaps

- [ ] Angular Material not yet installed — using Tailwind CSS only (matches React prototype better)
- [ ] Company dashboard charts not implemented (React has TrendChartClient)
- [ ] Employee dashboard data visualization not fully matched
- [ ] Mobile responsive sidebar (hamburger menu) not implemented
- [ ] Dark mode not implemented (not in React prototype either)
- [ ] Fraunces/DM Sans fonts need to be loaded via Google Fonts or local files
