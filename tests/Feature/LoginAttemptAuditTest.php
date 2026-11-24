<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAttemptAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_for_a_real_account_keeps_the_username(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $log = ActivityLog::where('action', 'login_attempt')->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($user->email, $log->name);
        $this->assertSame('failed', $log->metadata['result']);
    }

    public function test_failed_login_for_an_unknown_identifier_stores_only_a_hash(): void
    {
        // A password typed into the email field must not land in the audit trail.
        $this->post('/login', ['email' => 'Hunter2-secret', 'password' => 'whatever']);

        $log = ActivityLog::where('action', 'login_attempt')->sole();
        $this->assertNull($log->user_id);
        $this->assertNull($log->name);
        $this->assertStringNotContainsString('Hunter2-secret', json_encode($log->getAttributes()));
        $this->assertSame(12, strlen($log->metadata['unknown_identifier_hash']));
    }

    public function test_attempts_against_a_locked_login_are_not_each_audited(): void
    {
        $user = User::factory()->create();

        // Five failures set the lock (one 'failed' row each, the fifth logged as 'locked').
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }
        $afterLock = ActivityLog::where('action', 'login_attempt')->count();

        // A bot keeps hammering while locked.
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->assertSame($afterLock, ActivityLog::where('action', 'login_attempt')->count());
        $this->assertSame(1, ActivityLog::where('action', 'login_attempt')->get()
            ->where('metadata.result', 'locked')->count());
        $this->assertGuest();
    }
}
