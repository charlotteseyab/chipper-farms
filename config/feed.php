<?php

/*
| Feed reminders. These are the defaults; a farm manager can change each one
| in Feed Setup → settings (stored in settings under feed.alerts.*).
*/
return [
    'reminders' => [
        // Finished feed is "running low" below this many days of cover...
        'cover_days' => 7,
        // ...and "mix now" below this many.
        'cover_urgent_days' => 3,
        // A stage with animals and a ration but no feeding recorded by this time is reminded.
        'feeding_due_by' => '10:00',
        // Ingredients are checked against the mixing needed over this many days.
        'ingredient_days' => 7,
        // A production plan still waiting after this many hours is reminded.
        'plan_waiting_hours' => 24,
    ],
];
