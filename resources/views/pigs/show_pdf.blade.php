<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pig Profile - {{ $pig->ear_tag_number ?? $pig->code ?? $pig->id }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
        h1, h2, h3 { margin: 0 0 6px 0; }
        h1 { font-size: 20px; }
        h2 { font-size: 14px; margin-top: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .text-muted { color: #6b7280; }
        .small { font-size: 10px; }
        .section { margin-bottom: 14px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 999px; font-size: 9px; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-gray { background: #e5e7eb; color: #374151; }
        .badge-red { background: #fee2e2; color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .footer {
            margin-top: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <h1>Pig Profile: {{ $pig->ear_tag_number ?? 'N/A' }}</h1>
    <p class="text-muted small">
        Generated: {{ now()->format('Y-m-d H:i') }} • ID: {{ $pig->id }}
    </p>

    <div class="section">
        <h2>Basic Information</h2>
        <table>
            <tbody>
                <tr>
                    <th>Ear Tag</th>
                    <td>{{ $pig->ear_tag_number ?? 'N/A' }}</td>
                    <th>Code</th>
                    <td>{{ $pig->code ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Breed</th>
                    <td>{{ $pig->breed ?? 'N/A' }}</td>
                    <th>Gender</th>
                    <td>{{ $pig->gender === 'M' ? 'Male' : 'Female' }}</td>
                </tr>
                <tr>
                    <th>Stock Type</th>
                    <td>{{ ucfirst($pig->stock_type ?? 'standard') }}</td>
                    <th>Date of Birth</th>
                    <td>{{ $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Age (days)</th>
                    <td>{{ $pig->current_age_in_days }}</td>
                    <th>Latest Weight</th>
                    <td>
                        @if($pig->latestWeight)
                            {{ $pig->latestWeight->formatted_weight }} ({{ $pig->latestWeight->weigh_date?->format('Y-m-d') }})
                        @else
                            N/A
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Parents</h2>
        <table>
            <tbody>
                <tr>
                    <th>Father (Sire)</th>
                    <td>{{ $pig->father ? ($pig->father->ear_tag_number . ' • ' . $pig->father->code) : 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Mother (Dam)</th>
                    <td>{{ $pig->mother ? ($pig->mother->ear_tag_number . ' • ' . $pig->mother->code) : 'N/A' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Breeding Readiness</h2>
        <table>
            <tbody>
                <tr>
                    <th>Status</th>
                    <td>{{ $breedingReadiness['ready'] ? 'Ready for Breeding' : 'Not Ready' }}</td>
                    <th>Score</th>
                    <td>{{ $breedingReadiness['score'] }}%</td>
                </tr>
                <tr>
                    <th>Age Status</th>
                    <td>{{ $breedingReadiness['age_status'] ?? 'N/A' }}</td>
                    <th>Age (days)</th>
                    <td>{{ $breedingReadiness['age_in_days'] ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Quick Statistics</h2>
        <table>
            <tbody>
                <tr>
                    <th>Total Matings</th>
                    <td>{{ $stats['total_matings'] }}</td>
                    <th>Successful Matings</th>
                    <td>{{ $stats['successful_matings'] }}</td>
                </tr>
                <tr>
                    <th>Offspring</th>
                    <td>{{ $stats['total_offspring'] }}</td>
                    <th>Weight Records</th>
                    <td>{{ $stats['weight_records'] }}</td>
                </tr>
                <tr>
                    <th>Days Since Last Weigh</th>
                    <td>{{ $stats['days_since_last_weight'] ?? 'N/A' }}</td>
                    <th>Availability</th>
                    <td>{{ ucfirst($availabilityStatus['status'] ?? 'unknown') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($offspring->count() > 0)
        <div class="section">
            <h2>Offspring</h2>
            <table>
                <thead>
                    <tr>
                        <th>Ear Tag</th>
                        <th>Code</th>
                        <th>Gender</th>
                        <th>Birth Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($offspring as $child)
                        <tr>
                            <td>{{ $child->ear_tag_number }}</td>
                            <td>{{ $child->code }}</td>
                            <td>{{ $child->gender === 'M' ? 'Male' : 'Female' }}</td>
                            <td>{{ $child->date_of_birth?->format('Y-m-d') ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($pig->gender === 'F' && $pig->farrowingsAsSow->count() > 0)
        <div class="section">
            <h2>Farrowing (Birthing) History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Boar</th>
                        <th>Total</th>
                        <th>Live</th>
                        <th>Still</th>
                        <th>Deaths</th>
                        <th>Surviving</th>
                        <th>Status</th>
                        <th>Weaned On</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pig->farrowingsAsSow as $farrowing)
                        @php
                            $surviving = max(0, ($farrowing->live_births ?? 0) - $farrowing->pigletDeaths->sum('count'));
                            $deathCount = $farrowing->pigletDeaths->sum('count');

                            $baseStatus = $farrowing->status ?: 'n/a';
                            $weaningThreshold = (int) config('breeding.cycle.birth_to_weaning_days', 45);
                            $litterAgeDays = $farrowing->farrowing_date
                                ? (int) $farrowing->farrowing_date->diffInDays(now())
                                : null;

                            $litterStatusLabel = ucfirst($baseStatus);
                            if (
                                $surviving === 0 &&
                                ($farrowing->live_births ?? 0) > 0 &&
                                !$farrowing->date_weaned &&
                                $litterAgeDays !== null
                            ) {
                                $litterStatusLabel = $litterAgeDays < $weaningThreshold
                                    ? 'Lost (pre‑weaning)'
                                    : 'Lost (post‑weaning age)';
                            }

                            // Derive weaned-on display with same fallback logic as HTML view
                            $weanedText = '—';
                            if ($farrowing->date_weaned) {
                                $weanedText = \Illuminate\Support\Carbon::parse($farrowing->date_weaned)->format('Y-m-d');
                            } elseif (!empty($farrowing->notes)) {
                                $notesStr = (string) $farrowing->notes;
                                $pos = stripos($notesStr, 'litter weaned on');
                                if ($pos !== false) {
                                    $after = trim(substr($notesStr, $pos + strlen('litter weaned on')));
                                    $after = trim(strtok($after, "\r\n."));
                                    try {
                                        if ($after !== '') {
                                            $parsed = \Illuminate\Support\Carbon::parse($after);
                                            $weanedText = $parsed->format('Y-m-d');
                                        }
                                    } catch (\Throwable $e) {
                                        // leave as em dash if parsing fails
                                    }
                                }
                            }
                        @endphp
                        <tr>
                            <td>{{ $farrowing->farrowing_date?->format('Y-m-d') ?? 'N/A' }}</td>
                            <td>{{ $farrowing->boar?->ear_tag_number ?? 'N/A' }}</td>
                            <td>{{ $farrowing->total_piglets_born }}</td>
                            <td>{{ $farrowing->live_births }}</td>
                            <td>{{ $farrowing->stillbirths }}</td>
                            <td>{{ $deathCount }}</td>
                            <td>{{ $surviving }}</td>
                            <td>{{ $litterStatusLabel }}</td>
                            <td>{{ $weanedText }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($pig->weights->count() > 0)
        <div class="section">
            <h2>Recent Weight Records</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Weight</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pig->weights->sortByDesc('weigh_date')->take(10) as $weight)
                        <tr>
                            <td>{{ $weight->weigh_date?->format('Y-m-d') ?? 'N/A' }}</td>
                            <td>{{ $weight->formatted_weight }}</td>
                            <td>{{ $weight->notes ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        Generated automatically by Chipper Farms • {{ now()->format('Y-m-d H:i:s') }}
    </div>

</body>
</html>


