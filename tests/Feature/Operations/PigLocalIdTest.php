<?php

namespace Tests\Feature\Operations;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Services\Sales\RecordPigSaleService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `local_id` must not collide with animals that have left the herd.
 *
 * THE DEFECT
 * ----------
 * `local_id` is a human-facing sequential number, generated on create as
 * max + 1 and protected by a UNIQUE index. A sold pig is SOFT-deleted by
 * `RecordPigSaleService`, so its row — and its local_id — stay in the table and
 * keep occupying that index. A plain `max()` cannot see soft-deleted rows.
 *
 *   pigs 1, 2, 3 exist → sell pig 3 → max() returns 2 → next pig gets 3 → boom
 *
 * So a farm that sold its most recently added animal could not then add another
 * one. Not a multi-tenant edge case: one farm, ordinary use, and the failure is
 * a raw integrity-constraint error at the moment someone records a new pig.
 *
 * It surfaced while fixturing the storefront pig-pool work, where selling
 * animals is the normal case rather than the exception.
 */
class PigLocalIdTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'localid-farm', 'name' => 'Local Id Farm']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $role = Role::firstOrCreate(['name' => 'admin']);

        $this->staff = User::forceCreate([
            'name'            => 'Staff',
            'email'           => 'localid-staff@example.com',
            'password'        => bcrypt('secret'),
            'role'            => 'admin',
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
            'active_status'   => true,
        ]);
    }

    protected function makePig(?Organization $org = null): PigManagement
    {
        return PigManagement::create([
            'organization_id' => ($org ?? $this->org)->id,
            'ear_tag_number'  => 'LID-' . strtoupper(uniqid()),
            'breed'           => 'Large White',
            'gender'          => 'male',
            'stock_type'      => 'standard',
            'date_of_birth'   => now()->subMonths(8)->toDateString(),
            'code'            => 'PIG-' . uniqid(),
        ]);
    }

    /* ================================================================== */

    /**
     * The defect, stated directly.
     */
    public function test_a_new_pig_can_be_added_after_selling_the_newest_one(): void
    {
        $first  = $this->makePig();
        $second = $this->makePig();
        $third  = $this->makePig();

        // Sold through the real spine, which soft-deletes the animal.
        $this->app->make(RecordPigSaleService::class)->execute($third, $this->staff, [
            'sale_date'   => now()->toDateString(),
            'sale_price'  => 900,
            'weight_type' => 'live',
            'sale_weight' => 90,
            'buyer_name'  => 'Walk-in buyer',
        ]);

        // Before the fix this threw a unique-constraint violation.
        $fourth = $this->makePig();

        $this->assertNotNull($fourth->id);
        $this->assertGreaterThan(
            (int) $third->local_id,
            (int) $fourth->local_id,
            'A new pig must take a number beyond every animal that has ever existed, '
            . 'including the ones that have been sold.',
        );
    }

    /**
     * The number must clear soft-deleted rows even when several have gone.
     */
    public function test_the_sequence_clears_every_departed_animal(): void
    {
        $pigs = [];

        for ($i = 0; $i < 5; $i++) {
            $pigs[] = $this->makePig();
        }

        // Sell the last three.
        foreach (array_slice($pigs, 2) as $pig) {
            $this->app->make(RecordPigSaleService::class)->execute($pig, $this->staff, [
                'sale_date'   => now()->toDateString(),
                'sale_price'  => 900,
                'weight_type' => 'live',
                'sale_weight' => 90,
                'buyer_name'  => 'Buyer',
            ]);
        }

        $next = $this->makePig();

        $highestEver = (int) PigManagement::withTrashed()->max('local_id');

        $this->assertSame(
            $highestEver,
            (int) $next->local_id,
            'The new animal must hold the highest number in the table.',
        );

        $this->assertSame(
            1,
            PigManagement::withTrashed()->where('local_id', $next->local_id)->count(),
            'No two animals, living or sold, may share a local_id.',
        );
    }

    /**
     * A second farm must not collide with the first.
     *
     * `local_id` is a GLOBAL sequence — the unique index is on the column
     * alone — so two farms share one series. That is not per-farm numbering,
     * but it is what the schema says, and it must at least not fail.
     */
    public function test_a_second_farm_can_add_pigs_without_colliding(): void
    {
        $this->makePig();
        $this->makePig();

        $otherFarm = Organization::create(['slug' => 'localid-farm-2', 'name' => 'Second Farm']);

        $theirs = $this->makePig($otherFarm);

        $this->assertNotNull($theirs->id);

        $this->assertSame(
            1,
            PigManagement::withTrashed()->where('local_id', $theirs->local_id)->count(),
        );
    }

    /**
     * An explicitly supplied local_id is still honoured — imports and
     * migrations set it directly.
     */
    public function test_an_explicit_local_id_is_left_alone(): void
    {
        $pig = PigManagement::create([
            'organization_id' => $this->org->id,
            'local_id'        => 987654,
            'ear_tag_number'  => 'LID-EXPLICIT',
            'breed'           => 'Duroc',
            'gender'          => 'female',
            'stock_type'      => 'standard',
            'date_of_birth'   => now()->subMonths(8)->toDateString(),
            'code'            => 'PIG-' . uniqid(),
        ]);

        $this->assertSame(987654, (int) $pig->local_id);
    }
}
