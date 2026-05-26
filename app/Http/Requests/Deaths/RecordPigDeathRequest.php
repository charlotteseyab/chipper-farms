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
        ];
    }

    /**
     * Convenience: collapses "other" selections into their free-text values
     * and returns the payload the service expects.
     */
    public function normalisedForService(): array
    {
        $data = $this->validated();

        $deathCause = $data['death_cause'] === 'other'
            ? trim((string) ($data['death_cause_other'] ?? ''))
            : $data['death_cause'];

        $disposalMethod = $data['disposal_method'] === 'other'
            ? trim((string) ($data['disposal_method_other'] ?? ''))
            : $data['disposal_method'];

        return [
            'death_date'       => $data['death_date'],
            'death_cause'      => $deathCause,
            'final_weight'     => $data['final_weight']     ?? null,
            'death_location'   => $data['death_location']   ?? null,
            'veterinary_notes' => $data['veterinary_notes'] ?? null,
            'disposal_method'  => $disposalMethod,
            'disposal_date'    => $data['disposal_date']    ?? null,
            'death_notes'      => $data['death_notes']      ?? null,
        ];
    }
}