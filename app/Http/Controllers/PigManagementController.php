<?php

namespace App\Http\Controllers;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\PigSale;
use App\Models\PigDeath;
use App\Models\Mating;
use App\Models\Weaning;
use App\Models\Farrowing;
use App\Helpers\FlashHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PigManagementController extends Controller {

    // All Stock Management
    public function allIndex(Request $request) {
        $query = PigManagement::active();

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        if ($request->stock_type) {
            $query->where('stock_type', $request->stock_type);
        }

        // New: filter by presence/absence of weight records
        if ($request->has('has_weights')) {
            $hasWeights = filter_var($request->get('has_weights'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($hasWeights === true) {
                $query->whereHas('weights');
            } elseif ($hasWeights === false) {
                $query->whereDoesntHave('weights');
            }
        }

        // New: filter by created_at date range
        if ($request->created_from) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->created_to) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // New: filter by minimum age in days (date_of_birth <= today - X days)
        if ($request->min_age_days && is_numeric($request->min_age_days)) {
            $query->whereDate('date_of_birth', '<=', now()->subDays((int) $request->min_age_days));
        }

        // New: filter by recent weight activity within N days
        // Example: has_recent_weight=false&recent_weight_days=7 → pigs without weights in last 7 days
        if ($request->has('has_recent_weight') && $request->recent_weight_days) {
            $hasRecent = filter_var($request->get('has_recent_weight'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $days = (int) $request->recent_weight_days;
            if ($hasRecent === true) {
                $query->whereHas('weights', function($q) use ($days) {
                    $q->where('created_at', '>=', now()->subDays($days));
                });
            } elseif ($hasRecent === false) {
                $query->whereDoesntHave('weights', function($q) use ($days) {
                    $q->where('created_at', '>=', now()->subDays($days));
                });
            }
        }

        // New: filter by recent weigh_date activity within N days
        // Example: has_recent_weigh_date=false&recent_weigh_days=7 → pigs without weigh_date in last 7 days
        if ($request->has('has_recent_weigh_date') && $request->recent_weigh_days) {
            $hasRecent = filter_var($request->get('has_recent_weigh_date'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $days = (int) $request->recent_weigh_days;
            if ($hasRecent === true) {
                $query->whereHas('weights', function($q) use ($days) {
                    $q->where('weigh_date', '>=', now()->subDays($days));
                });
            } elseif ($hasRecent === false) {
                $query->whereDoesntHave('weights', function($q) use ($days) {
                    $q->where('weigh_date', '>=', now()->subDays($days));
                });
                // Also add age requirement: pig must be old enough to be weighed
                $query->where('date_of_birth', '<=', now()->subDays($days));
            }
        }

        // Add pagination with 10 records per page
        $pigmanagement = $query->with(['recentWeightAlerts' => function($query) {
            $query->where('acknowledged', false)
                  ->orderBy('created_at', 'desc')
                  ->limit(1);
        }, 'room.structure'])->orderBy('created_at', 'desc')->paginate(10);

        // Append query parameters to pagination links
        $pigmanagement->appends($request->query());

        return view('stocks.all.index', compact('pigmanagement'));
    }

    // Breeding Stock Management (existing functionality)
    public function index(Request $request) {
        $query = PigManagement::active()->where('stock_type', 'breeding');

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        // Add pagination with 10 records per page
        $pigmanagement = $query->orderBy('created_at', 'desc')->paginate(10);

        // Append query parameters to pagination links
        $pigmanagement->appends($request->query());

        return view('breeding_stocks.index', compact('pigmanagement'));
    }

    // Standard Stock Management
    public function standardIndex(Request $request) {
        $query = PigManagement::active()->where('stock_type', 'standard');

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        // Add pagination with 10 records per page
        $pigmanagement = $query->orderBy('created_at', 'desc')->paginate(10);

        // Append query parameters to pagination links
        $pigmanagement->appends($request->query());

        return view('stocks.standard.index', compact('pigmanagement'));
    }

    public function exportCsv(Request $request)
    {
        $query = PigManagement::active()->where('stock_type', 'breeding')->with(['latestWeight', 'sale']);

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        $pigs = $query->get();

        $response = new StreamedResponse(function() use ($pigs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Code', 'Ear Tag', 'Breed', 'Gender', 'Date of Birth', 'Age (days)',
                'Breeding Ready', 'Age Status', 'Weight Status', 'Current Weight',
                'Latest Weight Date', 'Father', 'Mother'
            ]);

            foreach ($pigs as $pig) {
                $readiness = $pig->breeding_readiness;

                fputcsv($handle, [
                    $pig->code,
                    $pig->ear_tag_number,
                    $pig->breed,
                    $pig->gender === 'M' ? 'Male' : 'Female',
                    $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : 'Not set',
                    $pig->current_age_in_days,
                    $readiness['ready'] ? 'Yes' : 'No',
                    ucfirst(str_replace('_', ' ', $readiness['age_status'])),
                    ucfirst(str_replace('_', ' ', $readiness['factors']['weight']['status'])),
                    $pig->latestWeight ? $pig->latestWeight->weight . ' kg' : 'No data',
                    $pig->latestWeight && $pig->latestWeight->weigh_date ? $pig->latestWeight->weigh_date->format('Y-m-d') : 'No data',
                    $pig->father ? $pig->father->ear_tag_number : 'N/A',
                    $pig->mother ? $pig->mother->ear_tag_number : 'N/A',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="breeding_stocks_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    public function exportPdf(Request $request)
    {
        $query = PigManagement::active()->where('stock_type', 'breeding')->with(['latestWeight', 'sale', 'father', 'mother']);

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        $pigs = $query->get();

        $pdf = Pdf::loadView('breeding_stocks.pdf', compact('pigs'));

        return $pdf->download('breeding_stocks_' . date('Y-m-d') . '.pdf');
    }

    public function create() {
        $males = PigManagement::active()->where('gender', 'M')->get();
        $females = PigManagement::active()->where('gender', 'F')->get();
        return view('breeding_stocks.create', compact('males', 'females'));
    }

    public function standardCreate() {
        $males = PigManagement::active()->where('gender', 'M')->get();
        $females = PigManagement::active()->where('gender', 'F')->get();
        return view('stocks.standard.create', compact('males', 'females'));
    }

    public function allCreate() {
        $males = PigManagement::active()->where('gender', 'M')->get();
        $females = PigManagement::active()->where('gender', 'F')->get();
        $structures = \App\Models\Structure::orderBy('name')->where('status','active')->get();
    
        return view('stocks.all.create', compact('males', 'females', 'structures'));
    }
    

    public function store(Request $request) {
        try {
            $data = $request->validate([
                'breed' => 'required|in:LW,DR,LWDR',
                'gender' => 'required|in:M,F',
                'stock_type' => 'required|in:standard,breeding',
                'date_of_birth' => 'required|date|before_or_equal:today',
                'ear_tag_number' => 'required|string|max:50|unique:pigs_management,ear_tag_number',
                'father_id' => 'nullable|exists:pigs_management,id',
                'mother_id' => 'nullable|exists:pigs_management,id',
                'room_id' => 'nullable|exists:rooms,id',
            ]);

            // under_weight alerts are treated as high priority

            // Validate room capacity if a room is chosen
            if (!empty($data['room_id'])) {
                $room = \App\Models\Room::find($data['room_id']);
                if (!$room) {
                    return back()->withErrors(['room_id' => 'Selected room not found. Please choose another room.'])->withInput();
                }
                if (!$room->canAccommodatePig()) {
                    return back()->withErrors(['room_id' => 'Selected room is full. Please choose another room.'])->withInput();
                }
            }

            // Trim and format the ear tag number
            $data['ear_tag_number'] = trim($data['ear_tag_number']);

            // Inbreeding check
            if ($this->hasInbreedingRisk($data['father_id'] ?? null, $data['mother_id'] ?? null)) {
                return back()->withErrors(['inbreeding' => 'Inbreeding risk detected.']);
            }

            // Auto-generate code in format CFPG-YM-ID (without leading zeros)
            $birthDate = \Carbon\Carbon::parse($data['date_of_birth']);
            $yearMonth = $birthDate->format('ym'); // e.g., 2501 for January 2025
            $nextId = PigManagement::max('id') + 1;
            $data['code'] = 'CFPG-' . $yearMonth . '-' . $nextId;
            $data['age_in_days'] = (int) $birthDate->diffInDays(now());

            $pig = PigManagement::create($data);

            // Redirect based on selected stock type
            if ($data['stock_type'] === 'breeding') {
                return redirect()->route('breeding-stocks.index')
                    ->with('success', 'Pig registered successfully as breeding stock.');
            } else {
                return redirect()->route('standard-stocks.index')
                    ->with('success', 'Pig registered successfully as standard stock.');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Pig creation failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create pig. Please try again.'])->withInput();
        }
    }
    
    private function hasInbreedingRisk($fatherId, $motherId): bool {
        $relationship = $this->analyzeRelationship($fatherId, $motherId);
        return $relationship['risk_level'] === 'critical';
    }

    public function checkRelationship(Request $request)
    {
        $fatherId = $request->input('father_id');
        $motherId = $request->input('mother_id');

        return response()->json($this->analyzeRelationship($fatherId, $motherId));
    }

    private function analyzeRelationship($fatherId, $motherId): array {
        // Basic validation
        if (!$fatherId || !$motherId) {
            return ['risk_level' => 'none', 'relationship' => null, 'message' => ''];
        }

        // Same pig selected twice
        if ($fatherId == $motherId) {
            return [
                'risk_level' => 'critical',
                'relationship' => 'same_pig',
                'message' => 'Cannot select the same pig as both father and mother.',
                'can_proceed' => false
            ];
        }

        $father = PigManagement::find($fatherId);
        $mother = PigManagement::find($motherId);

        if (!$father || !$mother) {
            return ['risk_level' => 'none', 'relationship' => null, 'message' => ''];
        }

        // Check for direct parent-child relationships
        if ($this->isDirectParentChild($father, $mother)) {
            return [
                'risk_level' => 'critical',
                'relationship' => 'parent_child',
                'message' => 'Direct parent-child relationship detected. This mating is not allowed.',
                'can_proceed' => false,
                'details' => $this->getParentChildDetails($father, $mother)
            ];
        }

        // Check for sibling relationships
        $siblingCheck = $this->checkSiblingRelationship($father, $mother);
        if ($siblingCheck['is_sibling']) {
            return [
                'risk_level' => $siblingCheck['type'] === 'full' ? 'warning' : 'caution',
                'relationship' => $siblingCheck['type'] . '_siblings',
                'message' => $siblingCheck['message'],
                'can_proceed' => true,
                'details' => $siblingCheck['details']
            ];
        }

        // Check for grandparent relationships
        $grandparentCheck = $this->checkGrandparentRelationship($father, $mother);
        if ($grandparentCheck['is_related']) {
            return [
                'risk_level' => 'caution',
                'relationship' => 'grandparent_grandchild',
                'message' => $grandparentCheck['message'],
                'can_proceed' => true,
                'details' => $grandparentCheck['details']
            ];
        }

        // Check for ancestor relationships (existing logic)
        if ($this->isAncestor($fatherId, $motherId) || $this->isAncestor($motherId, $fatherId)) {
            return [
                'risk_level' => 'caution',
                'relationship' => 'distant_relatives',
                'message' => 'These pigs are distantly related. Consider genetic diversity.',
                'can_proceed' => true
            ];
        }

        return [
            'risk_level' => 'none',
            'relationship' => null,
            'message' => 'No close relationships detected.',
            'can_proceed' => true
        ];
    }

    private function isDirectParentChild($pig1, $pig2): bool {
        // Check if pig1 is parent of pig2
        if ($pig2->father_id == $pig1->id || $pig2->mother_id == $pig1->id) {
            return true;
        }
        // Check if pig2 is parent of pig1
        if ($pig1->father_id == $pig2->id || $pig1->mother_id == $pig2->id) {
            return true;
        }
        return false;
    }

    private function getParentChildDetails($pig1, $pig2): array {
        if ($pig2->father_id == $pig1->id) {
            return ['relationship' => $pig1->ear_tag_number . ' is the father of ' . $pig2->ear_tag_number];
        }
        if ($pig2->mother_id == $pig1->id) {
            return ['relationship' => $pig1->ear_tag_number . ' is the mother of ' . $pig2->ear_tag_number];
        }
        if ($pig1->father_id == $pig2->id) {
            return ['relationship' => $pig2->ear_tag_number . ' is the father of ' . $pig1->ear_tag_number];
        }
        if ($pig1->mother_id == $pig2->id) {
            return ['relationship' => $pig2->ear_tag_number . ' is the mother of ' . $pig1->ear_tag_number];
        }
        return [];
    }

    private function checkSiblingRelationship($pig1, $pig2): array {
        $sharedFather = $pig1->father_id && $pig2->father_id && $pig1->father_id == $pig2->father_id;
        $sharedMother = $pig1->mother_id && $pig2->mother_id && $pig1->mother_id == $pig2->mother_id;

        if ($sharedFather && $sharedMother) {
            return [
                'is_sibling' => true,
                'type' => 'full',
                'message' => 'These pigs are full siblings (same parents). High inbreeding risk.',
                'details' => [
                    'shared_father' => $pig1->father ? $pig1->father->ear_tag_number : 'Unknown',
                    'shared_mother' => $pig1->mother ? $pig1->mother->ear_tag_number : 'Unknown'
                ]
            ];
        }

        if ($sharedFather || $sharedMother) {
            $sharedParent = $sharedFather ? 'father' : 'mother';
            $parentTag = $sharedFather ?
                ($pig1->father ? $pig1->father->ear_tag_number : 'Unknown') :
                ($pig1->mother ? $pig1->mother->ear_tag_number : 'Unknown');

            return [
                'is_sibling' => true,
                'type' => 'half',
                'message' => "These pigs are half-siblings (shared $sharedParent). Moderate inbreeding risk.",
                'details' => [
                    'shared_parent' => $sharedParent,
                    'parent_tag' => $parentTag
                ]
            ];
        }

        return ['is_sibling' => false];
    }

    private function checkGrandparentRelationship($pig1, $pig2): array {
        // Check if pig1 is grandparent of pig2
        if ($this->isGrandparent($pig1->id, $pig2)) {
            return [
                'is_related' => true,
                'message' => $pig1->ear_tag_number . ' is a grandparent of ' . $pig2->ear_tag_number . '. Moderate inbreeding risk.',
                'details' => ['type' => 'grandparent_grandchild']
            ];
        }

        // Check if pig2 is grandparent of pig1
        if ($this->isGrandparent($pig2->id, $pig1)) {
            return [
                'is_related' => true,
                'message' => $pig2->ear_tag_number . ' is a grandparent of ' . $pig1->ear_tag_number . '. Moderate inbreeding risk.',
                'details' => ['type' => 'grandparent_grandchild']
            ];
        }

        return ['is_related' => false];
    }

    private function isGrandparent($grandparentId, $pig): bool {
        if (!$pig->father_id && !$pig->mother_id) return false;

        // Check paternal grandparents
        if ($pig->father_id) {
            $father = PigManagement::find($pig->father_id);
            if ($father && ($father->father_id == $grandparentId || $father->mother_id == $grandparentId)) {
                return true;
            }
        }

        // Check maternal grandparents
        if ($pig->mother_id) {
            $mother = PigManagement::find($pig->mother_id);
            if ($mother && ($mother->father_id == $grandparentId || $mother->mother_id == $grandparentId)) {
                return true;
            }
        }

        return false;
    }

    private function isAncestor($ancestorId, $pigId, $depth = 0): bool {
        if (!$ancestorId || !$pigId || $depth > 3) return false; // Limit depth to prevent infinite recursion
        $pig = PigManagement::find($pigId);
        if (!$pig) return false;
        if ($pig->father_id === $ancestorId || $pig->mother_id === $ancestorId) return true;
        return $this->isAncestor($ancestorId, $pig->father_id, $depth + 1) ||
               $this->isAncestor($ancestorId, $pig->mother_id, $depth + 1);
    }

    public function destroy($id)
    {
        $pig = PigManagement::findOrFail($id);
        $pig->delete();
        
        // Redirect back to the previous page if available, otherwise to breeding stocks
        if (request()->headers->get('referer')) {
            return redirect()->back()->with('success', 'Pig deleted successfully.');
        }
        
        return redirect()->route('breeding-stocks.index')->with('success', 'Pig deleted successfully.');
    }

    public function edit($id)
    {
        $pig = PigManagement::findOrFail($id);
        $males = PigManagement::where('gender', 'M')->get();
        $females = PigManagement::where('gender', 'F')->get();
        $rooms = \App\Models\Room::with('structure')->orderBy('name')->get();
        return view('breeding_stocks.edit', [
            'breedingStock' => $pig,
            'males' => $males,
            'females' => $females,
            'rooms' => $rooms,
        ]);
    }

    public function update(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);

        $data = $request->validate([
            'breed' => 'required|in:LW,DR,LWDR',
            'gender' => 'required|in:M,F',
            'stock_type' => 'required|in:breeding,standard',
            'date_of_birth' => 'required|date|before_or_equal:today',
            'ear_tag_number' => 'required|string|max:50|unique:pigs_management,ear_tag_number,' . $pig->id,
            'father_id' => 'nullable|exists:pigs_management,id',
            'mother_id' => 'nullable|exists:pigs_management,id',
            'room_id' => 'nullable|exists:rooms,id',
        ]);

        // Inbreeding check - exclude current pig from ancestry check
        if ($this->hasInbreedingRiskForUpdate($data['father_id'] ?? null, $data['mother_id'] ?? null, $pig->id)) {
            return back()->withErrors(['inbreeding' => 'Inbreeding risk detected.']);
        }

        // Validate room capacity if changing/setting room
        if (!empty($data['room_id'])) {
            $room = \App\Models\Room::find($data['room_id']);
            if (!$room) {
                return back()->withErrors(['room_id' => 'Selected room not found or inactive.'])->withInput();
            }
            
            // Check if room can accommodate the pig, excluding the current pig if it's already in this room
            if (!$room->canAccommodatePig($pig->id)) {
                return back()->withErrors(['room_id' => 'Selected room is full. Please choose another room.'])->withInput();
            }
        }

        // Trim and format the ear tag number
        $data['ear_tag_number'] = trim($data['ear_tag_number']);

        // Recalculate age if date of birth changed
        if ($pig->date_of_birth->format('Y-m-d') !== $data['date_of_birth']) {
            $data['age_in_days'] = (int) \Carbon\Carbon::parse($data['date_of_birth'])->diffInDays(now());
        }

        $pig->update($data);

        // Redirect based on stock type
        if ($data['stock_type'] === 'breeding') {
            return redirect()->route('breeding-stocks.index')->with('success', 'Breeding stock updated successfully.');
        } else {
            return redirect()->route('standard-stocks.index')->with('success', 'Standard stock updated successfully.');
        }
    }

    private function hasInbreedingRiskForUpdate($fatherId, $motherId, $currentPigId): bool {
        // Don't check inbreeding if either parent is the current pig being updated
        if ($fatherId == $currentPigId || $motherId == $currentPigId) {
            return false;
        }

        return $this->isAncestor($fatherId, $motherId) || $this->isAncestor($motherId, $fatherId);
    }

    // All Stock Export Methods
    public function allExportCsv(Request $request)
    {
        $query = PigManagement::active()->with(['latestWeight', 'sale']);

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        if ($request->stock_type) {
            $query->where('stock_type', $request->stock_type);
        }

        $pigs = $query->get();

        $response = new StreamedResponse(function() use ($pigs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Code', 'Ear Tag', 'Stock Type', 'Breed', 'Gender', 'Date of Birth', 'Age (days)',
                'Breeding Ready', 'Age Status', 'Weight Status', 'Current Weight',
                'Sale Status', 'Sale Price', 'Father', 'Mother'
            ]);

            foreach ($pigs as $pig) {
                $readiness = $pig->breeding_readiness;
                $saleStatus = $pig->sale_status;

                fputcsv($handle, [
                    $pig->code,
                    $pig->ear_tag_number,
                    ucfirst($pig->stock_type),
                    $pig->breed,
                    $pig->gender === 'M' ? 'Male' : 'Female',
                    $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : 'Not set',
                    $pig->current_age_in_days,
                    $readiness['ready'] ? 'Yes' : 'No',
                    ucfirst(str_replace('_', ' ', $readiness['age_status'])),
                    ucfirst(str_replace('_', ' ', $readiness['factors']['weight']['status'])),
                    $pig->latestWeight ? $pig->latestWeight->weight . ' kg' : 'No data',
                    $saleStatus['sold'] ? 'Sold' : 'Available',
                    $saleStatus['sold'] && $pig->sale ? $pig->sale->formatted_sale_price : 'N/A',
                    $pig->father ? $pig->father->ear_tag_number : 'N/A',
                    $pig->mother ? $pig->mother->ear_tag_number : 'N/A',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="all_stocks_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    public function allExportPdf(Request $request)
    {
        $query = PigManagement::active()->with(['latestWeight', 'sale', 'father', 'mother']);

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        if ($request->stock_type) {
            $query->where('stock_type', $request->stock_type);
        }

        $pigs = $query->get();

        $pdf = Pdf::loadView('stocks.all.pdf', compact('pigs'));

        return $pdf->download('all_stocks_' . date('Y-m-d') . '.pdf');
    }

    // Standard Stock Export Methods
    public function standardExportCsv(Request $request)
    {
        $query = PigManagement::active()->where('stock_type', 'standard')->with(['latestWeight', 'sale']);

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        $pigs = $query->get();

        $response = new StreamedResponse(function() use ($pigs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Code', 'Ear Tag', 'Breed', 'Gender', 'Date of Birth', 'Age (days)',
                'Breeding Ready', 'Age Status', 'Weight Status', 'Current Weight',
                'Sale Status', 'Sale Price', 'Sale Date', 'Buyer', 'Father', 'Mother'
            ]);

            foreach ($pigs as $pig) {
                $readiness = $pig->breeding_readiness;
                $saleStatus = $pig->sale_status;

                fputcsv($handle, [
                    $pig->code,
                    $pig->ear_tag_number,
                    $pig->breed,
                    $pig->gender === 'M' ? 'Male' : 'Female',
                    $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : 'Not set',
                    $pig->current_age_in_days,
                    $readiness['ready'] ? 'Yes' : 'No',
                    ucfirst(str_replace('_', ' ', $readiness['age_status'])),
                    ucfirst(str_replace('_', ' ', $readiness['factors']['weight']['status'])),
                    $pig->latestWeight ? $pig->latestWeight->weight . ' kg' : 'No data',
                    $saleStatus['sold'] ? 'Sold' : 'Available',
                    $saleStatus['sold'] && $pig->sale ? $pig->sale->formatted_sale_price : 'N/A',
                    $saleStatus['sold'] && $pig->sale ? $pig->sale->sale_date->format('Y-m-d') : 'N/A',
                    $saleStatus['sold'] && $pig->sale ? $pig->sale->buyer_name : 'N/A',
                    $pig->father ? $pig->father->ear_tag_number : 'N/A',
                    $pig->mother ? $pig->mother->ear_tag_number : 'N/A',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="standard_stocks_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    public function standardExportPdf(Request $request)
    {
        $query = PigManagement::active()->where('stock_type', 'standard')->with(['latestWeight', 'sale', 'father', 'mother']);

        if ($request->breed) {
            $query->where('breed', $request->breed);
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        if ($request->age) {
            $query->where('age_in_days', '<=', $request->age);
        }

        $pigs = $query->get();

        $pdf = Pdf::loadView('stocks.standard.pdf', compact('pigs'));

        return $pdf->download('standard_stocks_' . date('Y-m-d') . '.pdf');
    }

    // Mating Planner Methods
    public function matingIndex()
    {
        // Get all pigs first
        $allMales = PigManagement::active()->where('gender', 'M')->get();
        $allFemales = PigManagement::active()->where('gender', 'F')->get();

        // Filter available pigs using the same logic as dropdown (availability_status)
        $availableMales = $allMales->filter(function($pig) {
            return $pig->availability_status['available'];
        });

        $availableFemales = $allFemales->filter(function($pig) {
            return $pig->availability_status['available'];
        });

        $males = $availableMales->map(function($pig) {
            $readiness = $pig->breeding_readiness;
            return [
                'id' => $pig->id,
                'code' => $pig->code,
                'ear_tag_number' => $pig->ear_tag_number,
                'breed' => $pig->breed,
                'stock_type' => $pig->stock_type,
                'current_age_in_days' => $pig->current_age_in_days,
                'breeding_readiness' => $readiness,
                'readiness_message' => $pig->breeding_readiness_message,
                'readiness_badge' => $pig->breeding_readiness_badge
            ];
        })->values();

        $females = $availableFemales->map(function($pig) {
            $readiness = $pig->breeding_readiness;
            return [
                'id' => $pig->id,
                'code' => $pig->code,
                'ear_tag_number' => $pig->ear_tag_number,
                'breed' => $pig->breed,
                'stock_type' => $pig->stock_type,
                'current_age_in_days' => $pig->current_age_in_days,
                'breeding_readiness' => $readiness,
                'readiness_message' => $pig->breeding_readiness_message,
                'readiness_badge' => $pig->breeding_readiness_badge
            ];
        })->values();

        return view('mating.index', compact('males', 'females', 'allMales', 'allFemales', 'availableMales', 'availableFemales'));
    }

    public function planMating(Request $request)
    {
        $data = $request->validate([
            'male_id' => 'required|exists:pigs_management,id',
            'female_id' => 'required|exists:pigs_management,id',
            'planned_date' => 'required|date',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Check breeding readiness
        $male = PigManagement::findOrFail($data['male_id']);
        $female = PigManagement::findOrFail($data['female_id']);

        $maleReadiness = $male->breeding_readiness;
        $femaleReadiness = $female->breeding_readiness;

        // Only block for age issues, not weight issues
        if ($maleReadiness['age_status'] === 'too_young' || $maleReadiness['age_status'] === 'too_old') {
            return back()->withErrors(['mating' => "Male {$male->code} is not ready for breeding: {$maleReadiness['age_message']}"]);
        }

        if ($femaleReadiness['age_status'] === 'too_young' || $femaleReadiness['age_status'] === 'too_old') {
            return back()->withErrors(['mating' => "Female {$female->code} is not ready for breeding: {$femaleReadiness['age_message']}"]);
        }

        // Collect weight warnings for later display
        $warnings = [];
        if ($maleReadiness['factors']['weight']['status'] === 'too_light') {
            $warnings[] = "Male {$male->code}: {$maleReadiness['factors']['weight']['message']}";
        }
        if ($femaleReadiness['factors']['weight']['status'] === 'too_light') {
            $warnings[] = "Female {$female->code}: {$femaleReadiness['factors']['weight']['message']}";
        }

        // Check relationship before planning
        $relationship = $this->analyzeRelationship($data['male_id'], $data['female_id']);

        if ($relationship['risk_level'] === 'critical') {
            return back()->withErrors(['mating' => $relationship['message']]);
        }

        // Check for duplicate planned matings
        $existingMating = Mating::where('male_id', $data['male_id'])
            ->where('female_id', $data['female_id'])
            ->where('status', 'planned')
            ->first();

        if ($existingMating) {
            return back()->withErrors(['mating' => 'A mating between these pigs is already planned for ' . $existingMating->planned_date->format('M d, Y')]);
        }

        // Create the mating record
        $mating = Mating::create([
            'male_id' => $data['male_id'],
            'female_id' => $data['female_id'],
            'planned_date' => $data['planned_date'],
            'notes' => $data['notes'],
            'status' => 'planned',
            'created_by' => auth()->id()
        ]);

        $male = PigManagement::find($data['male_id']);
        $female = PigManagement::find($data['female_id']);

        $successMessage = "Mating successfully planned for {$male->code} × {$female->code} on " .
                         Carbon::parse($data['planned_date'])->format('M d, Y');

        // Add relationship warnings
        if ($relationship['risk_level'] !== 'none') {
            $successMessage .= ' ⚠️ Note: ' . $relationship['message'];
        }

        // Add weight warnings
        if (!empty($warnings)) {
            $successMessage .= ' ⚖️ Weight Warning: ' . implode('; ', $warnings);
        }

        return back()->with('success', $successMessage);
    }

    public function matingManage()
    {
        $matings = Mating::with(['male', 'female', 'createdBy', 'checkedBy'])
            ->orderBy('planned_date', 'desc')
            ->paginate(10);

        $stats = [
            'total' => Mating::count(),
            'planned' => Mating::planned()->count(),
            'completed' => Mating::completed()->count(),
            'cancelled' => Mating::where('status', 'cancelled')->count(),
            'overdue' => Mating::overdue()->count(),
            'upcoming' => Mating::upcoming()->count(),
            'pregnancy_checks_due' => Mating::pregnancyCheckDue()->count(),
            'pregnancy_checks_overdue' => Mating::pregnancyCheckOverdue()->count(),
            'pregnant' => Mating::pregnant()->count(),
            'not_pregnant' => Mating::notPregnant()->count()
        ];

        return view('mating.manage', compact('matings', 'stats'));
    }

    public function recordPregnancyCheck(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);

        $data = $request->validate([
            'pregnancy_status' => 'required|in:pregnant,not_pregnant,uncertain',
            'pregnancy_check_method' => 'required|in:physical_exam,blood_test,ultrasound,behavioral_signs,other',
            'pregnancy_confidence' => 'required|in:high,medium,low',
            'pregnancy_check_date' => 'required|date|before_or_equal:today',
            'pregnancy_check_notes' => 'nullable|string|max:1000'
        ]);

        $success = $mating->recordPregnancyCheck(
            $data['pregnancy_status'],
            $data['pregnancy_check_method'],
            $data['pregnancy_confidence'],
            $data['pregnancy_check_notes'] ?? null,
            $data['pregnancy_check_date'],
            auth()->id()
        );

        if ($success) {
            $statusMessage = match($data['pregnancy_status']) {
                'pregnant' => '🤰 Pregnancy confirmed! Expected farrowing: ' . $mating->fresh()->expected_farrowing_date->format('M d, Y'),
                'not_pregnant' => '❌ Not pregnant. Consider re-mating or health check.',
                'uncertain' => '❓ Pregnancy uncertain. Consider follow-up check in a few days.',
            };

            return back()->with('success', 'Pregnancy check recorded successfully. ' . $statusMessage);
        }

        return back()->withErrors(['pregnancy' => 'Failed to record pregnancy check.']);
    }

    public function completeMating(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);

        $data = $request->validate([
            'actual_date' => 'required|date|before_or_equal:today',
            'outcome_notes' => 'nullable|string|max:1000'
        ]);

        $success = $mating->markAsCompleted(
            auth()->id(),
            $data['actual_date'],
            $data['outcome_notes'] ?? null
        );

        if ($success) {
            $male = $mating->male;
            $female = $mating->female;
            $pregnancyCheckDate = \Carbon\Carbon::parse($data['actual_date'])->addDays(14);

            return back()->with('success',
                "Mating completed for {$male->code} × {$female->code}. " .
                "Pregnancy check recommended on {$pregnancyCheckDate->format('M d, Y')} (14 days from mating)."
            );
        }

        return back()->withErrors(['mating' => 'Failed to complete mating.']);
    }

    public function cancelMating(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);

        // Only allow cancellation of planned matings
        if ($mating->status !== 'planned') {
            return back()->withErrors(['mating' => 'Only planned matings can be cancelled.']);
        }

        // Get pig information before cancellation for success message
        $male = $mating->male;
        $female = $mating->female;
        $plannedDate = $mating->planned_date->format('M d, Y');

        // Mark the mating as cancelled instead of deleting
        $success = $mating->markAsCancelled('Cancelled by user');

        if ($success) {
            return back()->with('success', 
                "Mating cancelled for {$male->code} × {$female->code} planned for {$plannedDate}. " .
                "Both pigs are now available for breeding."
            );
        }

        return back()->withErrors(['mating' => 'Failed to cancel mating.']);
    }

    // Gestation Period Management Methods
    public function gestationIndex()
    {
        $pregnantMatings = Mating::with(['male', 'female', 'createdBy', 'checkedBy'])
            ->whereIn('pregnancy_status', ['pregnant', 'farrowed'])
            ->orderByRaw("CASE WHEN pregnancy_status = 'pregnant' THEN 0 ELSE 1 END")
            ->orderBy('expected_farrowing_date', 'asc')
            ->paginate(10);

        $stats = [
            'total_pregnant' => Mating::where('pregnancy_status', 'pregnant')->count(),
            'total_farrowed' => Mating::where('pregnancy_status', 'farrowed')->count(),
            'due_this_week' => Mating::where('pregnancy_status', 'pregnant')
                ->whereBetween('expected_farrowing_date', [now(), now()->addWeek()])
                ->count(),
            'due_next_week' => Mating::where('pregnancy_status', 'pregnant')
                ->whereBetween('expected_farrowing_date', [now()->addWeek(), now()->addWeeks(2)])
                ->count(),
            'overdue' => Mating::where('pregnancy_status', 'pregnant')
                ->where('expected_farrowing_date', '<', now())
                ->count(),
            'farrowed_this_month' => Mating::where('pregnancy_status', 'farrowed')
                ->whereMonth('actual_farrowing_date', now()->month)
                ->whereYear('actual_farrowing_date', now()->year)
                ->count(),
            'average_gestation' => Mating::where('pregnancy_status', 'pregnant')
                ->selectRaw('AVG(DATEDIFF(expected_farrowing_date, actual_date)) as avg_days')
                ->value('avg_days') ?? config('breeding.cycle.gestation_days'),
        ];

        return view('gestation.index', compact('pregnantMatings', 'stats'));
    }

    public function recordFarrowing(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);

        $data = $request->validate([
            'farrowing_date' => 'required|date|before_or_equal:today',
            'farrowing_time' => 'nullable|date_format:H:i',
            'total_piglets_born' => 'required|integer|min:0|max:20',
            'live_births' => 'required|integer|min:0|max:20',
            'stillbirths' => 'required|integer|min:0|max:20',
            'number_of_males' => 'nullable|integer|min:0|max:20',
            'number_of_females' => 'nullable|integer|min:0|max:20',
            'farrowing_type' => 'required|in:natural,assisted,cesarean',
            'farrowing_difficulty' => 'required|in:easy,moderate,difficult,complicated',
            'average_birth_weight' => 'nullable|numeric|min:0|max:5',
            'sow_condition' => 'required|in:excellent,good,fair,poor',
            'complications' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000'
        ]);



        // Ensure all values are integers for comparison
        $totalPiglets = (int) $data['total_piglets_born'];
        $liveBirths = (int) $data['live_births'];
        $stillbirths = (int) $data['stillbirths'];
        $males = (int) $data['number_of_males'];
        $females = (int) $data['number_of_females'];

        // Validate that live + stillborn = total (using integer comparison)
        $calculatedTotal = $liveBirths + $stillbirths;
        if ($calculatedTotal !== $totalPiglets) {

            return back()->withErrors([
                'piglets' => "Live births ({$liveBirths}) + stillbirths ({$stillbirths}) = {$calculatedTotal} must equal total piglets born ({$totalPiglets})."
            ])->withInput();
        }

        // Validate that gender distribution matches live births (only if both are provided and not empty)
        // Skip validation if both values are 0 (likely means fields were left empty)
        if ($males > 0 || $females > 0) {
            // At least one field has a non-zero value, validate the math
            $genderTotal = $males + $females;
            if ($genderTotal !== $liveBirths) {
                return back()->withErrors([
                    'gender' => "Number of males ({$males}) + females ({$females}) = {$genderTotal} must equal live births ({$liveBirths})."
                ])->withInput();
            }
        }

        // Calculate gestation length
        $gestationLength = null;
        if ($mating->mating_date) {
            $gestationLength = \Carbon\Carbon::parse($mating->mating_date)->diffInDays($data['farrowing_date']);
        }

        // Create farrowing record
        $farrowing = Farrowing::create([
            'sow_id' => $mating->female_id,
            'boar_id' => $mating->male_id,
            'farrowing_date' => $data['farrowing_date'],
            'farrowing_time' => $data['farrowing_time'],
            'total_piglets_born' => $data['total_piglets_born'],
            'live_births' => $data['live_births'],
            'stillbirths' => $data['stillbirths'],
            'number_of_males' => $data['number_of_males'] ?: 0,
            'number_of_females' => $data['number_of_females'] ?: 0,
            'average_birth_weight' => $data['average_birth_weight'],
            'farrowing_type' => $data['farrowing_type'],
            'farrowing_difficulty' => $data['farrowing_difficulty'],
            'gestation_length_days' => $gestationLength,
            'complications' => $data['complications'],
            'notes' => $data['notes'],
            'sow_condition' => $data['sow_condition'],
            'recorded_by' => auth()->id(),
            'status' => 'active'
        ]);

        // Update mating record (keep for backward compatibility)
        $mating->update([
            'actual_farrowing_date' => $data['farrowing_date'],
            'total_piglets' => $data['total_piglets_born'],
            'live_piglets' => $data['live_births'],
            'stillborn_piglets' => $data['stillbirths'],
            'farrowing_notes' => $data['notes'],
            'pregnancy_status' => 'farrowed', // Mark as farrowed to indicate completion
            'status' => 'farrowed' // Mark mating status as farrowed
        ]);

        if ($farrowing) {
            $female = $mating->female;
            $weaningDate = \Carbon\Carbon::parse($data['farrowing_date'])->addDays(config('breeding.cycle.birth_to_weaning_days'));

            return back()->with('success',
                "Farrowing recorded for {$female->code}! " .
                "{$data['live_births']} live piglets born ({$data['number_of_males']} males, {$data['number_of_females']} females). " .
                "Expected weaning date: {$weaningDate->format('M d, Y')}"
            );
        }

        return back()->withErrors(['farrowing' => 'Failed to record farrowing.']);
    }

    public function updateGestationStatus(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:pregnant,lost_pregnancy,aborted',
            'status_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000'
        ]);

        $success = $mating->update([
            'pregnancy_status' => $data['status'],
            'pregnancy_status_date' => $data['status_date'],
            'pregnancy_status_notes' => $data['notes'] ?? null
        ]);

        if ($success) {
            $female = $mating->female;
            $statusMessage = match($data['status']) {
                'lost_pregnancy' => "Pregnancy loss recorded for {$female->code}. Sow will become available for re-breeding.",
                'aborted' => "Abortion recorded for {$female->code}. Sow will become available for re-breeding.",
                'pregnant' => "Pregnancy status updated for {$female->code}.",
            };

            return back()->with('success', $statusMessage);
        }

        return back()->withErrors(['status' => 'Failed to update gestation status.']);
    }

    public function editGestation(Mating $mating)
    {
        $mating->load(['male', 'female', 'createdBy', 'checkedBy']);
        
        return view('gestation.edit', compact('mating'));
    }

    public function updateGestation(Request $request, Mating $mating)
    {
        $data = $request->validate([
            'actual_date' => 'required|date|before_or_equal:today',
            'expected_farrowing_date' => 'required|date|after:actual_date',
            'pregnancy_status' => 'required|in:pregnant,not_pregnant,uncertain,pending,lost_pregnancy,aborted,farrowed',
            'pregnancy_check_date' => 'nullable|date|before_or_equal:today',
            'pregnancy_check_method' => 'nullable|in:ultrasound,manual,blood_test,other',
            'pregnancy_check_notes' => 'nullable|string|max:1000',
            'pregnancy_confidence' => 'nullable|in:high,medium,low',
            'pregnancy_status_date' => 'nullable|date|before_or_equal:today',
            'pregnancy_status_notes' => 'nullable|string|max:1000',
            'expected_piglets' => 'nullable|integer|min:1|max:25',
            'notes' => 'nullable|string|max:1000',
            'outcome_notes' => 'nullable|string|max:1000',
        ]);

        // Update the mating record
        $mating->update($data);

        return redirect()->route('gestation.index')
            ->with('success', 'Gestation record updated successfully!');
    }

    // Weaning Period Management Methods

    private function getWeaningAlerts()
    {
        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
        $today = now()->startOfDay();

        // Calculate target weaning dates
        $alerts = [
            'upcoming' => [], // 2 days before weaning
            'today' => [],    // Due today
            'overdue' => []   // 2+ days overdue
        ];

        // Get pigs that haven't been weaned yet
        $unweaned = PigManagement::active()->whereNotNull('date_of_birth')
            ->whereDoesntHave('weaning')
            ->with(['mother', 'father'])
            ->get();

        foreach ($unweaned as $pig) {
            $birthDate = $pig->date_of_birth;
            $targetWeaningDate = $birthDate->copy()->addDays($weaningAge);
            $daysUntilWeaning = $today->diffInDays($targetWeaningDate, false);

            // 2 days before weaning (upcoming alert)
            if ($daysUntilWeaning == 2) {
                $alerts['upcoming'][] = [
                    'pig' => $pig,
                    'target_date' => $targetWeaningDate,
                    'days_until' => 2,
                    'message' => "Weaning due in 2 days"
                ];
            }
            // Due today or 1 day before
            elseif ($daysUntilWeaning <= 1 && $daysUntilWeaning >= 0) {
                $alerts['today'][] = [
                    'pig' => $pig,
                    'target_date' => $targetWeaningDate,
                    'days_until' => $daysUntilWeaning,
                    'message' => $daysUntilWeaning == 0 ? "Weaning due today" : "Weaning due tomorrow"
                ];
            }
            // Overdue (2+ days past due date)
            elseif ($daysUntilWeaning <= -2) {
                $daysOverdue = abs($daysUntilWeaning);
                $alerts['overdue'][] = [
                    'pig' => $pig,
                    'target_date' => $targetWeaningDate,
                    'days_overdue' => $daysOverdue,
                    'message' => "Weaning overdue by {$daysOverdue} days"
                ];
            }
        }

        return $alerts;
    }

    // Weaning Status Pages
    public function weaningPending()
    {
        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

        // Get pigs that are not yet ready for weaning OR ready but not overdue
        // This includes pigs from 0 days up to 6 days past weaning age (before they become overdue)
        $pendingPigs = PigManagement::active()->whereNotNull('date_of_birth')
            ->whereRaw('DATEDIFF(NOW(), date_of_birth) < ?', [$weaningAge + 7]) // Under 52 days (not overdue)
            ->whereDoesntHave('weaning')
            ->with(['mother', 'father'])
            ->orderBy('date_of_birth', 'desc')
            ->paginate(10);

        $stats = [
            'total_pending' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) < ?', [$weaningAge + 7])
                ->whereDoesntHave('weaning')
                ->count(),
            'ready_soon' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) BETWEEN ? AND ?', [$weaningAge - 7, $weaningAge - 1])
                ->whereDoesntHave('weaning')
                ->count(),
            'ready_for_weaning' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$weaningAge])
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) < ?', [$weaningAge + 7])
                ->whereDoesntHave('weaning')
                ->count(),
        ];

        return view('weaning.pending', compact('pendingPigs', 'stats'));
    }

    public function weaningInProgress()
    {
        $maturityAge = config('breeding.cycle.birth_to_maturity_days', 180);

        // Get pigs that have been weaned but haven't completed maturity yet
        // Show ALL weaned pigs regardless of age until they're manually completed
        $inProgressPigs = PigManagement::active()->whereNotNull('date_of_birth')
            ->whereHas('weaning', function($query) {
                $query->where('maturity_completed', false); // Not yet completed
            })
            ->with(['mother', 'father', 'weaning'])
            ->orderBy('date_of_birth', 'asc')
            ->paginate(10);

        // Calculate additional data for each pig
        $inProgressPigs->getCollection()->transform(function ($pig) use ($maturityAge) {
            if ($pig->weaning) {
                $pig->days_since_weaning = (int) $pig->weaning->weaning_date->diffInDays(now());
                $pig->days_until_mature = $maturityAge - (int) $pig->date_of_birth->diffInDays(now());
                $pig->progress_percentage = round((($pig->date_of_birth->diffInDays(now()) - 45) / ($maturityAge - 45)) * 100, 1);
                $pig->expected_maturity_date = $pig->date_of_birth->copy()->addDays($maturityAge);
            }
            return $pig;
        });

        $stats = [
            'total_in_progress' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereHas('weaning', function($query) {
                    $query->where('maturity_completed', false);
                })
                ->count(),
            'recently_weaned' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereHas('weaning', function($query) {
                    $query->where('weaning_date', '>=', now()->subDays(7))
                          ->where('maturity_completed', false);
                })
                ->count(),
            'near_maturity' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) BETWEEN ? AND ?', [$maturityAge - 14, $maturityAge - 1])
                ->whereHas('weaning', function($query) {
                    $query->where('maturity_completed', false);
                })
                ->count(),
            'ready_for_completion' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$maturityAge])
                ->whereHas('weaning', function($query) {
                    $query->where('maturity_completed', false);
                })
                ->count(),
        ];

        return view('weaning.in-progress', compact('inProgressPigs', 'stats'));
    }

    public function weaningWeaned()
    {
        // Show only pigs that have completed the full weaning-to-maturity journey
        $completedPigs = Weaning::with(['pig', 'mother', 'recordedBy', 'completedBy'])
            ->where('maturity_completed', true)
            ->orderBy('maturity_completion_date', 'desc')
            ->paginate(10);

        $stats = [
            'total_completed' => Weaning::where('maturity_completed', true)->count(),
            'completed_this_month' => Weaning::where('maturity_completed', true)
                ->whereMonth('maturity_completion_date', now()->month)
                ->whereYear('maturity_completion_date', now()->year)
                ->count(),
            'average_maturity_age' => Weaning::where('maturity_completed', true)
                ->avg('age_at_maturity_days'),
            'breeding_destination' => Weaning::where('maturity_completed', true)
                ->where('next_destination', 'breeding')
                ->count(),
            'market_destination' => Weaning::where('maturity_completed', true)
                ->where('next_destination', 'market')
                ->count(),
        ];

        return view('weaning.weaned', compact('completedPigs', 'stats'));
    }

    public function weaningOverdue()
    {
        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

        $overduePigs = PigManagement::active()->whereNotNull('date_of_birth')
            ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$weaningAge + 7]) // 7+ days overdue
            ->whereDoesntHave('weaning')
            ->with(['mother', 'father'])
            ->orderBy('date_of_birth', 'asc') // Oldest first (most overdue)
            ->paginate(10);

        $stats = [
            'total_overdue' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$weaningAge + 7])
                ->whereDoesntHave('weaning')
                ->count(),
            'critically_overdue' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$weaningAge + 14]) // 14+ days overdue
                ->whereDoesntHave('weaning')
                ->count(),
        ];

        return view('weaning.overdue', compact('overduePigs', 'stats'));
    }

    public function recordWeaning(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);

        $data = $request->validate([
            'weaning_date' => 'required|date|before_or_equal:today',
            'weaning_weight' => 'nullable|numeric|min:0|max:500',
            'weaning_method' => 'required|in:natural,forced,gradual',
            'weaning_reason' => 'required|in:age_appropriate,health_issue,mother_issue,management_decision',
            'notes' => 'nullable|string|max:1000',
            'post_weaning_status' => 'required|in:healthy,stressed,sick,monitoring',
            'follow_up_date' => 'nullable|date|after:weaning_date'
        ]);

        // Check if pig is eligible for weaning
        if (!$pig->date_of_birth) {
            return back()->withErrors(['weaning' => 'Cannot wean pig without birth date.']);
        }

        // Check if pig is already weaned
        if ($pig->weaning) {
            return back()->withErrors(['weaning' => 'This pig has already been weaned.']);
        }

        $ageAtWeaning = (int) $pig->date_of_birth->diffInDays($data['weaning_date']);
        $minWeaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

        if ($ageAtWeaning < $minWeaningAge) {
            return back()->withErrors(['weaning' => "Pig is too young for weaning. Minimum age is {$minWeaningAge} days."]);
        }

        // Create weaning record
        $weaning = Weaning::create([
            'pig_id' => $pig->id,
            'mother_id' => $pig->mother_id,
            'weaning_date' => $data['weaning_date'],
            'age_at_weaning_days' => $ageAtWeaning,
            'weaning_weight' => $data['weaning_weight'] ?? null,
            'weaning_method' => $data['weaning_method'],
            'weaning_reason' => $data['weaning_reason'],
            'notes' => $data['notes'] ?? null,
            'post_weaning_status' => $data['post_weaning_status'],
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'recorded_by' => auth()->id(),
            'maturity_completed' => false  // Explicitly set to false for In Progress tracking
        ]);

        // Create weight record if provided
        if (isset($data['weaning_weight'])) {
            $pig->weights()->create([
                'weight' => $data['weaning_weight'],
                'weigh_date' => $data['weaning_date'],
                'notes' => 'Weaning weight'
            ]);
        }

        $statusMessage = match($data['post_weaning_status']) {
            'healthy' => 'Pig appears healthy post-weaning.',
            'stressed' => 'Pig showing signs of stress. Monitor closely.',
            'sick' => 'Health concerns noted. Consider veterinary consultation.',
            'monitoring' => 'Pig requires ongoing monitoring.',
        };

        return back()->with('success',
            "Weaning recorded for {$pig->code} at {$ageAtWeaning} days old. {$statusMessage}"
        );
    }

    public function completeMaturity(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);

        $data = $request->validate([
            'completion_date' => 'required|date|before_or_equal:today',
            'final_weight' => 'nullable|numeric|min:0|max:500',
            'health_status' => 'required|in:excellent,good,fair,poor',
            'body_condition_score' => 'nullable|in:1,2,3,4,5',
            'next_destination' => 'required|in:breeding,market,monitoring,other',
            'growth_performance' => 'nullable|in:excellent,good,average,poor',
            'completion_notes' => 'nullable|string|max:1000'
        ]);

        // Check if pig has been weaned
        if (!$pig->weaning) {
            return back()->withErrors(['maturity' => 'This pig has not been weaned yet.']);
        }

        // Check if pig is already completed
        if ($pig->weaning->maturity_completed) {
            return back()->withErrors(['maturity' => 'This pig has already completed maturity.']);
        }

        $ageAtCompletion = (int) $pig->date_of_birth->diffInDays($data['completion_date']);
        $maturityAge = config('breeding.cycle.birth_to_maturity_days', 180);

        // Update weaning record with maturity completion data
        $pig->weaning->update([
            'maturity_completed' => true,
            'maturity_completion_date' => $data['completion_date'],
            'age_at_maturity_days' => $ageAtCompletion,
            'final_weight' => $data['final_weight'],
            'health_status_at_maturity' => $data['health_status'],
            'body_condition_score' => $data['body_condition_score'],
            'next_destination' => $data['next_destination'],
            'growth_performance' => $data['growth_performance'],
            'maturity_notes' => $data['completion_notes'],
            'completed_by' => auth()->id()
        ]);

        // Create final weight record if provided
        if (isset($data['final_weight'])) {
            $pig->weights()->create([
                'weight' => $data['final_weight'],
                'weigh_date' => $data['completion_date'],
                'notes' => 'Maturity completion weight'
            ]);
        }

        $destinationMessage = match($data['next_destination']) {
            'breeding' => 'Ready for breeding program.',
            'market' => 'Ready for market.',
            'monitoring' => 'Requires continued monitoring.',
            'other' => 'Special handling required.',
        };

        return back()->with('success',
            "Maturity completed for {$pig->code} at {$ageAtCompletion} days old. {$destinationMessage}"
        );
    }

    public function updateWeaningStatus(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:weaned,delayed,health_issue',
            'status_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($data['status'] === 'weaned') {
            $pig->update([
                'weaning_date' => $data['status_date'],
                'weaning_notes' => $data['notes'] ?? null
            ]);
            $message = "Weaning status updated for {$pig->code}.";
        } else {
            $pig->update([
                'weaning_notes' => $data['notes'] ?? null
            ]);
            $statusMessage = match($data['status']) {
                'delayed' => "Weaning delayed for {$pig->code}. Monitor for readiness.",
                'health_issue' => "Health issue noted for {$pig->code}. Consult veterinarian before weaning.",
            };
            $message = $statusMessage;
        }

        return back()->with('success', $message);
    }

    // Promote pig from standard to breeding stock
    public function promoteToBreeding($id)
    {
        $pig = PigManagement::findOrFail($id);

        // Check if pig is already breeding stock
        if ($pig->stock_type === 'breeding') {
            return FlashHelper::errorAndBack('This pig is already breeding stock.');
        }

        // Check breeding readiness
        $readiness = $pig->breeding_readiness;

        // Allow promotion if pig meets minimum age requirements (even if not optimal)
        if (!in_array($readiness['age_status'], ['minimum_met', 'optimal'])) {
            return back()->withErrors([
                'promotion' => "Cannot promote {$pig->code} to breeding stock: {$readiness['age_message']}"
            ]);
        }

        // Check weight if available
        if ($readiness['factors']['weight']['status'] === 'too_light') {
            return back()->withErrors([
                'promotion' => "Cannot promote {$pig->code} to breeding stock: {$readiness['factors']['weight']['message']}"
            ]);
        }

        // Promote to breeding stock
        $pig->update(['stock_type' => 'breeding']);

        // Create a weight record for breeding promotion if none exists
        if (!$pig->latestWeight) {
            // Suggest adding weight data
            $message = "Pig {$pig->code} promoted to breeding stock successfully! Consider adding weight data for better breeding management.";
        } else {
            $message = "Pig {$pig->code} promoted to breeding stock successfully!";
        }

        return FlashHelper::successAndRedirect($message, 'breeding-stocks.index');
    }

    // Demote pig from breeding to standard stock
    public function demoteToStandard($id)
    {
        $pig = PigManagement::findOrFail($id);

        // Check if pig is already standard stock
        if ($pig->stock_type === 'standard') {
            return back()->withErrors(['demotion' => 'This pig is already standard stock.']);
        }

        // Check if pig has active breeding activities
        $hasActiveMating = $pig->maleMatings()->whereIn('status', ['planned', 'completed'])->exists() ||
                          $pig->femaleMatings()->whereIn('status', ['planned', 'completed'])->exists();

        if ($hasActiveMating) {
            return back()->withErrors([
                'demotion' => "Cannot demote {$pig->code} to standard stock: pig has active or completed matings."
            ]);
        }

        // Demote to standard stock
        $pig->update(['stock_type' => 'standard']);

        return redirect()->route('standard-stocks.index')->with('success', "Pig {$pig->code} demoted to standard stock successfully.");
    }

    // Show detailed pig information
    public function show($id)
    {
        $pig = PigManagement::with([
            'father',
            'mother',
            'weights' => function($query) {
                $query->latest('weigh_date')->limit(10);
            },
            'latestWeight',
            'maleMatings' => function($query) {
                $query->with(['female', 'createdBy'])->latest('created_at');
            },
            'femaleMatings' => function($query) {
                $query->with(['male', 'createdBy'])->latest('created_at');
            }
        ])->findOrFail($id);

        // Get breeding readiness
        $breedingReadiness = $pig->breeding_readiness;

        // Get availability status
        $availabilityStatus = $pig->availability_status;

        // Get offspring (children)
        $offspring = PigManagement::where(function($query) use ($pig) {
            if ($pig->gender === 'M') {
                $query->where('father_id', $pig->id);
            } else {
                $query->where('mother_id', $pig->id);
            }
        })->with(['father', 'mother'])->get();

        // Get siblings
        $siblings = collect();
        if ($pig->father_id || $pig->mother_id) {
            $siblings = PigManagement::where('id', '!=', $pig->id)
                ->where(function($query) use ($pig) {
                    if ($pig->father_id) {
                        $query->where('father_id', $pig->father_id);
                    }
                    if ($pig->mother_id) {
                        $query->orWhere('mother_id', $pig->mother_id);
                    }
                })
                ->get();
        }

        // Calculate statistics
        $stats = [
            'total_matings' => $pig->maleMatings->count() + $pig->femaleMatings->count(),
            'successful_matings' => $pig->maleMatings->where('successful', true)->count() +
                                   $pig->femaleMatings->where('successful', true)->count(),
            'total_offspring' => $offspring->count(),
            'weight_records' => $pig->weights->count(),
            'days_since_last_weight' => $pig->latestWeight ? $pig->latestWeight->days_ago : null,
        ];

        // Calculate weight analytics (growth periods) similar to weights reports
        $analytics = [];
        $allWeightsAsc = $pig->weights()->orderBy('weigh_date', 'asc')->get();
        if ($allWeightsAsc->count() >= 2) {
            $analytics['total_records'] = $allWeightsAsc->count();
            $analytics['first_weight'] = $allWeightsAsc->first();
            $analytics['latest_weight'] = $allWeightsAsc->last();
            $analytics['weight_gain'] = $analytics['latest_weight']->weight - $analytics['first_weight']->weight;

            $daysBetween = $analytics['first_weight']->weigh_date->diffInDays($analytics['latest_weight']->weigh_date);
            $analytics['avg_daily_gain'] = $daysBetween > 0 ? $analytics['weight_gain'] / $daysBetween : 0;

            $analytics['growth_periods'] = [];
            for ($i = 1; $i < $allWeightsAsc->count(); $i++) {
                $current = $allWeightsAsc->values()[$i];
                $previous = $allWeightsAsc->values()[$i - 1];

                $days = $previous->weigh_date->diffInDays($current->weigh_date);
                $gain = $current->weight - $previous->weight;
                $dailyGain = $days > 0 ? $gain / $days : 0;

                $analytics['growth_periods'][] = [
                    'from_date' => $previous->weigh_date,
                    'to_date' => $current->weigh_date,
                    'days' => $days,
                    'weight_gain' => $gain,
                    'daily_gain' => $dailyGain,
                    'from_weight' => $previous->weight,
                    'to_weight' => $current->weight,
                ];
            }
        }

        return view('pigs.show', compact(
            'pig',
            'breedingReadiness',
            'availabilityStatus',
            'offspring',
            'siblings',
            'stats',
            'analytics'
        ));
    }

    // Sale Management Methods
    public function saleForm($id)
    {
        $pig = PigManagement::findOrFail($id);

        // Check if pig is already sold
        if ($pig->sales()->exists()) {
            return back()->withErrors(['sale' => 'This pig has already been sold.']);
        }

        // Allow selling deceased pigs, but check eligibility for live pigs
        if (!$pig->deaths()->exists()) {
            $eligibility = $pig->sale_eligibility;
            if (!$eligibility['eligible']) {
                return back()->withErrors(['sale' => $eligibility['reason']]);
            }
        }

        return view('pigs.sale', compact('pig'));
    }

    public function recordSale(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);

        // Check if pig is already sold
        if ($pig->sales()->exists()) {
            return back()->withErrors(['sale' => 'This pig has already been sold.']);
        }

        // Allow selling deceased pigs, but check eligibility for live pigs
        if (!$pig->deaths()->exists()) {
            $eligibility = $pig->sale_eligibility;
            if (!$eligibility['eligible']) {
                return back()->withErrors(['sale' => $eligibility['reason']]);
            }
        }

        $data = $request->validate([
            'sale_date' => 'required|date|before_or_equal:today',
            'sale_weight' => 'required|numeric|min:0',
            'weight_type' => 'required|in:live,carcass',
            'buyer_name' => 'required|string|max:255',
            'buyer_contact' => 'nullable|string|max:255',
            'buyer_address' => 'nullable|string|max:1000',
            'sale_purpose' => 'required|in:market,breeding,slaughter,export,other',
            'sale_purpose_other' => 'required_if:sale_purpose,other|string|max:255',
            'payment_method' => 'nullable|in:cash,check,bank_transfer,mobile_money,other',
            'payment_method_other' => 'required_if:payment_method,other|string|max:255',
            'payment_status' => 'required|in:pending,paid,partial',
            'notes' => 'nullable|string|max:1000',
            'sold_by' => 'nullable|string|max:255',
            'transport_cost' => 'nullable|numeric|min:0',
            'transport_method' => 'nullable|in:pickup,delivery,farm_gate,other'
        ]);

        $data['pig_id'] = $pig->id;
        $data['sold_by'] = auth()->user()->name; // Set the current user as the person who sold the pig

        // Set price rate based on weight type
        if ($data['weight_type'] === 'live') {
            $data['price_rate'] = \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00);
        } elseif ($data['weight_type'] === 'carcass') {
            $data['price_rate'] = \App\Models\Setting::get('sales.pricing.carcass_weight_price', 50.00);
        }

        // Handle "other" values
        if (isset($data['sale_purpose']) && $data['sale_purpose'] === 'other') {
            $data['sale_purpose'] = $data['sale_purpose_other'];
        }
        if (isset($data['payment_method']) && $data['payment_method'] === 'other') {
            $data['payment_method'] = $data['payment_method_other'];
        }

        // Create sale record
        PigSale::create($data);

        return redirect()->route('sales.index')->with('success', "Sale of pig {$pig->code} recorded successfully!");
    }

    // Sales Management
    public function salesIndex(Request $request)
    {
        $query = PigSale::with(['pig']);

        // Apply filters
        if ($request->buyer_name) {
            $query->where('buyer_name', 'like', '%' . $request->buyer_name . '%');
        }

        if ($request->sale_purpose) {
            $query->where('sale_purpose', $request->sale_purpose);
        }

        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->date_from) {
            $query->where('sale_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('sale_date', '<=', $request->date_to);
        }

        $sales = $query->orderBy('sale_date', 'desc')->paginate(10);
        $sales->appends($request->query());

        // Calculate statistics
        $stats = [
            'total_sales' => PigSale::count(),
            'total_value' => PigSale::sum('sale_price'),
            'recent_sales' => PigSale::recent(30)->count(),
            'recent_value' => PigSale::recent(30)->sum('sale_price'),
            'pending_payments' => PigSale::pending()->count(),
            'pending_value' => PigSale::pending()->sum('sale_price'),
            'average_price' => PigSale::avg('sale_price'),
            'average_weight' => PigSale::whereNotNull('sale_weight')->avg('sale_weight')
        ];

        return view('sales.index', compact('sales', 'stats'));
    }

    public function saleShow($id)
    {
        $sale = PigSale::with(['pig'])->findOrFail($id);
        return view('sales.show', compact('sale'));
    }

    public function updatePaymentStatus(Request $request, $id)
    {
        $sale = PigSale::findOrFail($id);

        $data = $request->validate([
            'payment_status' => 'required|in:pending,paid,partial',
            'notes' => 'nullable|string|max:1000'
        ]);

        $sale->update($data);

        return back()->with('success', 'Payment status updated successfully!');
    }

    // Sales Export Methods
    public function salesExportCsv(Request $request)
    {
        $query = PigSale::with(['pig']);

        // Apply same filters as index method
        if ($request->buyer_name) {
            $query->where('buyer_name', 'like', '%' . $request->buyer_name . '%');
        }
        if ($request->sale_purpose) {
            $query->where('sale_purpose', $request->sale_purpose);
        }
        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->date_from) {
            $query->where('sale_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('sale_date', '<=', $request->date_to);
        }

        $sales = $query->orderBy('sale_date', 'desc')->get();

        $filename = 'sales_records_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($sales) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'Pig Code',
                'Ear Tag',
                'Sale Date',
                'Buyer Name',
                'Buyer Contact',
                'Sale Weight (kg)',
                'Weight Type',
                'Sale Price (₵)',
                'Price per kg (₵)',
                'Sale Purpose',
                'Payment Status',
                'Payment Method',
                'Sold By',
                'Transport Cost (₵)',
                'Notes'
            ]);

            // CSV Data
            foreach ($sales as $sale) {
                fputcsv($file, [
                    $sale->pig->code ?? 'N/A',
                    $sale->pig->ear_tag_number ?? 'N/A',
                    $sale->sale_date->format('Y-m-d'),
                    $sale->buyer_name,
                    $sale->buyer_contact ?? '',
                    $sale->sale_weight ?? '',
                    ucfirst($sale->weight_type ?? 'live'),
                    number_format($sale->sale_price, 2),
                    $sale->price_per_kg ? number_format($sale->price_per_kg, 2) : '',
                    ucfirst($sale->sale_purpose),
                    ucfirst($sale->payment_status),
                    $sale->payment_method ? ucfirst(str_replace('_', ' ', $sale->payment_method)) : '',
                    $sale->sold_by ?? '',
                    $sale->transport_cost ? number_format($sale->transport_cost, 2) : '',
                    $sale->notes ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function salesExportPdf(Request $request)
    {
        $query = PigSale::with(['pig']);

        // Apply same filters as index method
        if ($request->buyer_name) {
            $query->where('buyer_name', 'like', '%' . $request->buyer_name . '%');
        }
        if ($request->sale_purpose) {
            $query->where('sale_purpose', $request->sale_purpose);
        }
        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->date_from) {
            $query->where('sale_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('sale_date', '<=', $request->date_to);
        }

        $sales = $query->orderBy('sale_date', 'desc')->get();

        // Calculate statistics
        $stats = [
            'total_sales' => $sales->count(),
            'total_value' => $sales->sum('sale_price'),
            'average_price' => $sales->avg('sale_price'),
            'average_weight' => $sales->where('sale_weight', '>', 0)->avg('sale_weight')
        ];

        $pdf = \PDF::loadView('exports.sales-pdf', compact('sales', 'stats'));
        $filename = 'sales_records_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        return $pdf->download($filename);
    }

    // Death Management Methods
    public function deathForm($id)
    {
        $pig = PigManagement::findOrFail($id);

        // Prevent recording death if pig already marked as deceased
        if ($pig->deaths()->exists()) {
            return back()->withErrors(['death' => 'This pig is already recorded as deceased.']);
        }

        return view('pigs.death', compact('pig'));
    }

    public function recordDeath(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);

        if ($pig->deaths()->exists()) {
            return back()->withErrors(['death' => 'This pig is already recorded as deceased.']);
        }

        $data = $request->validate([
            'death_date' => 'required|date|before_or_equal:today',
            'death_cause' => 'required|in:disease,accident,natural,stillbirth,crushing,starvation,hypothermia,dehydration,congenital,unknown,other',
            'death_cause_other' => 'required_if:death_cause,other|string|max:255',
            'final_weight' => 'nullable|numeric|min:0|max:1000',
            'death_location' => 'nullable|string|max:255',
            'veterinary_notes' => 'nullable|string|max:2000',
            'disposal_method' => 'required|in:burial,incineration,rendering,other',
            'disposal_method_other' => 'required_if:disposal_method,other|string|max:255',
            'disposal_date' => 'nullable|date|after_or_equal:death_date',
            'death_notes' => 'nullable|string|max:2000',
        ]);

        // Handle "other" values
        $deathCause = $data['death_cause'] === 'other' ? $data['death_cause_other'] : $data['death_cause'];
        $disposalMethod = $data['disposal_method'] === 'other' ? $data['disposal_method_other'] : $data['disposal_method'];

        // Create death record with pig relationship loaded for age calculation
        $death = new \App\Models\PigDeath([
            'pig_id' => $pig->id,
            'death_date' => $data['death_date'],
            'death_cause' => $deathCause,
            'final_weight' => $data['final_weight'] ?? null,
            'death_location' => $data['death_location'] ?? null,
            'veterinary_notes' => $data['veterinary_notes'] ?? null,
            'disposal_method' => $disposalMethod,
            'disposal_date' => $data['disposal_date'] ?? null,
            'death_notes' => $data['death_notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);
        
        // Load the pig relationship for age calculation
        $death->setRelation('pig', $pig);
        $death->save();

        return redirect()->route('deaths.index')->with('success', "Death of pig {$pig->code} recorded successfully.");
    }

    public function deathsIndex(Request $request)
    {
        // Adult pig deaths (pigs 60+ days old)
        $adultQuery = \App\Models\PigDeath::with(['pig'])
            ->whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= 60');
            });

        if ($request->death_cause) {
            $adultQuery->where('death_cause', $request->death_cause);
        }

        if ($request->date_from) {
            $adultQuery->where('death_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $adultQuery->where('death_date', '<=', $request->date_to);
        }

        $deaths = $adultQuery->orderBy('death_date', 'desc')->paginate(10);
        $deaths->appends($request->query());

        // Piglet deaths (pigs under 60 days old)
        $pigletQuery = \App\Models\PigDeath::with(['pig'])
            ->whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) < 60');
            });

        if ($request->death_cause) {
            $pigletQuery->where('death_cause', $request->death_cause);
        }

        if ($request->date_from) {
            $pigletQuery->where('death_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $pigletQuery->where('death_date', '<=', $request->date_to);
        }

        $pigletDeaths = $pigletQuery->orderBy('death_date', 'desc')->paginate(10);
        $pigletDeaths->appends($request->query());

        // Enhanced stats
        $stats = [
            'total_deaths' => \App\Models\PigDeath::count(),
            'recent_deaths' => \App\Models\PigDeath::recent(30)->count(),
            'total_piglet_deaths' => \App\Models\PigDeath::whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) < 60');
            })->count(),
            'recent_piglet_deaths' => \App\Models\PigDeath::recent(30)->whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) < 60');
            })->count(),
            'total_adult_deaths' => \App\Models\PigDeath::whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= 60');
            })->count(),
            'recent_adult_deaths' => \App\Models\PigDeath::recent(30)->whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= 60');
            })->count(),
        ];

        return view('deaths.index', compact('deaths', 'pigletDeaths', 'stats'));
    }

    public function deathShow($id)
    {
        $death = \App\Models\PigDeath::with(['pig', 'recordedBy'])->findOrFail($id);
        return view('deaths.show', compact('death'));
    }

    // Death Export Methods
    public function deathsExportCsv(Request $request)
    {
        // Adult deaths query (60+ days)
        $adultQuery = PigDeath::with(['pig', 'recordedBy'])
            ->whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= 60');
            });

        // Piglet deaths query (under 60 days)
        $pigletQuery = PigDeath::with(['pig', 'recordedBy'])
            ->whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) < 60');
            });

        // Apply filters to both queries
        if ($request->death_cause) {
            $adultQuery->where('death_cause', $request->death_cause);
            $pigletQuery->where('death_cause', $request->death_cause);
        }
        if ($request->date_from) {
            $adultQuery->where('death_date', '>=', $request->date_from);
            $pigletQuery->where('death_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $adultQuery->where('death_date', '<=', $request->date_to);
            $pigletQuery->where('death_date', '<=', $request->date_to);
        }

        $adultDeaths = $adultQuery->orderBy('death_date', 'desc')->get();
        $pigletDeaths = $pigletQuery->orderBy('death_date', 'desc')->get();

        $filename = 'death_records_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($adultDeaths, $pigletDeaths) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'Category',
                'Pig Code',
                'Ear Tag',
                'Death Date',
                'Age at Death',
                'Death Cause',
                'Final Weight (kg)',
                'Disposal Method',
                'Recorded By',
                'Notes'
            ]);

            // Adult Deaths Data
            foreach ($adultDeaths as $death) {
                fputcsv($file, [
                    'Adult (60+ days)',
                    $death->pig->code ?? 'N/A',
                    $death->pig->ear_tag_number ?? 'N/A',
                    $death->death_date->format('Y-m-d'),
                    $death->age_at_death_display ?? 'Unknown',
                    $death->death_cause_display,
                    $death->final_weight ? number_format($death->final_weight, 2) : '',
                    $death->disposal_method_display,
                    $death->recordedBy->name ?? 'System',
                    $death->death_notes ?? ''
                ]);
            }

            // Piglet Deaths Data
            foreach ($pigletDeaths as $death) {
                fputcsv($file, [
                    'Piglet (< 60 days)',
                    $death->pig->code ?? 'N/A',
                    $death->pig->ear_tag_number ?? 'N/A',
                    $death->death_date->format('Y-m-d'),
                    $death->age_at_death_display ?? 'Unknown',
                    $death->death_cause_display,
                    $death->final_weight ? number_format($death->final_weight, 2) : '',
                    $death->disposal_method_display,
                    $death->recordedBy->name ?? 'System',
                    $death->death_notes ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function deathsExportPdf(Request $request)
    {
        // Adult deaths query (60+ days)
        $adultQuery = PigDeath::with(['pig', 'recordedBy'])
            ->whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= 60');
            });

        // Piglet deaths query (under 60 days)
        $pigletQuery = PigDeath::with(['pig', 'recordedBy'])
            ->whereHas('pig', function($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) < 60');
            });

        // Apply filters to both queries
        if ($request->death_cause) {
            $adultQuery->where('death_cause', $request->death_cause);
            $pigletQuery->where('death_cause', $request->death_cause);
        }
        if ($request->date_from) {
            $adultQuery->where('death_date', '>=', $request->date_from);
            $pigletQuery->where('death_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $adultQuery->where('death_date', '<=', $request->date_to);
            $pigletQuery->where('death_date', '<=', $request->date_to);
        }

        $adultDeaths = $adultQuery->orderBy('death_date', 'desc')->get();
        $pigletDeaths = $pigletQuery->orderBy('death_date', 'desc')->get();

        // Calculate accurate statistics
        $stats = [
            'total_deaths' => $adultDeaths->count() + $pigletDeaths->count(),
            'adult_deaths' => $adultDeaths->count(),
            'piglet_deaths' => $pigletDeaths->count(),
            'recent_deaths' => $adultDeaths->where('death_date', '>=', now()->subDays(30))->count() + 
                              $pigletDeaths->where('death_date', '>=', now()->subDays(30))->count()
        ];

        $pdf = \PDF::loadView('exports.deaths-pdf', compact('adultDeaths', 'pigletDeaths', 'stats'));
        $filename = 'death_records_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        return $pdf->download($filename);
    }


    // Weaning Weight Update Methods
    public function updateWeaningWeight(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);

        $data = $request->validate([
            'weight' => 'required|numeric|min:0|max:100',
            'weigh_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500'
        ]);

        // Use database transaction to ensure data consistency
        DB::transaction(function () use ($pig, $data) {
            // Create or update weight record for the date (upsert)
            $weighDate = \Carbon\Carbon::parse($data['weigh_date'])->toDateString();
            $weight = PigWeight::where('pig_id', $pig->id)
                ->whereDate('weigh_date', $weighDate)
                ->first();

            if ($weight) {
                $weight->update([
                'weight' => $data['weight'],
                    'weigh_date' => $weighDate,
                'notes' => $data['notes'],
                'weighed_by' => auth()->user()->name,
                    'purpose' => 'weaning_tracking',
                'weight_unit' => 'kg',
                'is_estimated' => false
            ]);
            } else {
                $weight = PigWeight::create([
                'pig_id' => $pig->id,
                'weight' => $data['weight'],
                    'weigh_date' => $weighDate,
                'notes' => $data['notes'],
                'weighed_by' => auth()->user()->name,
                'purpose' => 'weaning_tracking',
                'weight_unit' => 'kg',
                'is_estimated' => false
            ]);
            }

            // Generate or regenerate alert immediately using standards
            // Prefer target week if pig has weaning record with target_week, else compute actual age in weeks
            $targetWeek = null;
            if ($pig->weaning) {
                $targetWeek = $pig->weaning->target_week ?? null;
            }

            // Delete any existing alerts for this weight record to avoid duplicates
            \App\Models\WeightAlert::where('weight_record_id', $weight->id)->delete();
            \App\Models\WeightAlert::createFromWeight($weight, $pig);
        });

        return back()->with('success', "Weight updated for piglet {$pig->ear_tag_number}! New weight: {$data['weight']}kg");
    }

    /**
     * Check if an ear tag number already exists (for real-time validation)
     */
    public function checkEarTag(Request $request)
    {
        $request->validate([
            'ear_tag_number' => 'required|string|max:50'
        ]);

        $earTagNumber = trim($request->ear_tag_number);
        $exists = PigManagement::where('ear_tag_number', $earTagNumber)->exists();

        return response()->json([
            'exists' => $exists,
            'ear_tag_number' => $earTagNumber
        ]);
    }
}

