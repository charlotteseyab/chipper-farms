@php
    // Ensure plain CSV, no BOM, no HTML
    $headers = [
        'Pig Ear Tag',
        'Pig Code',
        'Gender',
        'Weigh Date',
        'Target Week',
        'Weight (kg)',
        'Prev Weigh Date',
        'Prev Week',
        'Prev Weight (kg)',
        'Days Since Prev',
        'Gain (kg)',
        'Gain Per Day (kg)',
        'Gain Per Week (kg)',
        'Notes',
    ];
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['ear_tag'],
            $r['code'],
            $r['gender'],
            $r['weigh_date'],
            $r['target_week'],
            $r['weight'] !== null ? number_format((float)$r['weight'], 2) : null,
            $r['prev_weigh_date'],
            $r['prev_week'],
            $r['prev_weight'] !== null ? number_format((float)$r['prev_weight'], 2) : null,
            $r['days_since_prev'],
            $r['gain'] !== null ? number_format((float)$r['gain'], 3) : null,
            $r['gain_per_day'] !== null ? number_format((float)$r['gain_per_day'], 4) : null,
            $r['gain_per_week'] !== null ? number_format((float)$r['gain_per_week'], 4) : null,
            $r['notes'],
        ]);
    }
    fclose($out);
    exit;
@endphp

