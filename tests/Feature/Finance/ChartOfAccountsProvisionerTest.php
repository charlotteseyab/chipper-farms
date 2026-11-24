<?php

namespace Tests\Feature\Finance;

use App\Models\Organization;
use App\Services\Finance\ChartOfAccountsProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChartOfAccountsProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(int $organizationId)
    {
        return DB::table('chart_of_accounts')->where('organization_id', $organizationId);
    }

    public function test_a_farm_without_a_chart_gets_the_whole_template_with_its_parents(): void
    {
        $farm = Organization::create(['name' => 'New Farm', 'slug' => 'new-farm']);
        $templates = DB::table('chart_of_accounts')->whereNull('organization_id');

        $added = app(ChartOfAccountsProvisioner::class)->sync($farm->id);

        $this->assertGreaterThan(100, $added);
        $this->assertSame((clone $templates)->count(), $this->accounts($farm->id)->count());
        $this->assertSame((clone $templates)->whereNotNull('parent_id')->count(), $this->accounts($farm->id)->whereNotNull('parent_id')->count());

        // A child points at the farm's own parent, not the template's.
        $child = $this->accounts($farm->id)->whereNotNull('parent_id')->first();
        $parent = DB::table('chart_of_accounts')->find($child->parent_id);
        $this->assertSame($farm->id, (int) $parent->organization_id);
    }

    public function test_only_missing_accounts_are_added_and_existing_ones_are_left_alone(): void
    {
        $farm = Organization::create(['name' => 'Old Farm', 'slug' => 'old-farm']);
        $provisioner = app(ChartOfAccountsProvisioner::class);
        $provisioner->sync($farm->id);

        // The farm renamed one account and deleted another; a later migration
        // added 2055 to the template, which the farm does not have yet.
        $this->accounts($farm->id)->where('code', '1010')->update(['name' => 'Petty cash']);
        $this->accounts($farm->id)->where('code', '6010')->update(['deleted_at' => now()]);
        $this->accounts($farm->id)->where('code', '2055')->delete();

        $this->assertSame(['2055'], array_map(fn ($a) => $a->code, $provisioner->missing($farm->id)));
        $this->assertSame(1, $provisioner->sync($farm->id));
        $this->assertSame(0, $provisioner->sync($farm->id), 'Running again adds nothing.');
        $this->assertSame('Petty cash', $this->accounts($farm->id)->where('code', '1010')->value('name'));
        $this->assertNotNull($this->accounts($farm->id)->where('code', '6010')->value('deleted_at'));
    }

    public function test_the_command_only_reports_until_told_to_apply(): void
    {
        $farm = Organization::create(['name' => 'Bare Farm', 'slug' => 'bare-farm']);

        $this->artisan('finance:sync-chart')->assertSuccessful();
        $this->assertSame(0, $this->accounts($farm->id)->count());

        $this->artisan('finance:sync-chart', ['--apply' => true])->assertSuccessful();
        $this->assertGreaterThan(100, $this->accounts($farm->id)->count());
    }
}
