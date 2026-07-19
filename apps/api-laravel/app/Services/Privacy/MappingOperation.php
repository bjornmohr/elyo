<?php

namespace App\Services\Privacy;

enum MappingOperation: string
{
    case PROVISION_OWN_SUBJECT = 'provisionOwnSubject';
    case RESOLVE_OWN_SUBJECT = 'resolveOwnSubject';
    case REVOKE_SUBJECT_LINK = 'revokeSubjectLink';
    case RESOLVE_REPORTING_COHORT = 'resolveReportingCohort';
    case RESOLVE_FOR_DATA_SUBJECT_REQUEST = 'resolveForDataSubjectRequest';
}
