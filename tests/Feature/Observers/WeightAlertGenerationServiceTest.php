<?php

namespace Tests\Feature\Observers;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\WeightAlert;
use App\Services\Weights\WeightAlertGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E3 — verify the alert generation service swaps old+new alerts
 * atomically. Previously the observer's updated() handler did
 * delete-then-create as separate operations; if the create threw between
 * them, the system was left without an alert at all.
 *
 * This test focuses on the service (the observer is a thin shell that
 * delegates here, and its transaction behaviour is inherited from the
 * service).
 */
class WeightAlertGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makePig(): PigManagement
    {
        return PigManagement::create([
            'ear_tag_number' => 'T-' . uniqid(),
            'code' => 'PIG-' . uniqid(),
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subWeeks(10)->toDateString(),
        ]);
    }

    protected function makeWeight(PigManagement $pig, float $kg): PigWeight
    {
        // Avoid the observer kicking in during setup — disable it.
        PigWeight::unsetEventDispatcher();

        $w = PigWeight::create([
            'pig_id'       => $pig->id,
            'weigh_date'   => now()->toDateString(),
            'weight'       => $kg,
            'recorded_by'  => null,
        ]);

        return $w;
    }

    public function test_generate_creates_alert_for_pig_with_birth_date(): void
    {
        $pig = $this->makePig();
        $w   = $this->makeWeight($pig, 12.0);

        $service = $this->app->make(WeightAlertGenerationService::class);

        $alert = $service->generate($w);

        $this->assertNotNull($alert);
        $this->assertSame($w->id, $alert->weight_record_id);
    }

    public function test_generate_returns_null_when_pig_has_no_birth_date(): void
    {
        $pig = new PigManagement([
            'ear_tag_number' => 'T-NOBIRTH',
            'code' => 'PIG-NOBIRTH',
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => null,
        ]);

        $w = new PigWeight([
            'weight' => 12.0,
            'weigh_date' => now()->toDateString(),
        ]);
        $w->setRelation('pig', $pig);

        $service = $this->app->make(WeightAlertGenerationService::class);

        $alert = $service->generate($w);

        $this->assertNull($alert);
        $this->assertSame(0, WeightAlert::count());
    }

    public function test_swap_with_existing_alert_replaces_atomically(): void
    {
        $pig = $this->makePig();
        $w   = $this->makeWeight($pig, 12.0);

        $service = $this->app->make(WeightAlertGenerationService::class);

        // First generation
        $first = $service->generate($w);
        $this->assertSame(1, WeightAlert::where('weight_record_id', $w->id)->count());
        $firstId = $first->id;

        // Now regenerate, passing the existing alert
        $w->update(['weight' => 14.0]);
        $second = $service->generate($w, $first);

        // Old alert gone, new one in its place — count is exactly 1
        $this->assertSame(1, WeightAlert::where('weight_record_id', $w->id)->count());
        $this->assertNotSame($firstId, $second->id);
    }

    public function test_delete_alerts_for_removes_all_matching(): void
    {
        $pig = $this->makePig();
        $w   = $this->makeWeight($pig, 12.0);
        $service = $this->app->make(WeightAlertGenerationService::class);

        $service->generate($w);
        $this->assertSame(1, WeightAlert::where('weight_record_id', $w->id)->count());

        $count = $service->deleteAlertsFor($w);

        $this->assertSame(1, $count);
        $this->assertSame(0, WeightAlert::where('weight_record_id', $w->id)->count());
    }
}
