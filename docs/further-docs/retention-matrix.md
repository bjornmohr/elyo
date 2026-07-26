# ELYO retention matrix

Status: technical proposal for legal review (ELYO-108)

This matrix is a technical implementation aid, not legal advice. Periods marked
`PROPOSED` must be approved by the privacy/legal owner before scheduled
enforcement is enabled. The 30-day account-deletion grace period and 90-day
backup rotation come from ADR-001 §2.8/§2.9. The grace workflow UI is outside
ELYO-108; `AccountDeletionService::deleteUser` is the immediate execution step
to call after that grace period.

## Matrix

| Category | Domain / storage | Legal-basis pointer | Period | Deletion trigger | Mechanism | Status |
|---|---|---|---|---|---|---|
| Wellbeing entries | Health: `wellbeing_entries` | ADR-001 §2.6/§2.8; DSFA §5 and §9 question 6 | 24 months from `created_at`; account deletion within 30 days | Age cutoff or approved account deletion | Retention command deletes expired rows; account deletion removes the Health Subject and cascades | PROPOSED |
| Lab readings | Health: `lab_marker_readings` | ADR-001 §2.6/§2.8; DSFA §5 and §9 question 6 | 24 months from `measured_at`; account deletion within 30 days | Age cutoff or approved account deletion | Retention command deletes expired readings; account deletion cascades from Health Subject | PROPOSED |
| Lab catalog (non-personal) | Health: `lab_markers` | ADR-001 §2.6; DSFA §7 R10 | No personal-data expiry; retain while catalog version is supported | Catalog governance decision, not user deletion | No subject-scoped deletion and no retention-command deletion | DECIDED |
| Anamnesis profiles | Health: `anamnesis_profiles` | ADR-001 §2.6/§2.8; DSFA §9 question 7 | 24 months from `updated_at`; account deletion within 30 days | Age cutoff or approved account deletion | Retention command deletes expired profiles; account deletion cascades from Health Subject | PROPOSED |
| Health-document catalog metadata | Health: `health_documents` | ADR-001 §2.8/§2.9; DSFA §5 and §9 question 7 | 24 months from `uploaded_at`; account deletion within 30 days | Age cutoff or approved account deletion | Retention command deletes metadata; account deletion cascades from Health Subject | PROPOSED |
| Uploaded health-document metadata and files | Health: `user_documents`; configured document storage disk | ADR-001 §2.8/§2.9; DSFA §5 and §9 question 7 | 24 months from `uploaded_at`; account deletion within 30 days | Age cutoff or approved account deletion | Retention command deletes each file by pseudonymous `blob_key` immediately before its own metadata row, in bounded chunks; account deletion uses the same file-first order | PROPOSED |
| Wearable connections | Health: `wearable_connections` | ADR-001 §2.6/§2.8; DSFA §9 question 6 | 90 days after an inactive connection's `updated_at`; account deletion within 30 days | Connection inactive past cutoff or approved account deletion | Retention command deletes expired inactive connections; account deletion cascades from Health Subject | PROPOSED |
| Wearable syncs | Health: `wearable_syncs` | ADR-001 §2.6/§2.8; DSFA §9 question 6 | 24 months from measurement `date`; account deletion within 30 days | Age cutoff or approved account deletion | Retention command deletes expired sync rows; account deletion cascades from Health Subject | PROPOSED |
| Audit events | Audit: `audit_events` | ADR-001 §2.7 | 2 years from `occurred_at` | Age cutoff | Retention command deletes through the maintenance connection; runtime roles remain append-only | DECIDED |
| Subject mappings (tombstone) | Mapping: `subject_mappings` | ADR-001 §2.3/§2.8 | No expiry defined; retain `REVOKED` tombstone as deletion proof | Successful completion of health and identity deletion | `revokeSubjectLink` changes `ACTIVE` to final `REVOKED` last; no restore or re-provision path | DECIDED |
| Point transactions | Identity: `point_transactions` | ADR-001 §2.6; ADR-003 D8 / ELYO-91 execution plan D8 | 24 months from `created_at`; account deletion within 30 days | Age cutoff or approved account deletion | Retention command deletes expired rows; identity user deletion cascades | PROPOSED |
| Points and streaks | Identity: `user_points` | ADR-001 §2.6; ADR-003 D8 / ELYO-91 execution plan D8 | 24 months from `updated_at`; account deletion within 30 days | Age cutoff or approved account deletion | Retention command deletes expired rows; identity user deletion cascades | PROPOSED |

Points and streaks remain identity-keyed behavioral data only because ADR-003 D8
deferred their health-domain move. Their proposed retention must be reviewed
again when that move is designed.

## Account-deletion order and resumability

1. An external workflow observes the ADR-001 30-day grace period, then calls
   `AccountDeletionService::deleteUser`.
2. `MappingService::revokeSubjectLink` resolves the active Health Subject under
   mandatory purpose `REVOCATION`. No caller reads the mapping table directly.
3. Uploaded health-document files are deleted first. The Health Subject is then
   physically deleted, cascading to every subject-keyed Health table. Every such
   table is re-counted afterwards and a surviving row aborts the deletion, so the
   audited row count is proven rather than assumed.
4. The Identity user is physically deleted. Existing foreign-key semantics
   cascade owned personal rows and null references where the application
   already preserves non-owned records. Non-FK identity artifacts (sessions,
   notifications, personal access tokens, and invite records for the same
   email) are explicitly deleted first.
5. Mapping stages the change from `ACTIVE` to `REVOKED` and emits its Mapping
   audit event. The account-deletion summary is then written synchronously with
   counts only, while the Mapping transaction remains open.
6. Only after both audit writes succeed does the Mapping transaction commit.
   The final `REVOKED` tombstone is therefore the last visible domain mutation
   and has no resurrection path. An audit failure rolls the staged Mapping
   change back to `ACTIVE` for a retry. Neither event contains raw user IDs,
   Health Subject IDs, file names, or health values.

   The audit connection cannot enlist in the Mapping transaction, so a failure of
   the commit itself leaves a `success` event reporting `mapping_revoked: 1`
   against a mapping that is still `ACTIVE`; the retry then writes a second
   `success` event. This over-claiming is deliberate. Auditing after the commit
   would invert the failure mode into a revoked mapping with no audit proof,
   which ADR-001 §2.7 treats as the worse outcome. Because the summary carries
   counts only and never IDs, the two events cannot be correlated back to a user
   by design; a retry is recognisable only by its zero deletion counts.

Physical deletes and file deletion are idempotent. A failure before mapping
revocation leaves the mapping `ACTIVE`, so the same user ID can resume the
operation. Already deleted rows/files produce zero counts on retry. Once the
mapping is `REVOKED`, another call cannot recreate a Health Subject: it is a
count-only no-op when the Identity user is gone, and it fails with a `FAILED`
audit event when the user survives, because a tombstone without the physical
deletes must never be reported as a completed deletion.

## Retention command and scheduling

`php artisan elyo:enforce-retention` is always a dry-run unless `--execute` is
present. Output contains category names, policy periods, policy status, and
counts only; it never prints row IDs, user IDs, Health Subject IDs, file names,
or health values.

An interrupted `--execute` run is resumable: files are unlinked one document at
a time, immediately before that document's metadata row, so surviving rows are
still eligible for the next run and no metadata is left pointing at a deleted
blob. Expired documents are processed in bounded chunks, because the first run
after a legal decision can cover the entire upload history.

`--execute` applies the matrix cutoffs, including periods still marked
`PROPOSED`. It is therefore an explicit maintenance action only, not approval
of those periods. Scheduler registration remains commented until legal review
marks every enforced period `DECIDED` and a dedicated maintenance runtime/role
is available.

Backup deletion is not performed by this command. Per ADR-001 §2.8/§2.9,
encrypted domain backups expire through the separate 90-day rotation and
restores must consult the deletion list.

## Legal-review decisions still required

- Confirm or replace the proposed 24-month period for wellbeing, lab readings,
  anamnesis, uploaded documents, wearable syncs, and identity behavior data.
- Confirm the proposed 90-day post-disconnection period for wearable
  credentials.
- Decide whether points/streaks and their history need different periods when
  moved to the Health domain.
- Confirm whether a finite retention period is required for revoked mapping
  tombstones, while preserving non-reidentifying deletion proof.
- Approve the production maintenance runtime/role before enabling the schedule;
  the current command uses the existing migrator connection for two-year audit
  enforcement because runtime audit credentials are intentionally INSERT-only.
