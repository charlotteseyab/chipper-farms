<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
// Import the necessary Models
use App\Models\Mating;
use App\Models\Farrowing;
// Import the correct Model for the weanings table
use App\Models\Weaning; 
use Throwable;

class PerformanceMetricController extends Controller
{
    /**
     * Main table-driven list of metrics: label, unit, excellent, average, poor
     * (Matching reference image)
     */
    protected const THRESHOLDS = [
        // 1. Pregnancy rate (%)
        'Pregnancy Rate' => [
            'unit' => '%', 'excellent' => 85, 'average' => 75
        ],
        // 2. Farrowing rate (%)
        'Farrowing Rate' => [
            'unit' => '%', 'excellent' => 85, 'average' => 70
        ],
        // 3. No. of live pigs born per litter
        'No. of Live Pigs per Litter (Gilts)' => [
            'unit' => 'no', 'excellent' => 9.5, 'average' => 7.5
        ],
        'No. of Live Pigs per Litter (Sows)' => [
            'unit' => 'no', 'excellent' => 10.5, 'average' => 8.5
        ],
        // 4. Still born/litter
        'Stillborns per Litter' => [
            'unit' => 'no', 'excellent' => 0.8, 'average' => 1.4, 'trend' => 'lowBest'
        ],
        // 5. Average birth weight
        'Average Birth Weight' => [
            'unit' => 'kg', 'excellent' => 1.4, 'average' => 1.1
        ],
        // 6. Pigs weaned/litter farrowed
        'Pigs Weaned/Litter (Gilts)' => [
            'unit' => 'no', 'excellent' => 8.0, 'average' => 6.5
        ],
        'Pigs Weaned/Litter (Sows)' => [
            'unit' => 'no', 'excellent' => 8.5, 'average' => 7.0
        ],
        // 7. Weaning percentage
        'Weaning Percentage' => [
            'unit' => '%', 'excellent' => 90, 'average' => 80
        ],
        // 8. Average weaning weight (3, 4, 5, 6 weeks)
        'Weaning Weight (3 wks)' => [ 'unit' => 'kg', 'excellent' => 5.4, 'average' => 4.0 ],
        'Weaning Weight (4 wks)' => [ 'unit' => 'kg', 'excellent' => 7.3, 'average' => 5.0 ],
        'Weaning Weight (5 wks)' => [ 'unit' => 'kg', 'excellent' => 9.3, 'average' => 7.0 ],
        'Weaning Weight (6 wks)' => [ 'unit' => 'kg', 'excellent' => 11.3, 'average' => 9.0 ],
        // 9. Average daily gain
        'Daily Gain (Birth to Market)' => [ 'unit' => 'kg', 'excellent' => 0.57, 'average' => 0.45 ],
        'Daily Gain (18kg to Market)' => [ 'unit' => 'kg', 'excellent' => 0.64, 'average' => 0.54 ],
        // 10. Age of 104kg
        // 'Age to 104kg' => [ 'unit' => 'days', 'excellent' => 182, 'average' => 227 ],
        // (Metrics 11-19 omitted for brevity; add more rows with correct units and thresholds as needed)
    ];

    /**
     * Display a listing of the performance metrics, including calculation.
     */
    public function index()
    {
        $metrics = collect([
            $this->calcPregnancyRate(),
            $this->getFarrowingRate(),
            $this->calcLiveBornPerLitterGilts(),
            $this->calcLiveBornPerLitterSows(),
            $this->calcStillbornsPerLitter(),
            $this->calcAvgBirthWeight(),
            $this->calcPigsWeanedPerLitterGilts(),
            $this->calcPigsWeanedPerLitterSows(),
            $this->calcWeaningPercentage(),
            $this->calcWeaningWeightWeeks(3),
            $this->calcWeaningWeightWeeks(4),
            $this->calcWeaningWeightWeeks(5),
            $this->calcWeaningWeightWeeks(6),
            $this->calcAvgDailyGain('birth','market'),
            $this->calcAvgDailyGain('18kg','market'),
            // $this->calcAgeTo104kg()
            // Add more here as you expand
        ]);
        $metricMap = $metrics->keyBy('measure');
        
        $thresholdRows = collect(self::THRESHOLDS)->map(function ($t, $name) use ($metricMap) {
            $excellent = $t['excellent'];
            $average = $t['average'];
            $trend = $t['trend'] ?? 'highBest'; // Default to highBest
            
            // Format numbers for display (e.g., 0.8 instead of 0.80)
            $formattedExcellent = rtrim(rtrim(number_format($excellent, 2), '0'),'.');
            $formattedAverage = rtrim(rtrim(number_format($average, 2), '0'), '.');


            if ($trend === 'lowBest') {
                // Low is Best: Stillborns (Excellent is Under 0.8, Poor is Over 1.4)
                $excellentText = 'Under ' . $formattedExcellent; // Under 0.8
                $averageText = $formattedExcellent . ' - ' . $formattedAverage; // 0.8 - 1.4
                $poorText = 'Over ' . $formattedAverage; // Over 1.4
            } else {
                // High is Best: Farrowing Rate, Daily Gain (Excellent is Over 85, Poor is Under 70)
                $excellentText = 'Over ' . $formattedExcellent;
                $averageText = $formattedAverage . ' - ' . $formattedExcellent;
                $poorText = 'Under ' . $formattedAverage;
            }


            $cell = [
                'measure' => $name,
                'unit' => $t['unit'],
                'excellent' => $excellentText,
                'average' => $averageText,
                'poor' => $poorText,
                'current_value' => $metricMap[$name]['value'] ?? 'N/A',
                'current_level' => $metricMap[$name]['level'] ?? 'N/A',
            ];
            return $cell;
        })->values();
        return view('performance_metrics.index', compact('thresholdRows'));
    }

    /**
     * Calculates the Farrowing Rate (Successful Farrowings / Total Planned Matings).
     */
    protected function getFarrowingRate(): array
    {
        $thresholds = self::THRESHOLDS['Farrowing Rate'];
        $totalMatings = Mating::count();
        $successfulFarrowings = Farrowing::where('farrowing_date', '!=', null)->count();
        $value = $totalMatings > 0 ? ($successfulFarrowings / $totalMatings) * 100 : 0;
        $value = round($value, 1);
        $level = $this->determineLevel($value, $thresholds);

        return [
            'measure' => 'Farrowing Rate',
            'unit' => $thresholds['unit'],
            'value' => $value,
            'level' => $level,
        ];
    }

    /**
     * Calculates the Pigs Weaned per Sow (Total Pigs Weaned / Total Sows Farrowed).
     *
     * @return array
     */
    protected function getPigsWeanedPerSow(): array
    {
        $thresholds = self::THRESHOLDS['Pigs Weaned per Sow'];
        
        // 1. Get total pigs weaned (LOGIC UPDATED TO USE THE 'Weaning' MODEL)
        
        // OPTION 1: If each row in the 'weanings' table represents a single weaned pig:
        $totalPigsWeaned = Weaning::count();

        // OR OPTION 2: If the 'weanings' table stores the count per litter/event 
        // in a column (e.g., 'weaned_count' or 'pigs_count'):
        // $totalPigsWeaned = Weaning::sum('pigs_count'); 
        // You MUST verify the actual column name here! We'll stick with count() for now.


        // 2. Get the divisor (Total Sows Farrowed)
        $totalSowsFarrowed = Farrowing::where('farrowing_date', '!=', null)->count();

        // 3. Calculate the metric
        $value = $totalSowsFarrowed > 0 ? ($totalPigsWeaned / $totalSowsFarrowed) : 0;
        $value = round($value, 1);

        // 4. Determine the performance level
        $level = $this->determineLevel($value, $thresholds);

        return [
            'measure' => 'Pigs Weaned per Sow',
            'unit' => $thresholds['unit'],
            'value' => $value,
            'level' => $level,
        ];
    }
    
    // Example metric: Pregnancy Rate (%)
    protected function calcPregnancyRate(): array {
        $thresholds = self::THRESHOLDS['Pregnancy Rate'];
        try {
            $eligibleMatings = \App\Models\Mating::whereNotNull('female_id')->count();
            $pregnant = \App\Models\Mating::where('pregnancy_status','pregnant')->count();
            $value = $eligibleMatings > 0 ? round(100 * $pregnant / $eligibleMatings, 1) : 0;
        } catch (\Throwable $e) { $value = 'N/A'; }
        $level = is_numeric($value) ? $this->determineLevel($value, $thresholds) : 'N/A';
        return ['measure'=>'Pregnancy Rate', 'unit'=>$thresholds['unit'], 'value'=>$value, 'level'=>$level];
    }

    // 3. No. of live pigs born per litter (Gilts)
    protected function calcLiveBornPerLitterGilts(): array {
        $thresholds = self::THRESHOLDS['No. of Live Pigs per Litter (Gilts)'];
        try {
            $gilts = \App\Models\Farrowing::whereHas('sow', function($q){ $q->where('parity','=',1); })->get();
            $count = $gilts->count();
            $avg = $count > 0 ? round($gilts->avg('live_births'), 1) : 0;
        } catch (\Throwable $e) { $avg = 'N/A'; }
        $level = is_numeric($avg) ? $this->determineLevel($avg, $thresholds) : 'N/A';
        return ['measure'=>'No. of Live Pigs per Litter (Gilts)', 'unit'=>$thresholds['unit'], 'value'=>$avg, 'level'=>$level];
    }
    // 4. No. of live pigs born per litter (Sows)
    protected function calcLiveBornPerLitterSows(): array {
        $thresholds = self::THRESHOLDS['No. of Live Pigs per Litter (Sows)'];
        try {
            $sows = \App\Models\Farrowing::whereHas('sow', function($q){ $q->where('parity','>',1); })->get();
            $count = $sows->count();
            $avg = $count > 0 ? round($sows->avg('live_births'), 1) : 0;
        } catch (\Throwable $e) { $avg = 'N/A'; }
        $level = is_numeric($avg) ? $this->determineLevel($avg, $thresholds) : 'N/A';
        return ['measure'=>'No. of Live Pigs per Litter (Sows)', 'unit'=>$thresholds['unit'], 'value'=>$avg, 'level'=>$level];
    }
    // 5. Stillborns per litter
    protected function calcStillbornsPerLitter(): array {
        $thresholds = self::THRESHOLDS['Stillborns per Litter'];
        $cnt = 'N/A';
        $level = 'N/A';
        
        try {
            $totalFarrowings = \App\Models\Farrowing::count();
    
            if ($totalFarrowings > 0) {
                // Calculate the average number of stillbirths across all farrowings, rounded to 1 decimal place.
                $avgStillbirths = \App\Models\Farrowing::avg('stillbirths');
                $cnt = round($avgStillbirths, 1);
                
                // Use the 'lowBest' flag to determine the performance level
                $level = $this->determineLevel($cnt, $thresholds, 'lowBest');
            } else {
                // If total farrowings is 0, the result is N/A.
                $cnt = 'N/A';
                $level = 'N/A';
            }
        } catch (Throwable $e) { 
            // Catch database or other exceptions and return N/A
            // You may want to log $e here for debugging
            $cnt = 'N/A'; 
            $level = 'N/A';
        }
        
        return ['measure'=>'Stillborns per Litter', 'unit'=>$thresholds['unit'], 'value'=>$cnt, 'level'=>$level];
    }
    
    // 6. Average birth weight
    protected function calcAvgBirthWeight(): array {
        $thresholds = self::THRESHOLDS['Average Birth Weight'];
        try {
            $avg = \App\Models\Farrowing::whereNotNull('average_birth_weight')->avg('average_birth_weight');
            $avg = $avg ? round($avg,2) : 0;
        } catch (\Throwable $e) { $avg = 'N/A'; }
        $level = is_numeric($avg) ? $this->determineLevel($avg, $thresholds) : 'N/A';
        return ['measure'=>'Average Birth Weight', 'unit'=>$thresholds['unit'], 'value'=>$avg, 'level'=>$level];
    }
    // 7. Pigs Weaned/Litter (Gilts)
    protected function calcPigsWeanedPerLitterGilts(): array {
        $thresholds = self::THRESHOLDS['Pigs Weaned/Litter (Gilts)'];
        try {
            $gilts = \App\Models\Farrowing::whereHas('sow', function($q){ $q->where('parity','=',1); })->get();
            $n = $gilts->count();
            $avg = $n > 0 ? round($gilts->avg('number_weaned'),1) : 0;
        } catch (\Throwable $e) { $avg = 'N/A'; }
        $level = is_numeric($avg) ? $this->determineLevel($avg, $thresholds) : 'N/A';
        return ['measure'=>'Pigs Weaned/Litter (Gilts)', 'unit'=>$thresholds['unit'], 'value'=>$avg, 'level'=>$level];
    }
    // 8. Pigs Weaned/Litter (Sows)
    protected function calcPigsWeanedPerLitterSows(): array {
        $thresholds = self::THRESHOLDS['Pigs Weaned/Litter (Sows)'];
        try {
            $sows = \App\Models\Farrowing::whereHas('sow', function($q){ $q->where('parity','>',1); })->get();
            $n = $sows->count();
            $avg = $n > 0 ? round($sows->avg('number_weaned'),1) : 0;
        } catch (\Throwable $e) { $avg = 'N/A'; }
        $level = is_numeric($avg) ? $this->determineLevel($avg, $thresholds) : 'N/A';
        return ['measure'=>'Pigs Weaned/Litter (Sows)', 'unit'=>$thresholds['unit'], 'value'=>$avg, 'level'=>$level];
    }
    // 9. Weaning Percentage
    protected function calcWeaningPercentage(): array {
        $thresholds = self::THRESHOLDS['Weaning Percentage'];
        try {
            $totalBorn = \App\Models\Farrowing::sum('live_births');
            // Weaned pigs are recorded in the Weaning table as individual records
            $totalWeaned = \App\Models\Weaning::count();
            $pct = $totalBorn > 0 ? round(100*$totalWeaned/$totalBorn,1) : 0;
        } catch (\Throwable $e) { $pct = 'N/A'; }
        $level = is_numeric($pct) ? $this->determineLevel($pct, $thresholds) : 'N/A';
        return ['measure'=>'Weaning Percentage', 'unit'=>$thresholds['unit'], 'value'=>$pct, 'level'=>$level];
    }
    // 10-13. Weaning Weight at 3-6 weeks. Data dependent on weaning weights by age.
    protected function calcWeaningWeightWeeks($week): array {
        $key = "Weaning Weight ({$week} wks)";
        $thresholds = self::THRESHOLDS[$key];
        try {
            // Avg weaning_weight for weaning_age in days ~weeks*7 (+/-2)
            $lower = $week*7 - 2; $upper = $week*7 + 2;
            $recs = \App\Models\Weaning::whereBetween('age_at_weaning_days', [$lower,$upper])->whereNotNull('weaning_weight')->get();
            $n = $recs->count();
            $avg = $n>0 ? round($recs->avg('weaning_weight'),1) : 0;
        } catch (\Throwable $e) { $avg = 'N/A'; }
        $level = is_numeric($avg) ? $this->determineLevel($avg,$thresholds) : 'N/A';
        return ['measure'=>$key, 'unit'=>$thresholds['unit'],'value'=>$avg,'level'=>$level];
    }
    // 14-15, etc. Daily Gain and Age to 104kg (example calculations)
    protected function calcAvgDailyGain($period = 'birth', $to = 'market'): array {
        $key = $period==='birth' ? 'Daily Gain (Birth to Market)' : 'Daily Gain (18kg to Market)';
        $thresholds = self::THRESHOLDS[$key];
        // Simplified for demo. True impl. would require tracking marketed pigs' weights and days.
        $value = 0; $level = 'N/A';
        try {
            // Try to find pigs with sold weights over 90kg (marketed); find birth, sale dates.
            $sales = \App\Models\PigSale::where('sale_weight','>=',90)->get();
            $gains = $sales->map(function($s) {
                $pig = $s->pig;
                if ($pig && $pig->date_of_birth && $s->sale_date && $s->sale_weight && $s->sale_weight >= 90) {
                    $days = $pig->date_of_birth->diffInDays($s->sale_date);
                    if ($days > 0) return ($s->sale_weight / $days);
                }
                return null;
            })->filter();
            $avg = $gains->count() > 0 ? round($gains->avg(),3) : 0;
            $value = $avg;
            $level = is_numeric($value) ? $this->determineLevel($value,$thresholds) : 'N/A';
        } catch (\Throwable $e) { $value = 'N/A'; $level = 'N/A'; }
        return ['measure'=>$key, 'unit'=>$thresholds['unit'],'value'=>$value,'level'=>$level];
    }
    // protected function calcAgeTo104kg(): array {
    //     $thresholds = self::THRESHOLDS['Age to 104kg'];
    //     try {
    //         $sales = \App\Models\PigSale::where('sale_weight','>=',104)->get();
    //         $days = $sales->map(function($s) {
    //             if ($s->pig && $s->pig->date_of_birth && $s->sale_date) {
    //                 return $s->pig->date_of_birth->diffInDays($s->sale_date);
    //             }
    //             return null;
    //         })->filter();
    //         $avgDays = $days->count() > 0 ? round($days->avg()) : 0;
    //     } catch (\Throwable $e){ $avgDays='N/A'; }
    //     $level = is_numeric($avgDays) ? $this->determineLevel($avgDays, $thresholds, 'lowBest') : 'N/A';
    //     return ['measure'=>'Age to 104kg', 'unit'=>$thresholds['unit'], 'value'=>$avgDays, 'level'=>$level];
    // }

    /** Override to allow low-is-best (e.g. stillborns, age-to-104kg) **/
    protected function determineLevel(float $value, array $thresholds, $trend='highBest'): string
    {
        if ($trend==='lowBest') {
             if ($value <= $thresholds['excellent']) return 'Excellent';
             if ($value <= $thresholds['average']) return 'Average';
             return 'Poor';
        } else {
             if ($value >= $thresholds['excellent']) return 'Excellent';
             if ($value >= $thresholds['average']) return 'Average';
             return 'Poor';
        }
    }
}
