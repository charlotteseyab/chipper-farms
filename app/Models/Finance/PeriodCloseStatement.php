<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen financial statement captured at period close.
 */
class PeriodCloseStatement extends Model
{
    use \App\Models\Concerns\BelongsToOrganizationThroughRelation;

    protected static function organizationRelation(): string
    {
        return 'run';
    }

    public const TYPE_TRIAL_BALANCE    = 'trial_balance';
    public const TYPE_INCOME_STATEMENT = 'income_statement';
    public const TYPE_BALANCE_SHEET    = 'balance_sheet';
    public const TYPE_CASH_FLOW        = 'cash_flow';

    protected $fillable = [
        'period_close_run_id',
        'statement_type',
        'payload',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PeriodCloseRun::class, 'period_close_run_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodedPayload(): ?array
    {
        if (empty($this->payload)) {
            return null;
        }
        $decoded = json_decode((string) $this->payload, true);
        return is_array($decoded) ? $decoded : null;
    }
}
