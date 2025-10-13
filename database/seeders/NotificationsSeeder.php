<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\PigManagement;

class NotificationsSeeder extends Seeder
{
    public function run(): void
    {
        // Pick some recipients (dataentry, farmmanager, admin, superuser); fallback to any users
        $recipients = User::whereHas('role', function($q) {
                $q->whereIn('name', ['dataentry','farmmanager','admin','superuser']);
            })->get();
        if ($recipients->isEmpty()) {
            $recipients = User::take(3)->get();
        }
        if ($recipients->isEmpty()) {
            return; // no users to notify
        }

        // Grab some pigs for context; fallback to synthetic
        $pigs = PigManagement::active()->take(5)->get();

        $now = now();
        foreach ($recipients as $user) {
            // Create 3 notifications per user: 2 overdue, 1 due soon
            for ($i = 0; $i < 3; $i++) {
                $type = $i < 2 ? 'weight_overdue' : 'weight_due_soon';
                $pig = $pigs[$i % max(1, $pigs->count())] ?? null;
                $pigId = $pig?->id;
                $earTag = $pig?->ear_tag_number ?? ('DEMO-'.str_pad((string)rand(1,999), 3, '0', STR_PAD_LEFT));
                $daysSince = $type === 'weight_overdue' ? rand(35, 70) : rand(20, 29);
                $lastWeighed = $now->copy()->subDays($daysSince)->format('Y-m-d');

                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'database',
                    'notifiable_type' => get_class($user),
                    'notifiable_id' => $user->getKey(),
                    'data' => json_encode([
                        'type' => $type,
                        'pig_id' => $pigId,
                        'ear_tag' => $earTag,
                        'days_since' => $daysSince,
                        'last_weighed' => $lastWeighed,
                        'link' => route('weights.create', ['pig_id' => $pigId]),
                        'date_key' => $now->toDateString(),
                        'message' => $type === 'weight_overdue' ? 'Weighing overdue' : 'Weighing due soon',
                    ]),
                    'read_at' => null,
                    'created_at' => $now->copy()->subMinutes(rand(10, 1000)),
                    'updated_at' => $now,
                ]);
            }
        }
    }
}


