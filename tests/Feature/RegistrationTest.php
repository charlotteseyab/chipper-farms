<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_forbidden_for_guests(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertForbidden();
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_authenticated_superuser_is_redirected_from_registration_screen(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $role = Role::firstOrCreate(['name' => 'superuser']);
        $superuser = User::forceCreate([
            'name' => 'Superuser',
            'email' => 'superuser@example.com',
            'password' => bcrypt('password'),
            'role' => 'superuser',
            'role_id' => $role->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($superuser)
            ->get('/register')
            ->assertRedirect();
    }
}
