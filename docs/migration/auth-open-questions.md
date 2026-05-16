# Auth Open Questions

- **Open Registration vs. Invite-only**: The legacy system had a `RegisterPage` for companies. We have implemented this in the new system as `POST /api/auth/register`, which creates a new `Company` and a `COMPANY_ADMIN` user. Is this the intended flow, or should registration be more restricted?
- **Invite Verification Response**: The `/api/auth/invite/verify/{token}` endpoint returns `companyName`, `email`, and `role`. We've used this to show context in the Angular Invitation Acceptance page. Ensure this doesn't expose sensitive info.
- **Password Policies**: Currently using Laravel default password rules. We might need to align them with specific business requirements.
- **Login Error Messaging**: As requested, login errors are neutral and do not reveal if an email exists.
- **Company Manager Dashboard**: Currently, `COMPANY_MANAGER` sees the same dashboard as `COMPANY_ADMIN` but filtered to their managed team. If a manager doesn't have a team, they get an error.
