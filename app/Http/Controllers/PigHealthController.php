<?php

namespace App\Http\Controllers;

use App\Models\PigManagement;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PigHealthController extends Controller
{
    public function index(Request $request)
    {
        $query = PigManagement::with(['healthRecords' => function($q) {
            $q->latest('record_date')->latest('id')->limit(1);
        }, 'medicationAdministrations' => function($q) {
            $q->latest('administration_date')->latest('id')->limit(1);
        }])->whereDoesntHave('deaths')->whereDoesntHave('sales');

        if ($search = $request->get('q')) {
            $query->where(function($q) use ($search) {
                $q->where('ear_tag_number', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('breed', 'like', "%{$search}%");
            });
        }

        // Health filters: condition, severity, date range
        $condition = $request->string('condition')->toString();
        $severity = $request->string('severity')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        if ($condition || $severity || $from || $to) {
            $query->whereHas('healthRecords', function($hr) use ($condition, $severity, $from, $to) {
                if ($condition) {
                    $hr->where('condition', 'like', "%{$condition}%");
                }
                if ($severity) {
                    $hr->where('severity', $severity);
                }
                if ($from) {
                    $hr->whereDate('record_date', '>=', $from);
                }
                if ($to) {
                    $hr->whereDate('record_date', '<=', $to);
                }
            });
        }

        $pigs = $query->orderBy('code')->paginate(15)->withQueryString();

        return view('health.index', compact('pigs'));
    }

    public function show(Request $request, PigManagement $pig)
    {
        $healthRecords = $pig->healthRecords()
            ->latest('record_date')
            ->latest('id')
            ->paginate(10, ['*'], 'health_page')
            ->withQueryString();

        $medicationRecords = $pig->medicationAdministrations()
            ->latest('administration_date')
            ->latest('id')
            ->paginate(10, ['*'], 'medication_page')
            ->withQueryString();

        $lastRecord = $pig->healthRecords()->latest('record_date')->latest('id')->first();
        $lastMedication = $pig->medicationAdministrations()->latest('administration_date')->latest('id')->first();

        return view('health.show', compact(
            'pig',
            'healthRecords',
            'medicationRecords',
            'lastRecord',
            'lastMedication'
        ));
    }

    public function updateHealthStatus(Request $request, PigManagement $pig)
    {
        $record = $pig->healthRecords()->latest('record_date')->latest('id')->first();

        if (! $record) {
            return redirect()->route('health.show', $pig)
                ->with('error', 'Add a health record before updating this pig\'s health status.');
        }

        $data = $request->validate([
            'severity' => 'nullable|in:mild,moderate,severe,low,medium,high',
            'treatment_status' => 'required|in:planned,ongoing,completed,stopped',
            'outcome' => 'nullable|in:recovered,improved,unchanged,worsened',
            'resolved_at' => 'nullable|date|after_or_equal:'.$record->record_date->toDateString(),
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $record->update($data);

        return redirect()->route('health.show', $pig)
            ->with('success', 'Health status updated successfully.');
    }

    public function exportPigHealthPdf(PigManagement $pig)
    {
        $healthRecords = $pig->healthRecords()
            ->with('recordedBy')
            ->latest('record_date')
            ->latest('id')
            ->get();
        $medicationRecords = $pig->medicationAdministrations()
            ->with('administeredBy')
            ->latest('administration_date')
            ->latest('id')
            ->get();
        $lastRecord = $healthRecords->first();
        $lastMedication = $medicationRecords->first();
        $summary = [
            'health_records' => $healthRecords->count(),
            'medications' => $medicationRecords->count(),
            'resolved' => $healthRecords->whereNotNull('resolved_at')->count(),
            'active' => $healthRecords->whereNull('resolved_at')->count(),
        ];

        $pdf = Pdf::loadView('health.pdf', compact(
            'pig',
            'healthRecords',
            'medicationRecords',
            'lastRecord',
            'lastMedication',
            'summary'
        ))->setPaper('a4', 'portrait');

        $identifier = $pig->code ?: ($pig->ear_tag_number ?: $pig->id);

        return $pdf->download('health-report-'.$identifier.'-'.now()->format('Y-m-d').'.pdf');
    }

    public function edit(PigManagement $pig)
    {
        // Load health records and medication administrations for the pig
        $pig->load(['healthRecords' => function($q) { $q->latest('record_date'); },
                    'medicationAdministrations' => function($q) { $q->latest('administration_date'); }]);
        return view('health.edit', compact('pig'));
    }

    public function createHealth(PigManagement $pig = null)
    {
        if (!$pig) {
            // If no pig is provided, get all pigs for selection
            $pigs = PigManagement::orderBy('code')->get();
            return view('health.create-health', compact('pigs'));
        }
        return view('health.create-health', compact('pig'));
    }

    public function storeHealth(Request $request, PigManagement $pig = null)
    {
        $data = $request->validate([
            'record_date' => 'required|date',
            'condition' => 'required|string|max:255',
            'severity' => 'nullable|string|max:50',
            'symptoms' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'treatment_status' => 'nullable|in:planned,ongoing,completed,stopped',
            'outcome' => 'nullable|in:recovered,improved,unchanged,worsened',
            'resolved_at' => 'nullable|date|after_or_equal:record_date',
            'resolution_notes' => 'nullable|string',
            'pig_id' => 'nullable|exists:pigs_management,id',
        ]);

        // If pig_id is provided in the form, use it; otherwise use the route parameter
        $pigId = $data['pig_id'] ?? $pig->id;
        $data['pig_id'] = $pigId;
        $data['recorded_by'] = auth()->id();
        HealthRecord::create($data);

        $selectedPig = $pig ?? PigManagement::find($pigId);
        return redirect()->route('health.show', $selectedPig)->with('success', 'Health record added');
    }

    public function update(Request $request, PigManagement $pig)
    {
        $data = $request->validate([
            'condition' => 'required|string|max:255',
            'severity' => 'nullable|string|max:50',
            'symptoms' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'treatment_status' => 'nullable|in:planned,ongoing,completed,stopped',
            'outcome' => 'nullable|in:recovered,improved,unchanged,worsened',
            'resolved_at' => 'nullable|date',
            'resolution_notes' => 'nullable|string',
        ]);

        $record = $pig->healthRecords()->latest('record_date')->first();

        if (!$record) {
            return redirect()->route('health.show', $pig)->with('error', 'No health record was found to update for this pig.');
        }

        $record->update($data);

        return redirect()->route('health.show', $pig)->with('success', 'Health record updated successfully.');
    }

    public function destroy(PigManagement $pig)
    {
        $record = $pig->healthRecords()->latest('record_date')->first();

        if (! $record) {
            return redirect()->route('health.show', $pig)->with('error', 'No health record was found to delete for this pig.');
        }

        $record->delete();

        return redirect()->route('health.show', $pig)->with('success', 'Latest health record deleted successfully.');
    }

    public function createMedication(PigManagement $pig = null)
    {
        if (!$pig) {
            // If no pig is provided, get all pigs for selection
            $pigs = PigManagement::orderBy('code')->get();
            return view('health.create-medication', compact('pigs'));
        }
        return view('health.create-medication', compact('pig'));
    }

    public function storeMedication(Request $request, PigManagement $pig = null)
    {
        $data = $request->validate([
            'administration_date' => 'required|date',
            'medication_name' => 'required|string|max:255',
            'dosage' => 'required|string|max:255',
            'purpose' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'pig_id' => 'nullable|exists:pigs_management,id',
        ]);

        // If pig_id is provided in the form, use it; otherwise use the route parameter
        $pigId = $data['pig_id'] ?? $pig->id;
        $data['pig_id'] = $pigId;
        $data['administered_by'] = auth()->id();
        MedicationAdministration::create($data);

        $selectedPig = $pig ?? PigManagement::find($pigId);
        return redirect()->route('health.show', $selectedPig)->with('success', 'Medication recorded');
    }

    public function medicationsIndex(Request $request)
    {
        $query = MedicationAdministration::with('pig');
        if ($search = $request->get('q')) {
            $query->where('medication_name', 'like', "%{$search}%");
        }
        $route = $request->string('route')->toString();
        $purpose = $request->string('purpose')->toString();
        $from = $request->date('from');
        $to = $request->date('to');
        if ($route) {
            $query->where('route', 'like', "%{$route}%");
        }
        if ($purpose) {
            $query->where('purpose', 'like', "%{$purpose}%");
        }
        if ($from) {
            $query->whereDate('administration_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('administration_date', '<=', $to);
        }
        $medications = $query->latest('administration_date')->paginate(15)->withQueryString();
        return view('health.medications-index', compact('medications'));
    }

    public function showMedication(MedicationAdministration $medication)
    {
        return view('medications.show', compact('medication'));
    }
    
    public function editMedication(MedicationAdministration $medication)
    {
        return view('medications.edit', compact('medication'));
    }
    
    public function updateMedication(Request $request, MedicationAdministration $medication)
    {
        $data = $request->validate([
            'administration_date' => 'required|date',
            'medication_name' => 'required|string|max:255',
            'dosage' => 'required|string|max:255',
            'purpose' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $medication->update($data);

        return redirect()->route('medications.show', $medication)->with('success', 'Medication record updated successfully.');
    }
    public function destroyMedication(MedicationAdministration $medication)
    {
        $medication->delete();
        return redirect()->route('medications.index')->with('success', 'Medication record deleted');
    }

    public function reports(Request $request)
    {
        $days = (int) $request->get('days', 30);
        $start = now()->subDays($days);

        $totalHealthRecords = HealthRecord::where('record_date', '>=', $start->toDateString())->count();
        $totalMedications = MedicationAdministration::where('administration_date', '>=', $start->toDateString())->count();
        $topConditions = HealthRecord::where('record_date', '>=', $start->toDateString())
            ->select('condition')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('condition')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        $recentHealthRecords = HealthRecord::with(['pig' => fn ($query) => $query->withTrashed()])
            ->where('record_date', '>=', $start->toDateString())
            ->latest('record_date')
            ->limit(10)
            ->get();

        $recentMedications = MedicationAdministration::with(['pig' => fn ($query) => $query->withTrashed()])
            ->where('administration_date', '>=', $start->toDateString())
            ->latest('administration_date')
            ->limit(10)
            ->get();

        return view('health.reports', compact('totalHealthRecords','totalMedications','topConditions','recentHealthRecords','recentMedications','days'));
    }

    // CSV Exports
    public function exportHealthCsv(Request $request)
    {
        $condition = $request->string('condition')->toString();
        $severity = $request->string('severity')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        $records = HealthRecord::with('pig')
            ->when($condition, fn($q) => $q->where('condition', 'like', "%{$condition}%"))
            ->when($severity, fn($q) => $q->where('severity', $severity))
            ->when($from, fn($q) => $q->whereDate('record_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('record_date', '<=', $to))
            ->orderByDesc('record_date')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="health-records.csv"',
        ];

        $callback = function() use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Pig', 'Date', 'Condition', 'Severity', 'Symptoms', 'Diagnosis', 'Treatment']);
            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->pig?->code ?? $r->pig?->ear_tag_number,
                    $r->record_date?->format('Y-m-d'),
                    $r->condition,
                    $r->severity,
                    $r->symptoms,
                    $r->diagnosis,
                    $r->treatment_plan,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportMedicationsCsv(Request $request)
    {
        $name = $request->string('q')->toString();
        $purpose = $request->string('purpose')->toString();
        $route = $request->string('route')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        $records = MedicationAdministration::with('pig')
            ->when($name, fn($q) => $q->where('medication_name', 'like', "%{$name}%"))
            ->when($purpose, fn($q) => $q->where('purpose', 'like', "%{$purpose}%"))
            ->when($route, fn($q) => $q->where('route', 'like', "%{$route}%"))
            ->when($from, fn($q) => $q->whereDate('administration_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('administration_date', '<=', $to))
            ->orderByDesc('administration_date')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="medications.csv"',
        ];

        $callback = function() use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Pig', 'Date', 'Medication', 'Dosage', 'Purpose', 'Route', 'Notes']);
            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->pig?->code ?? $r->pig?->ear_tag_number,
                    $r->administration_date?->format('Y-m-d'),
                    $r->medication_name,
                    $r->dosage,
                    $r->purpose,
                    $r->route,
                    $r->notes,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
