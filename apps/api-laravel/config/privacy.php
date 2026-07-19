<?php

return [
    'mapping' => [
        'encryption_key' => env('MAPPING_ENCRYPTION_KEY'),
        'hmac_key' => env('MAPPING_HMAC_KEY'),
        'subject_derivation_key' => env('MAPPING_SUBJECT_DERIVATION_KEY'),
    ],
];
