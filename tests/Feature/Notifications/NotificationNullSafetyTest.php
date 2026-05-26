<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PigletWeaningDueNotification;
use App\Notifications\WeightDueSoonNotification;
use App\Notifications\WeightOverdueNotification;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E3 — verify the hardened notifications survive a null pig.
 *
 * Before E3: $this->pig->ear_tag_number on a deleted-since-enqueue pig
 * would throw inside the queue worker, killing the whole job.
 *
 * After E3: typed ?PigManagement + null-safe accessors produce a degraded
 * but valid payload.
 */
class NotificationNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_piglet_weaning_due_notification_handles_null_pig(): void
    {
        $notification = new PigletWeaningDueNotification(null, 'overdue', 5);

        $payload = $notification->toDatabase((object) []);

        $this->assertSame('piglet_weaning_overdue', $payload['type']);
        $this->assertNull($payload['pig_id']);
        $this->assertSame('(deleted)', $payload['ear_tag']);
        $this->assertNull($payload['link']);
        $this->assertStringContainsString('(deleted)', $payload['message']);
    }

    public function test_piglet_weaning_due_notification_with_real_pig(): void
    {
        $pig = PigManagement::create([
            'ear_tag_number' => 'T-001',
            'code' => 'PIG-001',
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(40)->toDateString(),
        ]);

        $notification = new PigletWeaningDueNotification($pig, 'due_today', 0);
        $payload = $notification->toDatabase((object) []);

        $this->assertSame('T-001', $payload['ear_tag']);
        $this->assertSame($pig->id, $payload['pig_id']);
        $this->assertNotNull($payload['link']);
    }

    public function test_weight_due_soon_notification_handles_null_pig(): void
    {
        $notification = new WeightDueSoonNotification(null, '2026-04-25');

        $payload = $notification->toDatabase((object) []);

        $this->assertSame('weight_due_soon', $payload['type']);
        $this->assertNull($payload['pig_id']);
        $this->assertSame('(deleted)', $payload['ear_tag']);
        $this->assertNull($payload['link']);
        $this->assertNull($payload['days_since']);
    }

    public function test_weight_overdue_notification_handles_null_pig(): void
    {
        $notification = new WeightOverdueNotification(null, '2026-04-25');

        $payload = $notification->toDatabase((object) []);

        $this->assertSame('weight_overdue', $payload['type']);
        $this->assertNull($payload['pig_id']);
        $this->assertSame('(deleted)', $payload['ear_tag']);
        $this->assertNull($payload['link']);
    }
}
