<?php

namespace Tests\Feature\Console;

use App\Models\Mating;
use App\Models\PigManagement;
use App\Models\User;
use App\Notifications\GestationDueSoonNotification;
use App\Notifications\GestationDueTodayNotification;
use App\Notifications\GestationOverdueNotification;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * gestation:notify sent nothing for as long as Carbon 3 was installed:
 * diffInDays() returns a float, and the command compared it with `=== 3`,
 * `=== 0` and `=== -7`, which a float never matches. Nothing errored, so these
 * tests pin that each milestone actually notifies.
 */
class GestationNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->app->make(CurrentOrganization::class)->bind($this->admin->organization);
    }

    public static function milestoneProvider(): array
    {
        return [
            'due in 3 days' => [3, GestationDueSoonNotification::class],
            'due today' => [0, GestationDueTodayNotification::class],
            'a week overdue' => [-7, GestationOverdueNotification::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('milestoneProvider')]
    public function test_each_milestone_notifies_once(int $daysFromToday, string $notification): void
    {
        $this->pregnantSow(now()->addDays($daysFromToday)->toDateString());

        $this->artisan('gestation:notify')->assertSuccessful();
        // The scheduler runs it every minute; the second run must not repeat it.
        $this->artisan('gestation:notify')->assertSuccessful();

        $this->assertSame(1, $this->admin->notifications()->where('type', $notification)->count());
    }

    public function test_days_between_milestones_do_not_notify(): void
    {
        $this->pregnantSow(now()->addDays(10)->toDateString());

        $this->artisan('gestation:notify')->assertSuccessful();

        $this->assertSame(0, $this->admin->notifications()->count());
    }

    protected function pregnantSow(string $expectedFarrowing): Mating
    {
        $pig = fn (string $tag, string $gender) => PigManagement::create([
            'organization_id' => $this->admin->organization_id,
            'ear_tag_number' => $tag,
            'breed' => 'LW',
            'gender' => $gender,
            'date_of_birth' => now()->subYears(2)->toDateString(),
            'code' => $tag,
            'stock_type' => 'breeding',
        ]);

        $male = $pig('M-001', 'M');
        $female = $pig('F-001', 'F');

        return Mating::create([
            'male_id' => $male->id,
            'female_id' => $female->id,
            'planned_date' => now()->subDays(100)->toDateString(),
            'actual_date' => now()->subDays(100)->toDateString(),
            'status' => 'completed',
            'pregnancy_status' => 'pregnant',
            'expected_farrowing_date' => $expectedFarrowing,
        ]);
    }
}
