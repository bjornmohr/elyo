<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\Concerns\FailsWithCodedValidationError;
use Illuminate\Foundation\Http\FormRequest;

class StoreLabMarkerReadingRequest extends FormRequest
{
    use FailsWithCodedValidationError;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Manual self-entry per ELYO-102 §1.4.
     *
     * Validation is deliberately generic: values must fit the non-negative
     * decimal(12,4) storage shape, while marker-specific plausibility ranges are
     * ELYO-114. Catalog membership plus the active flag are checked in the
     * health domain, not here.
     *
     * `source` is rejected instead of silently overwritten. The MVP has exactly
     * one provenance (`manual`) and the server sets it; a client that believes it
     * imported a document must not be told the entry was stored as such.
     */
    public function rules(): array
    {
        return [
            'markerKey' => ['required', 'string', 'max:64'],
            'value' => ['required', 'numeric', 'gte:0', 'decimal:0,4', 'max:99999999.9999'],
            'measuredAt' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'source' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return [
            'source.missing' => 'Die Herkunft eines Messwerts wird serverseitig gesetzt.',
        ];
    }
}
