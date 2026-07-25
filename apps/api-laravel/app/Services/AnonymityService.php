<?php

namespace App\Services;

/**
 * Suppression utilities for company-visible aggregates.
 *
 * ELYO-91 prompt 09: the wellbeing-sourced aggregations (metrics, trend,
 * continuity) were removed. Their source moved into the health domain, keyed on
 * health_subject_id (ADR-003 D3), and the company runtime has neither a read
 * path to it nor — per ADR-001 §2.5 — the right to aggregate it live. The
 * affected dashboard/report blocks report `App\Http\Resources\Company\ReportingPendingResource`
 * until the reporting domain delivers suppressed quarterly snapshots (ADR-003 D7).
 *
 * What remains is the threshold used by the identity-side survey and measure
 * aggregations, which are unaffected by the health domain split.
 */
class AnonymityService
{
    public const DEFAULT_THRESHOLD = 5;
}
