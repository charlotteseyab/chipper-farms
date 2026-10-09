<?php

namespace App\Services\Health;

use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The one answer to "may this pig enter the food chain on this date?".
 *
 * Direct sales, storefront eligibility and the pig's health page all ask
 * here, so the rule cannot drift between them. Withdrawal days come only
 * from what was recorded on each dose; nothing is inferred from a medicine
 * name. A recent dose with no withdrawal recorded is a caution, never "safe".
 *
 * Date rule (decision D7): a dose's restriction runs through
 * dose date + withdrawal days, and the pig may be sold from the next day.
 */
class WithdrawalStatusService
{
    /** How far back a dose with no withdrawal recorded is still worth flagging. */
    public const CAUTION_DAYS = 90;

    /**
     * @return array{restricted: bool, until: ?CarbonImmutable, doses: Collection<int, MedicationAdministration>, cautions: Collection<int, MedicationAdministration>}
     */
    public function forPig(PigManagement|int $pig, ?string $asOf = null): array
    {
        $pigId = $pig instanceof PigManagement ? (int) $pig->getKey() : $pig;
        $asOf ??= now()->toDateString();

        $doses = MedicationAdministration::query()
            ->where('pig_id', $pigId)
            ->whereDate('administration_date', '<=', $asOf)
            ->underWithdrawal($asOf)
            ->orderByDesc('withdrawal_ends_on')
            ->orderByDesc('id')
            ->get();

        $cautions = MedicationAdministration::query()
            ->where('pig_id', $pigId)
            ->notVoided()
            ->whereNull('withdrawal_period_days')
            ->whereDate('administration_date', '<=', $asOf)
            ->whereDate('administration_date', '>=', CarbonImmutable::parse($asOf)->subDays(self::CAUTION_DAYS)->toDateString())
            ->orderByDesc('administration_date')
            ->orderByDesc('id')
            ->get();

        $latest = $doses->first()?->withdrawal_ends_on;

        return [
            'restricted' => $doses->isNotEmpty(),
            'until'      => $latest ? CarbonImmutable::parse($latest) : null,
            'doses'      => $doses,
            'cautions'   => $cautions,
        ];
    }

    /**
     * Farm-wide: one row per animal still on the farm that is restricted on
     * the date, being the dose that restricts it longest. Overview counts,
     * dashboards and the withdrawal report all read this, so they agree.
     */
    public function restrictedAnimals(?string $asOf = null): \Illuminate\Database\Eloquent\Builder
    {
        $asOf ??= now()->toDateString();

        return MedicationAdministration::query()->with('pig')
            ->whereDate('administration_date', '<=', $asOf)
            ->underWithdrawal($asOf)
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            ->whereNotExists(fn ($later) => $later->selectRaw('1')->from('medication_administrations as later')
                ->whereColumn('later.pig_id', 'medication_administrations.pig_id')->whereNull('later.voided_at')
                ->where(fn ($q) => $q->whereColumn('later.withdrawal_ends_on', '>', 'medication_administrations.withdrawal_ends_on')
                    ->orWhere(fn ($q) => $q->whereColumn('later.withdrawal_ends_on', 'medication_administrations.withdrawal_ends_on')
                        ->whereColumn('later.id', '>', 'medication_administrations.id'))))
            ->orderBy('withdrawal_ends_on');
    }

    public function restrictedCount(?string $asOf = null): int
    {
        return $this->restrictedAnimals($asOf)->count();
    }

    /** First date the pig may be sold, or null when it is not restricted. */
    public function clearsOn(PigManagement|int $pig, ?string $asOf = null): ?CarbonImmutable
    {
        return $this->forPig($pig, $asOf)['until']?->addDay();
    }

    /** One sentence for refusals and warnings, naming the dose that restricts the pig longest. */
    public function message(array $status): ?string
    {
        if (! $status['restricted']) {
            return null;
        }
        $dose = $status['doses']->first();

        return sprintf(
            'Under medicine withdrawal until %s (%s, given %s). It can be sold from %s. Selling before then puts drug residue into food.',
            $status['until']->format('d M Y'),
            $dose->medication_name ?: 'treatment',
            CarbonImmutable::parse($dose->administration_date)->format('d M Y'),
            $status['until']->addDay()->format('d M Y'),
        );
    }
}
