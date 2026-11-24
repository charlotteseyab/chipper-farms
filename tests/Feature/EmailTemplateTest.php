<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\TemplatedEmail;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create(['name' => 'Email Farm', 'slug' => 'email-farm']);
        $this->app->make(CurrentOrganization::class)->bind($this->organization);
    }

    public function test_queued_mail_exposes_transport_errors_for_retry_but_sync_mail_is_best_effort(): void
    {
        $service = \Mockery::mock(EmailTemplateService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->syncDefaults($this->organization->id);
        $service->shouldReceive('configureTransport')->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->assertFalse($service->send($this->organization->id, 'operations.weight_due', 'worker@example.com'));

        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP unavailable');
        (new \App\Notifications\Channels\TemplateMailChannel($service))->send(
            $user, new \App\Notifications\WeightOverdueNotification(null, now()->toDateString())
        );
    }

    public function test_an_inactive_template_suppresses_delivery(): void
    {
        Mail::fake();
        $service = app(EmailTemplateService::class);
        $service->syncDefaults($this->organization->id);

        EmailTemplate::query()
            ->where('organization_id', $this->organization->id)
            ->where('key', 'storefront.order_placed')
            ->update(['is_active' => false]);

        $sent = $service->send(
            $this->organization->id,
            'storefront.order_placed',
            'buyer@example.com',
            ['order_reference' => 'SO-TEST', 'customer_name' => 'Ama'],
        );

        $this->assertFalse($sent);
        Mail::assertNothingSent();
    }

    public function test_an_active_template_is_rendered_and_sent(): void
    {
        Mail::fake();

        $sent = app(EmailTemplateService::class)->send(
            $this->organization->id,
            'storefront.order_placed',
            'buyer@example.com',
            [
                'order_reference' => 'SO-TEST',
                'customer_name' => 'Ama',
                'order_total' => 'GHS 100.00',
                'order_status' => 'pending confirmation',
                'payment_status' => 'cash on delivery',
                'action_url' => 'https://example.com/order',
                'create_account_url' => 'https://example.com/account/create',
            ],
        );

        $this->assertTrue($sent);
        Mail::assertSentCount(1);
        Mail::assertSent(TemplatedEmail::class, fn (TemplatedEmail $mail) =>
            $mail->secondaryActionUrl === 'https://example.com/account/create'
            && $mail->secondaryActionLabel === 'Create buyer account'
        );
    }

    public function test_an_admin_can_disable_a_template_for_their_organization(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secret'),
            'role' => 'admin', 'role_id' => $role->id, 'organization_id' => $this->organization->id,
            'active_status' => true, 'email_verified_at' => now(),
        ]);

        app(EmailTemplateService::class)->syncDefaults($this->organization->id);
        $template = EmailTemplate::where('organization_id', $this->organization->id)->firstOrFail();

        $this->actingAs($admin)->put(route('settings.email.templates.update', $template), [
            'subject' => $template->subject,
            'body' => $template->body,
            'action_label' => $template->action_label,
        ])->assertRedirect(route('settings.email', ['tab' => 'templates']));

        $this->assertFalse($template->refresh()->is_active);
        $this->assertSame($admin->id, $template->updated_by_user_id);
    }

    public function test_farm_admin_sees_templates_but_cannot_view_or_change_the_shared_mail_server(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::forceCreate([
            'name' => 'Farm Admin', 'email' => 'farm-admin@example.com', 'password' => bcrypt('secret'),
            'role' => 'admin', 'role_id' => $role->id, 'organization_id' => $this->organization->id,
            'active_status' => true, 'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('settings.email'))
            ->assertOk()
            ->assertSee('Email Templates')
            ->assertSee('Email delivery is managed securely by the platform.')
            ->assertDontSee('Mail Server Settings')
            ->assertDontSee('Server Connection Testing');

        $this->actingAs($admin)->put(route('settings.email.update'))->assertForbidden();
        $this->actingAs($admin)->post(route('settings.email.test-connection'))->assertForbidden();
        $this->actingAs($admin)->post(route('settings.email.send-test'))->assertForbidden();
    }

    public function test_superuser_can_view_the_shared_mail_server_settings(): void
    {
        $role = Role::firstOrCreate(['name' => 'superuser']);
        $superuser = User::forceCreate([
            'name' => 'Platform User', 'email' => 'platform-email@example.com', 'password' => bcrypt('secret'),
            'role' => 'superuser', 'role_id' => $role->id, 'organization_id' => null,
            'active_status' => true, 'email_verified_at' => now(),
        ]);

        $this->actingAs($superuser)->get(route('settings.email'))
            ->assertOk()
            ->assertSee('Mail Server Settings')
            ->assertSee('Server Connection Testing')
            ->assertSee('Email Templates');
    }
}
