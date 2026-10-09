<?php

namespace App\Http\Requests\Deaths;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for recording a pig death.
 *
 * Rules preserved from the inline validation in
 * PigManagementController::recordDeath (was line 2593).
 *
 * Note: the death_cause and disposal_method enums match the broadened set
 * supported by the Phase 0.8 pig_deaths migration (which converted the DB
 * ENUM to varchar+CHECK to accept stillbirth/crushing/starvation/etc.).
 */
class RecordPigDeathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // Authorization handled by policy.
    }

    public function rules(): array
    {
        return [
            'death_date'              => ['required', 'date', 'before_or_equal:today'],
            'death_cause'             => ['required', 'in:disease,accident,natural,stillbirth,crushing,starvation,hypothermia,dehydration,congenital,unknown,other'],
            'death_cause_other'       => ['nullable', 'required_if:death_cause,other', 'string', 'max:255'],
            'final_weight'            => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'death_location'          => ['nullable', 'string', 'max:255'],
            'veterinary_notes'        => ['nullable', 'string', 'max:2000'],
            'disposal_method'         => ['required', 'in:burial,incineration,rendering,donation,disposal,other'],
            'disposal_method_other'   => ['nullable', 'required_if:disposal_method,other', 'string', 'max:255'],
            'disposal_date'           => ['nullable', 'date', 'after_or_equal:death_date'],
            'death_notes'             => ['nullable', 'string', 'max:2000'],
            'tag_outcome'              => ['sometimes', 'in:available,pending_recovery,lost,damaged,retired'],
            'tag_condition_notes'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Convenience: collapses "other" selections into their free-text values
     * and returns the payload the service expects.
     */
    public function normalisedForService(): array
    {
        $data = $this->validated();

        // The enum columns keep their constrained value; the free text goes to
        // its own column.
        //
        // These previously collapsed the free text INTO the enum column, which
        // the CHECK constraint rejects — so selecting "Other" and typing a
        // reason was a 500 in production, every time. It survived because the
        // test database had drifted from the migrations and carried no such
        // constraint.
        //
        // Reads are unaffected: PigDeath accessors surface the free text when
        // the enum says 'other', so every existing `$death->death_cause` call
        // site still shows what the user typed.
        $deathCause      = $data['death_cause'];
        $deathCauseOther = $deathCause === 'other'
            ? (trim((string) ($data['death_cause_other'] ?? '')) ?: null)
            : null;

        $disposalMethod      = $data['disposal_method'];
        $disposalMethodOther = $disposalMethod === 'other'
            ? (trim((string) ($data['disposal_method_other'] ?? '')) ?: null)
            : null;

        return [
            'death_date'       => $data['death_date'],
            'death_cause'      => $deathCause,
            'death_cause_other' => $deathCauseOther,
            'final_weight'     => $data['final_weight']     ?? null,
            'death_location'   => $data['death_location']   ?? null,
            'veterinary_notes' => $data['veterinary_notes'] ?? null,
            'disposal_method'  => $disposalMethod,
            'disposal_method_other' => $disposalMethodOther,
            'disposal_date'    => $data['disposal_date']    ?? null,
            'death_notes'      => $data['death_notes']      ?? null,
            'tag_outcome'      => $data['tag_outcome']      ?? 'pending_recovery',
            'tag_condition_notes' => $data['tag_condition_notes'] ?? null,
        ];
    }
}
