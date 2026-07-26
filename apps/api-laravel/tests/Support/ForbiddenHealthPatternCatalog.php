<?php

namespace Tests\Support;

/**
 * Versioned catalog for company/admin response leak detection.
 *
 * Add patterns when a new individual-health shape is introduced. Never remove
 * a pattern to admit a legitimate aggregate; add a narrow, reviewed entry to
 * HealthLeakAllowlist instead (ADR-001 §2.5).
 */
final class ForbiddenHealthPatternCatalog
{
    public const VERSION = 'v1';

    /**
     * Keys forbidden regardless of their scalar value.
     *
     * @var list<array{id: string, regex: string, rationale: string}>
     */
    public const KEY_PATTERNS = [
        [
            'id' => 'wellbeing_dimension',
            'regex' => '/^(?:average_)?(?:mood|stress|energy)(?:_score|_value)?$/',
            'rationale' => 'Mood, stress and energy are individual wellbeing dimensions.',
        ],
        [
            'id' => 'lab_marker_reference',
            'regex' => '/^(?:lab_)?marker(?:_key|_id)?$/',
            'rationale' => 'Lab marker identifiers reveal that a response contains lab-domain data.',
        ],
        [
            'id' => 'measurement_timestamp',
            'regex' => '/^measured_at$/',
            'rationale' => 'Measurement timestamps are part of individual lab and wearable readings.',
        ],
        [
            'id' => 'health_subject_reference',
            'regex' => '/^(?:health_)?subject(?:_id|_ref)?$/',
            'rationale' => 'Health subject identifiers must never leave the employee/privacy boundary.',
        ],
        [
            'id' => 'raw_health_text',
            'regex' => '/^(?:raw_answer|answer_text|free_text|text_answer|text_value|health_note)$/',
            'rationale' => 'Raw free text can contain identifying or sensitive health information.',
        ],
        [
            'id' => 'individual_health_record',
            'regex' => '/^(?:wellbeing_(?:entry|entries)|anamnesis|health_documents?|wearable_connections?|wearable_syncs?)$/',
            'rationale' => 'Individual health records are not company/admin response resources.',
        ],
    ];

    /**
     * A key is forbidden only when its endpoint/path/sibling context is health-related.
     *
     * @var list<array{id: string, key_regex: string, context_regex: string, rationale: string}>
     */
    public const CONTEXTUAL_KEY_PATTERNS = [
        [
            'id' => 'score_in_health_context',
            'key_regex' => '/^(?:(?:.+_)?score(?:_.+)?|value)$/',
            'context_regex' => '/(?:health|wellbeing|checkin|mood|stress|energy|lab|marker|measurement|biometric|anamnesis|wearable|company\/(?:dashboard|reports?(?:\b|\/)|surveys\/[^\/\s]+\/results))/',
            'rationale' => 'Score variants and generic value fields are health data inside health-related or company-reporting contexts.',
        ],
        [
            'id' => 'raw_text_in_answer_context',
            'key_regex' => '/^text$/',
            'context_regex' => '/\banswers?\b/',
            'rationale' => 'A generic text field inside an answer collection is raw survey content.',
        ],
        [
            'id' => 'raw_note_in_health_context',
            'key_regex' => '/^note$/',
            'context_regex' => '/(?:health|wellbeing|checkin)/',
            'rationale' => 'A note in health, wellbeing or check-in context is forbidden raw health text.',
        ],
        [
            'id' => 'lab_metadata_in_lab_context',
            'key_regex' => '/^(?:name|status|unit|low|high|group|source)$/',
            'context_regex' => '/(?:lab|marker|measurement|biometric)/',
            'rationale' => 'Lab names, statuses, units, bounds, groups and sources reveal lab-domain data in a lab-related context.',
        ],
    ];

    /**
     * Object shapes forbidden even when their individual keys are generic.
     *
     * @var list<array{id: string, required_keys: list<string>, rationale: string}>
     */
    public const COMPOUND_PATTERNS = [
        [
            'id' => 'lab_value_unit_pair',
            'required_keys' => ['value', 'unit'],
            'rationale' => 'A value/unit pair is the durable shape of a lab measurement.',
        ],
        [
            'id' => 'measurement_value_timestamp_pair',
            'required_keys' => ['value', 'measured_at'],
            'rationale' => 'A value plus measurement time identifies an individual reading.',
        ],
    ];

    /**
     * String values are ULID-shaped identifiers only when their response
     * location is explicitly health-related; ordinary domain ULIDs stay valid.
     */
    public const HEALTH_CONTEXT_ULID = [
        'id' => 'health_context_ulid',
        'context_regex' => '/(?:health|wellbeing|checkin|lab|marker|measurement|biometric|anamnesis|wearable|subject|\/api\/company(?:\/|\s))/',
        'rationale' => 'ULIDs in health-related locations or company responses may expose a health subject or health record.',
    ];

    /**
     * Seeded subject ids are forbidden at every path, including generic `id`.
     */
    public const KNOWN_HEALTH_SUBJECT = [
        'id' => 'known_health_subject',
        'rationale' => 'A known synthetic health subject proves direct subject-reference leakage.',
    ];
}
