<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class CheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Canonical 1–5 scale per ELYO-102 §3.1 (B3).
     *
     * `note` is rejected instead of silently dropped (ELYO-102 §3.3 / B4): the
     * field was removed for data-minimisation reasons (DSFA R5/Z7), and a silent
     * drop would let an outdated client discard text the employee believed was
     * stored. The Angular check-in is adjusted in prompt 10 of the same release
     * train.
     *
     * `location` and `sleep` deliberately stay out of the contract here — they
     * belong to ELYO-133.
     */
    public function rules(): array
    {
        return [
            'mood' => ['required', 'integer', 'min:1', 'max:5'],
            'stress' => ['required', 'integer', 'min:1', 'max:5'],
            'energy' => ['required', 'integer', 'min:1', 'max:5'],
            'note' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.prohibited' => 'Freitext-Notizen werden im Check-in nicht mehr erfasst.',
        ];
    }
}
