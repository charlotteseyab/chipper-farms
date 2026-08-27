<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_scoped_reports_for_a_selected_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.reports', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Farm reports')
            ->assertSee('Herd movement reconciliation')
            ->assertSee('01 Aug 2026')
            ->assertSee('31 Aug 2026');
    }

    public function test_reports_reject_an_inverted_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('settings.reports'))
            ->get(route('settings.reports', ['from' => '2026-08-31', 'to' => '2026-08-01']))
            ->assertRedirect(route('settings.reports'))
            ->assertSessionHasErrors('to');
    }

    public function test_csv_export_uses_the_selected_period(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('settings.reports.export.csv', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('2026-08-01 to 2026-08-31', $response->streamedContent());
    }

    public function test_pdf_export_renders_for_the_selected_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.reports.export.pdf', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
