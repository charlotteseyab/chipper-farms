<?php

namespace Tests\Feature\Operations;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\PdfExportLimit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards against the ways the system could fill a disk, pile up work or tie
 * up the server (after the October 2026 outage).
 */
class ProductionSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_postings_run_in_the_scheduler_not_the_queue(): void
    {
        config(['queue.default' => 'database']);

        $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'finance:process-events');
        $this->assertNotNull($event);
        $event->run($this->app);

        // Queued, a job piled up every minute while the worker was down.
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_no_scheduled_task_holds_its_overlap_lock_for_a_day(): void
    {
        foreach (app(Schedule::class)->events() as $event) {
            if ($event->withoutOverlapping) {
                $this->assertLessThanOrEqual(60, $event->expiresAt, ($event->description ?: $event->command) . ' keeps its lock too long.');
            }
        }
    }

    public function test_a_pdf_export_that_is_too_long_is_refused_with_a_message(): void
    {
        $this->assertNull(PdfExportLimit::check(PdfExportLimit::MAX_ROWS));
        $refused = PdfExportLimit::check(PdfExportLimit::MAX_ROWS + 1);
        $this->assertNotNull($refused);
        $this->assertStringContainsString('Excel or CSV', session('errors')->first('export'));
    }

    public function test_settings_are_read_once_per_request_and_a_save_is_seen_at_once(): void
    {
        $farm = Organization::create(['name' => 'Farm', 'slug' => 'farm']);
        $this->app->make(CurrentOrganization::class)->bind($farm);
        Setting::set('feed.example', 'one');

        DB::enableQueryLog();
        Setting::get('feed.example');
        Setting::get('feed.example');
        $this->assertSame('one', Setting::get('feed.example'));
        $this->assertCount(1, DB::getQueryLog(), 'Each setting should be queried once per request.');

        Setting::set('feed.example', 'two');
        $this->assertSame('two', Setting::get('feed.example'));
    }

    public function test_housekeeping_removes_old_notifications_and_abandoned_guest_carts_only(): void
    {
        $user = User::factory()->create();
        $insert = fn (string $id, $readAt, $createdAt) => DB::table('notifications')->insert([
            'id' => $id, 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $user->id,
            'data' => '{}', 'read_at' => $readAt, 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
        $insert('old-read', now()->subDays(100), now()->subDays(100));
        $insert('old-unread', null, now()->subDays(100));
        $insert('ancient-unread', null, now()->subDays(200));
        $insert('recent', now(), now());

        $cart = fn (?int $userId, string $status, $at) => DB::table('storefront_carts')->insertGetId([
            'organization_id' => $user->organization_id, 'user_id' => $userId, 'guest_token' => uniqid(), 'status' => $status,
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $abandoned = $cart(null, 'active', now()->subDays(40));
        $fresh = $cart(null, 'active', now()->subDays(2));
        $buyer = $cart($user->id, 'active', now()->subDays(40));
        $converted = $cart(null, 'converted', now()->subDays(40));

        $this->artisan('system:prune-housekeeping')->assertSuccessful();

        $this->assertSame(['old-unread', 'recent'], DB::table('notifications')->orderBy('id')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$fresh, $buyer, $converted], DB::table('storefront_carts')->pluck('id')->all());
        $this->assertNotContains($abandoned, DB::table('storefront_carts')->pluck('id')->all());
    }

    public function test_one_address_cannot_try_unlimited_accounts(): void
    {
        $limits = \Illuminate\Support\Facades\RateLimiter::limiter('login')(request()->merge(['email' => 'a@b.c']));

        $perIp = collect($limits)->first(fn ($l) => str_starts_with((string) $l->key, 'ip:'));
        $this->assertNotNull($perIp);
        $this->assertSame(60, $perIp->maxAttempts);
    }
}
