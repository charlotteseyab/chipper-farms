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
        // Vaccinations and deworming are announced this many days ahead, so
        // the product can be fetched and the animals penned. 0 turns it off.
        'routine_doses_days_ahead' => 1,
        'batch_expiry_days_ahead' => 30,
    ],

    /*
    | Health alerts: overdue, due today and coming up (alerts page, pig
    | profile, dashboards). "Coming up" looks this many days ahead.
    */
    'alerts' => [
        'upcoming_days' => (int) env('HEALTH_ALERTS_UPCOMING_DAYS', 7),
    ],

    /*
    | Herd health programmes: doses are scheduled this many days before they
    | are due, so the alerts and day-ahead reminders see them in time.
    */
    'programmes' => [
        'schedule_days_ahead' => (int) env('HEALTH_PROGRAMMES_DAYS_AHEAD', 14),
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
