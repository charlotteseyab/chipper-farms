<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\User;
use App\Notifications\PigletWeaningDueNotification;
use App\Notifications\WeightOverdueNotification;
use App\Services\Notifications\InAppNotificationService;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * In-app notifications must appear at once, never wait for a queue worker.
 * Email may still be queued. The suite normally runs the queue synchronously,
 * which hides the difference, so these tests use the database queue.
 */
class InAppNotificationsAreInstantTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PigManagement $pig;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
        $org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($org);
        $this->user = User::forceCreate(['name' => 'Manager', 'email' => 'manager@instant.test', 'password' => bcrypt('password'),
            'role' => 'admin', 'organization_id' => $org->id, 'active_status' => true]);
        $this->pig = PigManagement::create(['organization_id' => $org->id, 'ear_tag_number' => '1', 'code' => 'IN-1', 'breed' => 'LW',
            'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(3)->toDateString()]);
    }

    public function test_weighing_and_weaning_alerts_reach_the_bell_without_a_worker(): void
    {
        $this->user->notify(new WeightOverdueNotification($this->pig, now()->toDateString()));
        $this->user->notify(new PigletWeaningDueNotification($this->pig, 'due', 2));

        $this->assertSame(2, $this->user->notifications()->count(), 'Written before any worker runs');
        $queuedChannels = DB::table('jobs')->pluck('payload')
            ->flatMap(fn ($payload) => unserialize(json_decode($payload, true)['data']['command'])->channels)->unique()->values()->all();
        $this->assertNotContains('database', $queuedChannels, 'No in-app delivery waits in the queue');
        $this->assertNotEmpty($queuedChannels, 'Email is still queued');
    }

    public function test_the_weighing_check_sees_todays_alert_so_it_is_not_sent_twice(): void
    {
        $this->user->notify(new WeightOverdueNotification($this->pig, now()->toDateString()));

        $this->assertTrue($this->user->notifications()->where('data->pig_id', $this->pig->id)->where('data->date_key', now()->toDateString())->exists());
    }

    public function test_system_notifications_are_written_at_once(): void
    {
        app(InAppNotificationService::class)->notifyRelevantStaff((int) $this->user->organization_id, 'system.action_completed', 'Done', 'Saved.');

        $this->assertSame(1, $this->user->notifications()->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    /** Any notification that is queued and writes to the bell must send that part on the sync connection. */
    public function test_every_queued_notification_writes_its_in_app_part_synchronously(): void
    {
        $checked = 0;
        foreach (glob(app_path('Notifications/*.php')) as $file) {
            $class = 'App\\Notifications\\'.basename($file, '.php');
            if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class)) {
                continue;
            }
            $notification = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            if (! in_array('database', $notification->via($this->user), true)) {
                continue;
            }
            $this->assertTrue(method_exists($notification, 'viaConnections'), "{$class} queues its in-app notification");
            $this->assertSame('sync', $notification->viaConnections()['database'] ?? null, "{$class} queues its in-app notification");
            $checked++;
        }
        $this->assertGreaterThanOrEqual(7, $checked);
    }
}
