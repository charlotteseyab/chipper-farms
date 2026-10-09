<?php

/*
| Health reminders (decision D9). In-app only, to farm managers and the
| person responsible for the course or case. Each reminder is sent once.
*/
return [
    'reminders' => [
        // Doses due today are announced from this time on the day.
        'doses_due_from' => env('HEALTH_DOSES_DUE_FROM', '06:00'),
        // A scheduled dose still not given this long after it was due is overdue.
        'overdue_after_hours' => (int) env('HEALTH_OVERDUE_AFTER_HOURS', 2),
        // Overdue doses older than this are left to the worklist, not re-announced.
        'overdue_lookback_days' => 7,
        'follow_up_days_ahead' => 1,
        'withdrawal_days_ahead' => 2,
        'batch_expiry_days_ahead' => 30,
    ],

    /*
    | Files on vet visits, examinations, cases and follow-ups (lab results,
    | photos, the vet's written report). Stored in the shared attachments table.
    */
    'attachments' => [
        'disk' => env('HEALTH_ATTACHMENTS_DISK', 'local'),
        'max_size_mb' => 10,
        'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
    ],
];
