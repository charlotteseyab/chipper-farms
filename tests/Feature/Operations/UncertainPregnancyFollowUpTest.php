<?php

namespace Tests\Feature\Operations;

use App\Models\Mating;
use App\Models\PigManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UncertainPregnancyFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_uncertain_pregnancy_schedules_follow_up(): void
    {
        $mating = $this->completedMating();
        $followUpDate = now()->addDays(3)->toDateString();

        $this->assertTrue($mating->recordPregnancyCheck(
            status: 'uncertain',
            method: 'ultrasound',
            confidence: 'low',
            notes: 'Could not confirm embryo status.',
            checkDate: now()->toDateString(),
            userId: null,
            followUpDate: $followUpDate,
        ));

        $mating->refresh();

        $this->assertTrue($mating->isPregnancyUncertain());
        $this->assertTrue($mating->needsPregnancyFollowUp());
        $this->assertSame($followUpDate, $mating->pregnancy_follow_up_date->toDateString());
        $this->assertSame('scheduled', $mating->pregnancy_follow_up_status);
        $this->assertNull($mating->pregnancy_follow_up_completed_at);
    }

    public function test_follow_up_due_and_overdue_scopes_detect_uncertain_pregnancies(): void
    {
        $due = $this->completedMating('M-DUE', 'F-DUE');
        $due->recordPregnancyCheck('uncertain', 'physical_exam', 'low', 'Recheck today.', now()->toDateString(), null, now()->toDateString());

        $overdue = $this->completedMating('M-OLD', 'F-OLD');
        $overdue->recordPregnancyCheck('uncertain', 'physical_exam', 'low', 'Recheck overdue.', now()->subDays(3)->toDateString(), null, now()->subDay()->toDateString());

        $this->assertSame(2, Mating::query()->pregnancyFollowUpDue()->count());
        $this->assertSame(1, Mating::query()->pregnancyFollowUpOverdue()->count());
        $this->assertTrue($overdue->fresh()->pregnancyFollowUpOverdue());
    }

    public function test_resolving_uncertain_pregnancy_to_pregnant_clears_follow_up_task(): void
    {
        $mating = $this->completedMating();
        $mating->recordPregnancyCheck('uncertain', 'ultrasound', 'low', 'Recheck needed.', now()->toDateString(), null, now()->addDay()->toDateString());

        $this->assertTrue($mating->fresh()->recordPregnancyCheck(
            status: 'pregnant',
            method: 'ultrasound',
            confidence: 'high',
            notes: 'Pregnancy confirmed.',
            checkDate: now()->toDateString(),
        ));

        $mating->refresh();

        $this->assertSame('pregnant', $mating->pregnancy_status);
        $this->assertNull($mating->pregnancy_follow_up_date);
        $this->assertSame('resolved_pregnant', $mating->pregnancy_follow_up_status);
        $this->assertNotNull($mating->pregnancy_follow_up_completed_at);
        $this->assertFalse($mating->needsPregnancyFollowUp());
    }

    protected function completedMating(string $maleTag = 'M-001', string $femaleTag = 'F-001'): Mating
    {
        $male = PigManagement::create([
            'ear_tag_number' => $maleTag,
            'breed' => 'LW',
            'gender' => 'M',
            'date_of_birth' => now()->subYears(2)->toDateString(),
            'code' => $maleTag,
            'stock_type' => 'breeding',
        ]);

        $female = PigManagement::create([
            'ear_tag_number' => $femaleTag,
            'breed' => 'LW',
            'gender' => 'F',
            'date_of_birth' => now()->subYears(2)->toDateString(),
            'code' => $femaleTag,
            'stock_type' => 'breeding',
        ]);

        return Mating::create([
            'male_id' => $male->id,
            'female_id' => $female->id,
            'planned_date' => now()->subDays(20)->toDateString(),
            'actual_date' => now()->subDays(18)->toDateString(),
            'status' => 'completed',
            'pregnancy_status' => 'pending',
        ]);
    }
}
