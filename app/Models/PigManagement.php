<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Helpers\BreedingCalculator;
class PigManagement extends Model
{
    use SoftDeletes;
    // pigs_management is the root of derived tenancy: farrowing, matings,
    // weanings, health_records, pig_weights, medication_administrations and
    // piglet_deaths all resolve their organisation through a pig. The trait is
    // added here so new pigs are stamped with the current organisation on
    // create; without that, every new pig would be an unscoped row and every
    // record hanging off it would be unresolvable (CF-P0-005).
    use \App\Models\Concerns\BelongsToOrganization;
    protected $table = 'pigs_management'; // Pigs Management table name

    protected $fillable = [
        'organization_id',
        'ear_tag_number', 'breed', 'gender', 'stock_type', 'date_of_birth',
        'code', 'age_in_days', 'father_id', 'mother_id', 'room_id', 'local_id',
        'group_id', 'current_weight_kg_cache', 'weight_cache_recorded_at',
        'acquisition_cost', 'acquisition_date', 'acquisition_source',
        'acquisition_journal_batch_id', 'purchase_stock_intake_id',
    ];

    // Provide sane defaults for legacy-not-null columns used by tests/seeders
    protected $attributes = [
        'breed' => 'unknown',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'current_weight_kg_cache' => 'decimal:4',
        'weight_cache_recorded_at' => 'datetime',
        'acquisition_cost' => 'decimal:4',
        'acquisition_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($pig) {
            // Auto-generate local_id if not provided (global sequential ID).
            //
            // `withTrashed()` IS THE WHOLE FIX. A sold pig is soft-deleted by
            // RecordPigSaleService, but its row — and its local_id — stay in the
            // table and keep occupying `pigs_local_id_unique`. A plain max()
            // cannot see those rows, so after selling the most recently added
            // animal it returns a number that is already taken:
            //
            //   pigs 1,2,3 exist → sell 3 → max() = 2 → next pig gets 3 → boom
            //
            // That is a single-farm defect, not a multi-tenant one: any farm
            // that sells its newest pig could not then add another. It surfaced
            // while fixturing the storefront pig-pool work, where selling
            // animals is the normal case.
            if (!$pig->local_id) {
                $pig->local_id = static::nextLocalId(
                    $pig->organization_id ? (int) $pig->organization_id : null,
                );
            }

            // Auto-generate a unique code when not provided to satisfy NOT NULL + unique
            if (empty($pig->code)) {
                $pig->code = 'P-' . bin2hex(random_bytes(6));
            }

            // Keep age_in_days populated for schemas where it's NOT NULL.
            if (($pig->age_in_days === null || $pig->age_in_days === '') && $pig->date_of_birth) {
                $pig->age_in_days = (int) $pig->date_of_birth->diffInDays(now());
            }
        });
    }

    // Relationship to the father pig
    public function father(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'father_id');
    }

    // Relationship to the mother pig
    public function mother(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'mother_id');
    }

    // Children where this pig is the father
    public function childrenAsFather(): HasMany
    {
        return $this->hasMany(PigManagement::class, 'father_id');
    }

    // Children where this pig is the mother
    public function childrenAsMother(): HasMany
    {
        return $this->hasMany(PigManagement::class, 'mother_id');
    }

    // Optional: Custom accessor to get all children (father or mother)
    public function getAllChildrenAttribute()
    {
        return self::where('father_id', $this->id)
                    ->orWhere('mother_id', $this->id)
                    ->get();
    }

    // Weaning relationships
    public function weaning(): HasOne
    {
        return $this->hasOne(Weaning::class, 'pig_id');
    }

    public function weaningsAsMother(): HasMany
    {
        return $this->hasMany(Weaning::class, 'mother_id');
    }

    // Farrowing relationships
    public function farrowingsAsSow(): HasMany
    {
        return $this->hasMany(Farrowing::class, 'sow_id');
    }

    public function farrowingsAsBoar(): HasMany
    {
        return $this->hasMany(Farrowing::class, 'boar_id');
    }

    public function latestFarrowing()
    {
        return $this->hasOne(Farrowing::class, 'sow_id')->latest('farrowing_date');
    }

    // Accessor to get current age in days (dynamically calculated)
    public function getCurrentAgeInDaysAttribute()
    {
        if (!$this->date_of_birth) {
            return 0;
        }
        return (int) $this->date_of_birth->diffInDays(now());
    }

    // Accessor to get current age in weeks (dynamically calculated)
    public function getCurrentAgeInWeeksAttribute()
    {
        if (!$this->date_of_birth) {
            return 0;
        }
        return (int) $this->date_of_birth->diffInWeeks(now());
    }



    /**
     * The next free local_id, counting rows that are soft-deleted.
     *
     * Retries on a unique violation rather than locking the whole table: two
     * concurrent creates can read the same max and both try to claim it, and the
     * index is what makes that visible. A handful of attempts is cheaper than
     * serialising every pig creation on the farm, and the constraint guarantees
     * we never silently duplicate.
     */
    public static function nextLocalId(?int $organizationId = null, int $attempts = 5): int
    {
        $organizationId ??= app(\App\Support\CurrentOrganization::class)->id();
        $base = static::withoutGlobalScopes()
            ->withTrashed()
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId));
        $candidate = ((int) (clone $base)->max('local_id')) + 1;

        for ($try = 0; $try < $attempts; $try++) {
            $taken = (clone $base)->where('local_id', $candidate)->exists();

            if (! $taken) {
                return $candidate;
            }

            $candidate++;
        }

        return $candidate;
    }

    /**
     * Get compact age display for tables and lists
     */
    public function getAgeCompactAttribute()
    {
        if (!$this->date_of_birth) {
            return 'N/A';
        }

        $totalDays = $this->current_age_in_days;

        // Calculate years, months, and days
        $years = floor($totalDays / 365);
        $remainingDaysAfterYears = $totalDays % 365;
        $months = floor($remainingDaysAfterYears / 30);
        $days = $remainingDaysAfterYears % 30;

        $parts = [];

        if ($years > 0) {
            $parts[] = $years . 'y';
        }

        if ($months > 0) {
            $parts[] = $months . 'm';
        }

        if ($days > 0 || empty($parts)) {
            $parts[] = $days . 'd';
        }

        return implode(' ', $parts);
    }

    // Breeding age calculations using BreedingCalculator
    public function getBreedingStatusAttribute(): array
    {
        return BreedingCalculator::getBreedingStatus($this->date_of_birth);
    }

    public function getIsBreedingAgeAttribute(): bool
    {
        return BreedingCalculator::isBreedingAge($this->date_of_birth);
    }

    public function getIsTooOldForBreedingAttribute(): bool
    {
        return BreedingCalculator::isTooOldForBreeding($this->date_of_birth);
    }

    public function getMaturityDateAttribute(): \Carbon\Carbon
    {
        return BreedingCalculator::calculateMaturityDate($this->date_of_birth);
    }

    public function getDaysToMaturityAttribute(): int
    {
        if ($this->is_breeding_age) {
            return 0;
        }
        return (int) $this->maturity_date->diffInDays(now());
    }

    public function getBreedingStatusBadgeAttribute(): string
    {
        $status = $this->breeding_status;
        return match($status['status']) {
            'ready' => 'bg-green-100 text-green-800',
            'immature' => 'bg-yellow-100 text-yellow-800',
            'retired' => 'bg-gray-100 text-gray-800',
            default => 'bg-blue-100 text-blue-800'
        };
    }

    public function getBreedingStatusIconAttribute(): string
    {
        $status = $this->breeding_status;
        return match($status['status']) {
            'ready' => 'OK',
            'immature' => '⏳',
            'retired' => 'Too old',
            default => '?'
        };
    }

    // Availability tracking for breeding
    public function getIsAvailableForBreedingAttribute(): bool
    {
        // Must be of breeding age
        if (!$this->is_breeding_age) {
            return false;
        }

        // Check for active matings
        $activeMating = $this->getActiveMating();

        return $activeMating === null;
    }

    public function getActiveMating()
    {
        if ($this->gender === 'M') {
            // Males are only unavailable during planned matings, not after completion
            return Mating::where('male_id', $this->id)
                ->where('status', 'planned')
                ->first();
        } else {
            // Females are unavailable during planned matings and while pregnant/uncertain
            return Mating::where('female_id', $this->id)
                ->where(function($query) {
                    $query->where('status', 'planned')
                          ->orWhere(function($subQuery) {
                              $subQuery->where('status', 'completed')
                                       ->whereIn('pregnancy_status', ['pending', 'pregnant', 'uncertain']);
                          });
                })
                ->first();
        }
    }

    public function getAvailabilityStatusAttribute(): array
    {
        // Use our new breeding readiness system
        $readiness = $this->breeding_readiness;

        // Check age readiness first
        if (!$readiness['ready']) {
            $ageStatus = $readiness['age_status'];

            if ($ageStatus === 'too_young') {
                return [
                    'available' => false,
                    'reason' => $readiness['age_message'],
                    'status' => 'immature',
                    'icon' => '⏳'
                ];
            }

            if ($ageStatus === 'too_old') {
                return [
                    'available' => false,
                    'reason' => 'Too old for breeding',
                    'status' => 'retired',
                    'icon' => 'Too old'
                ];
            }

            // Check weight readiness - allow selection but show warning
            $weightStatus = $readiness['factors']['weight']['status'];
            if ($weightStatus === 'too_light') {
                return [
                    'available' => true, // Allow selection
                    'reason' => $readiness['factors']['weight']['message'],
                    'status' => 'underweight_warning',
                    'icon' => 'Warning',
                    'warning' => true,
                    'warning_message' => 'Underweight for breeding - proceed with caution'
                ];
            }
        }

        $activeMating = $this->getActiveMating();

        if (!$activeMating) {
            return [
                'available' => true,
                'reason' => 'Available for breeding',
                'status' => 'available',
                'icon' => 'OK'
            ];
        }

        // Determine specific unavailability reason
        if ($activeMating->status === 'planned') {
            return [
                'available' => false,
                'reason' => 'Mating planned for ' . $activeMating->planned_date->format('M d, Y'),
                'status' => 'planned_mating',
                'icon' => 'Date',
                'mating' => $activeMating
            ];
        }

        // For females only - males don't get restricted after mating completion
        if ($this->gender === 'F') {
            if ($activeMating->pregnancy_status === 'pending') {
                return [
                    'available' => false,
                    'reason' => 'Awaiting pregnancy check (mated ' . $activeMating->actual_date->format('M d, Y') . ')',
                    'status' => 'pending_check',
                    'icon' => '⏳',
                    'mating' => $activeMating
                ];
            }

            if ($activeMating->pregnancy_status === 'pregnant') {
                $farrowingDate = $activeMating->expected_farrowing_date;
                return [
                    'available' => false,
                    'reason' => 'Pregnant (due ' . $farrowingDate->format('M d, Y') . ')',
                    'status' => 'pregnant',
                    'icon' => 'Pregnant',
                    'mating' => $activeMating
                ];
            }

            if ($activeMating->pregnancy_status === 'uncertain') {
                return [
                    'available' => false,
                    'reason' => 'Pregnancy uncertain - follow-up needed',
                    'status' => 'uncertain',
                    'icon' => '?',
                    'mating' => $activeMating
                ];
            }
        }

        return [
            'available' => true,
            'reason' => 'Available for breeding',
            'status' => 'available',
            'icon' => 'OK'
        ];
    }

    public function getAvailabilityBadgeAttribute(): string
    {
        $status = $this->availability_status;
        return match($status['status']) {
            'available' => 'bg-green-100 text-green-800',
            'immature' => 'bg-yellow-100 text-yellow-800',
            'retired' => 'bg-gray-100 text-gray-800',
            'planned_mating' => 'bg-blue-100 text-blue-800',
            'pending_check' => 'bg-orange-100 text-orange-800',
            'pregnant' => 'bg-pink-100 text-pink-800',
            'uncertain' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    // Accessor to get age with proper formatting (years, months, days)
    public function getAgeDisplayAttribute()
    {
        if (!$this->date_of_birth) {
            return 'Unknown age';
        }

        $totalDays = $this->current_age_in_days;

        if ($totalDays < 0) {
            return 'Invalid Date';
        }

        // Calculate years, months, and days
        $years = floor($totalDays / 365);
        $remainingDaysAfterYears = $totalDays % 365;
        $months = floor($remainingDaysAfterYears / 30);
        $days = $remainingDaysAfterYears % 30;

        $parts = [];

        if ($years > 0) {
            $parts[] = $years . ' year' . ($years > 1 ? 's' : '');
        }

        if ($months > 0) {
            $parts[] = $months . ' month' . ($months > 1 ? 's' : '');
        }

        if ($days > 0 || empty($parts)) {
            $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
        }

        return implode(', ', $parts);
    }



    // Mating relationships
    public function maleMatings(): HasMany
    {
        return $this->hasMany(Mating::class, 'male_id');
    }

    public function femaleMatings(): HasMany
    {
        return $this->hasMany(Mating::class, 'female_id');
    }

    // Weight relationships
    public function weights(): HasMany
    {
        return $this->hasMany(PigWeight::class, 'pig_id');
    }

    public function recentWeightAlerts(): HasMany
    {
        return $this->hasMany(WeightAlert::class, 'pig_id');
    }

    /**
     * The most recent weight recorded for this animal.
     *
     * TIE-BROKEN ON id, AND IT MATTERS.
     *
     * Ordering by `weigh_date` alone leaves two weights recorded on the SAME
     * day in an undefined order — which one wins is whatever the engine feels
     * like returning. SQLite happened to give the later row and MariaDB the
     * earlier one, so a pig re-weighed the same day was priced off the reading
     * that was being corrected. Nothing caught it until the same test ran on
     * both engines.
     *
     * A second weight on the same day IS the correction, so the later-recorded
     * row wins.
     */
    public function latestWeight()
    {
        return $this->hasOne(PigWeight::class, 'pig_id')
            ->orderByDesc('weigh_date')
            ->orderByDesc('id');
    }

    public function purchaseStockIntake(): BelongsTo
    {
        return $this->belongsTo(PurchaseStockIntake::class, 'purchase_stock_intake_id');
    }

    public function isPregnant(): bool
    {
        if ($this->gender !== 'F') {
            return false;
        }

        return $this->femaleMatings()
            ->where('pregnancy_status', 'pregnant')
            ->exists();
    }

    // Sale relationship
    public function sale()
    {
        return $this->hasOne(PigSale::class, 'pig_id');
    }



    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    // Sales and Death relationships
    public function sales(): HasMany
    {
        return $this->hasMany(PigSale::class, 'pig_id');
    }

    public function deaths(): HasMany
    {
        return $this->hasMany(PigDeath::class, 'pig_id');
    }

    /**
     * Storefront listing pools this pig belongs to, in any state.
     *
     * A pig may appear in several rows over its life — pulled from one listing,
     * added to another — but may be ACTIVE in only one at a time. That
     * invariant lives in `StorefrontPigPoolService` under a row lock, because
     * MySQL and MariaDB have no partial unique indexes.
     */
    public function storefrontListingLinks(): HasMany
    {
        return $this->hasMany(\App\Models\Storefront\StorefrontListingPig::class, 'pig_id');
    }

    /**
     * The one pool row that currently speaks for this pig, if any.
     */
    public function activeStorefrontListingLink()
    {
        return $this->hasOne(\App\Models\Storefront\StorefrontListingPig::class, 'pig_id')
            ->whereIn('status', \App\Models\Storefront\StorefrontListingPig::ACTIVE_STATUSES);
    }

    /**
     * Storefront order allocations naming this pig.
     */
    public function storefrontPigAllocations(): HasMany
    {
        return $this->hasMany(\App\Models\Storefront\StorefrontOrderItemPigAllocation::class, 'pig_id');
    }

    // Scopes for availability
    public function scopeAvailableForBreeding($query)
    {
        return $query->where(function($q) {
            $q->where(function($maleQuery) {
                // Males: only unavailable during planned matings
                $maleQuery->where('gender', 'M')
                         ->whereDoesntHave('maleMatings', function($matingQuery) {
                             $matingQuery->where('status', 'planned');
                         });
            })
            ->orWhere(function($femaleQuery) {
                // Females: unavailable during planned matings and pregnancy/uncertainty
                $femaleQuery->where('gender', 'F')
                           ->whereDoesntHave('femaleMatings', function($matingQuery) {
                               $matingQuery->where(function($statusQuery) {
                                   $statusQuery->where('status', 'planned')
                                              ->orWhere(function($pregnancyQuery) {
                                                  $pregnancyQuery->where('status', 'completed')
                                                                ->whereIn('pregnancy_status', ['pending', 'pregnant', 'uncertain']);
                                              });
                               });
                           });
            });
        });
    }

    public function scopeBreedingAge($query)
    {
        $maxAge = config('breeding.parameters.maximum_breeding_age');
        $minDate = now()->subDays($maxAge);

        return $query->where(function($q) use ($minDate) {
            // Female breeding age (7 months minimum)
            $femaleMinAge = config('breeding.parameters.minimum_breeding_age.female');
            $femaleMaxDate = now()->subDays($femaleMinAge);

            // Male breeding age (8 months minimum)
            $maleMinAge = config('breeding.parameters.minimum_breeding_age.male');
            $maleMaxDate = now()->subDays($maleMinAge);

            $q->where(function($femaleQuery) use ($minDate, $femaleMaxDate) {
                $femaleQuery->where('gender', 'F')
                           ->whereBetween('date_of_birth', [$minDate, $femaleMaxDate]);
            })
            ->orWhere(function($maleQuery) use ($minDate, $maleMaxDate) {
                $maleQuery->where('gender', 'M')
                         ->whereBetween('date_of_birth', [$minDate, $maleMaxDate]);
            });
        });
    }

    public function scopeOptimalBreedingAge($query)
    {
        $maxAge = config('breeding.parameters.maximum_breeding_age');
        $minDate = now()->subDays($maxAge);

        return $query->where(function($q) use ($minDate) {
            // Female optimal breeding age (8 months)
            $femaleOptimalAge = config('breeding.parameters.optimal_first_breeding_age.female');
            $femaleMaxDate = now()->subDays($femaleOptimalAge);

            // Male optimal breeding age (10 months)
            $maleOptimalAge = config('breeding.parameters.optimal_first_breeding_age.male');
            $maleMaxDate = now()->subDays($maleOptimalAge);

            $q->where(function($femaleQuery) use ($minDate, $femaleMaxDate) {
                $femaleQuery->where('gender', 'F')
                           ->whereBetween('date_of_birth', [$minDate, $femaleMaxDate]);
            })
            ->orWhere(function($maleQuery) use ($minDate, $maleMaxDate) {
                $maleQuery->where('gender', 'M')
                         ->whereBetween('date_of_birth', [$minDate, $maleMaxDate]);
            });
        });
    }

    // Status scopes
    public function scopeActive($query)
    {
        return $query->whereDoesntHave('sales')->whereDoesntHave('deaths');
    }

    public function scopeSold($query)
    {
        return $query->whereHas('sales');
    }

    public function scopeDeceased($query)
    {
        return $query->whereHas('deaths');
    }

    public function scopeInactive($query)
    {
        return $query->where(function($q) {
            $q->whereHas('sales')->orWhereHas('deaths');
        });
    }

    // Status accessors
    public function getStatusAttribute(): string
    {
        if ($this->deaths()->exists()) {
            return 'deceased';
        }
        if ($this->sales()->exists()) {
            return 'sold';
        }
        return 'active';
    }

    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'active' => 'Active',
            'sold' => 'Sold',
            'deceased' => 'Deceased',
            default => 'Unknown'
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'active' => 'bg-green-100 text-green-800',
            'sold' => 'bg-blue-100 text-blue-800',
            'deceased' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    

    public function getIsDeceasedAttribute(): bool
    {
        return $this->status === 'deceased';
    }

    // Breeding readiness assessment
    public function getBreedingReadinessAttribute(): array
    {
        $ageInDays = $this->current_age_in_days;
        $gender = $this->gender === 'M' ? 'male' : 'female';

        $minAge = config("breeding.parameters.minimum_breeding_age.{$gender}");
        $optimalAge = config("breeding.parameters.optimal_first_breeding_age.{$gender}");
        $maxAge = config('breeding.parameters.maximum_breeding_age');

        // Age assessment
        $ageStatus = 'too_young';
        $ageMessage = '';

        if ($ageInDays >= $maxAge) {
            $ageStatus = 'too_old';
            $ageMessage = 'Pig is beyond optimal breeding age';
        } elseif ($ageInDays >= $optimalAge) {
            $ageStatus = 'optimal';
            $ageMessage = 'Pig is at optimal breeding age';
        } elseif ($ageInDays >= $minAge) {
            $ageStatus = 'minimum_met';
            $ageMessage = 'Pig meets minimum breeding age but not yet optimal';
        } else {
            $daysUntilMinimum = $minAge - $ageInDays;
            $ageMessage = "Pig needs {$daysUntilMinimum} more days to reach minimum breeding age";
        }

        // Weight assessment
        $weightStatus = 'not_checked';
        $weightMessage = 'Weight data not available';
        $weightReady = null;

        $latestWeight = $this->latestWeight;
        if ($latestWeight) {
            $minWeight = config("breeding.parameters.minimum_breeding_weight.{$gender}");
            $optimalWeight = config("breeding.parameters.optimal_breeding_weight.{$gender}");
            $currentWeight = $latestWeight->weight_in_kg;

            if ($currentWeight >= $optimalWeight) {
                $weightStatus = 'optimal';
                $weightMessage = "Weight is optimal for breeding ({$latestWeight->formatted_weight})";
                $weightReady = true;
            } elseif ($currentWeight >= $minWeight) {
                $weightStatus = 'minimum_met';
                $weightMessage = "Weight meets minimum requirement ({$latestWeight->formatted_weight})";
                $weightReady = true;
            } else {
                $weightStatus = 'too_light';
                $neededWeight = $minWeight - $currentWeight;
                $weightMessage = "Pig needs to gain {$neededWeight}kg to reach minimum breeding weight";
                $weightReady = false;
            }
        }

        // Overall readiness (includes both age and weight)
        $overallReady = in_array($ageStatus, ['minimum_met', 'optimal']) &&
                       ($weightReady === null || $weightReady === true);

        $readinessScore = match(true) {
            $ageStatus === 'optimal' && $weightStatus === 'optimal' => 100,
            $ageStatus === 'optimal' && $weightStatus === 'minimum_met' => 90,
            $ageStatus === 'optimal' && $weightStatus === 'not_checked' => 85,
            $ageStatus === 'minimum_met' && $weightStatus === 'optimal' => 85,
            $ageStatus === 'minimum_met' && $weightStatus === 'minimum_met' => 75,
            $ageStatus === 'minimum_met' && $weightStatus === 'not_checked' => 70,
            $weightStatus === 'too_light' => 25,
            $ageStatus === 'too_young' => 0,
            $ageStatus === 'too_old' => 50,
            default => 0
        };

        return [
            'ready' => $overallReady,
            'score' => $readinessScore,
            'age_status' => $ageStatus,
            'age_message' => $ageMessage,
            'age_in_days' => $ageInDays,
            'minimum_age' => $minAge,
            'optimal_age' => $optimalAge,
            'maximum_age' => $maxAge,
            'factors' => [
                'age' => [
                    'status' => $ageStatus,
                    'meets_requirement' => $overallReady,
                    'message' => $ageMessage
                ],
                // Weight factor
                'weight' => [
                    'status' => $weightStatus,
                    'meets_requirement' => $weightReady,
                    'message' => $weightMessage,
                    'current_weight' => $latestWeight ? $latestWeight->formatted_weight : null,
                    'minimum_required' => $latestWeight ? number_format((float)config("breeding.parameters.minimum_breeding_weight.{$gender}") ?? 0, 1) . ' kg' : null,
                    'optimal_weight' => $latestWeight ? number_format((float)config("breeding.parameters.optimal_breeding_weight.{$gender}") ?? 0, 1) . ' kg' : null,
                    'last_weighed' => $latestWeight ? $latestWeight->weigh_date->format('M d, Y') : null
                ]
            ]
        ];
    }

    // Check if pig is ready for first breeding
    public function getIsReadyForFirstBreedingAttribute(): bool
    {
        return $this->breeding_readiness['ready'];
    }

    // Get breeding readiness message for UI
    public function getBreedingReadinessMessageAttribute(): string
    {
        $readiness = $this->breeding_readiness;

        if ($readiness['ready']) {
            return $readiness['age_message'];
        }

        return $readiness['age_message'];
    }

    // Get breeding readiness badge class for UI
    public function getBreedingReadinessBadgeAttribute(): string
    {
        $status = $this->breeding_readiness['age_status'];

        return match($status) {
            'optimal' => 'bg-green-100 text-green-800',
            'minimum_met' => 'bg-yellow-100 text-yellow-800',
            'too_young' => 'bg-red-100 text-red-800',
            'too_old' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    // Sale status methods
    public function getIsSoldAttribute(): bool
    {
        return $this->sale()->exists();
    }

    public function getSaleStatusAttribute(): array
    {
        if (!$this->is_sold) {
            return [
                'sold' => false,
                'status' => 'available',
                'message' => 'Available for sale',
                'icon' => 'Money',
                'badge_class' => 'bg-green-100 text-green-800'
            ];
        }

        $sale = $this->sale;
        return [
            'sold' => true,
            'status' => 'sold',
            'message' => "Sold on {$sale->sale_date->format('M d, Y')} for {$sale->formatted_sale_price}",
            'sale_date' => $sale->sale_date,
            'sale_price' => $sale->formatted_sale_price,
            'buyer' => $sale->buyer_name,
            'purpose' => $sale->sale_purpose,
            'icon' => 'OK',
            'badge_class' => 'bg-blue-100 text-blue-800'
        ];
    }

    public function getSaleEligibilityAttribute(): array
    {
        // Already sold
        if ($this->is_sold) {
            return [
                'eligible' => false,
                'reason' => 'Pig has already been sold',
                'icon' => 'OK'
            ];
        }

        // Breeding stock - generally not for sale unless specifically demoted
        if ($this->stock_type === 'breeding') {
            return [
                'eligible' => false,
                'reason' => 'Breeding stock - demote to standard stock first',
                'icon' => 'Blocked'
            ];
        }

        // Check if pig has active breeding activities
        $hasActiveMating = $this->maleMatings()->whereIn('status', ['planned', 'completed'])->exists() ||
                          $this->femaleMatings()->whereIn('status', ['planned', 'completed'])->exists();

        if ($hasActiveMating) {
            return [
                'eligible' => false,
                'reason' => 'Pig has active mating records',
                'icon' => 'Breeding'
            ];
        }

        // Standard stock without active breeding - eligible for sale
        return [
            'eligible' => true,
            'reason' => 'Eligible for sale',
            'icon' => 'Money'
        ];
    }

    // Weighing schedule methods
    // Weighing schedule methods
public function hasAnyWeightRecord(): bool
{
    return $this->weights()->exists();
}

public function getLastWeighingDate()
{
    $lastWeight = $this->weights()->latest('weigh_date')->first();
    return $lastWeight ? $lastWeight->weigh_date : null;
}

public function getDaysSinceLastWeighing(): ?int
{
    $lastWeighing = $this->getLastWeighingDate();

    if (!$lastWeighing) {
        return null;
    }

    return (int) $lastWeighing->diffInDays(now());
}

public function getFirstWeightDueDate(int $firstWeightGraceDays = 30)
{
    if (!$this->date_of_birth) {
        return null;
    }

    return $this->date_of_birth->copy()->addDays($firstWeightGraceDays);
}

public function isOverdueForWeighing(int $weighingIntervalDays = 30): bool
{
    if (!$this->hasAnyWeightRecord()) {
        return false;
    }

    $daysSince = $this->getDaysSinceLastWeighing();

    return $daysSince !== null && $daysSince > $weighingIntervalDays;
}

public function getWeighingStatus(
    int $weighingIntervalDays = 30,
    int $criticalDays = 60,
    int $firstWeightGraceDays = 30
): array {
    $lastWeighing = $this->getLastWeighingDate();

    if (!$lastWeighing) {
        $firstDueDate = $this->getFirstWeightDueDate($firstWeightGraceDays);

        if (!$firstDueDate) {
            return [
                'status' => 'unknown',
                'message' => 'No birth date or weight record',
                'color' => 'gray',
                'icon' => '?',
                'days_since' => null,
                'overdue_days' => null,
            ];
        }

        $daysUntilFirstDue = now()->startOfDay()->diffInDays($firstDueDate->copy()->startOfDay(), false);

        if ($daysUntilFirstDue > 0) {
            return [
                'status' => 'not_due_yet',
                'message' => 'First weight not due yet',
                'color' => 'blue',
                'icon' => 'Guide',
                'days_since' => null,
                'overdue_days' => 0,
            ];
        }

        return [
            'status' => 'first_weight_pending',
            'message' => 'First weight pending',
            'color' => 'yellow',
            'icon' => 'Notes',
            'days_since' => null,
            'overdue_days' => abs($daysUntilFirstDue),
        ];
    }

    $daysSince = (int) $lastWeighing->diffInDays(now());

    if ($daysSince <= 21) {
        return [
            'status' => 'current',
            'message' => 'Up to date',
            'color' => 'green',
            'icon' => 'OK',
            'days_since' => $daysSince,
            'overdue_days' => 0,
        ];
    }

    if ($daysSince <= $weighingIntervalDays) {
        return [
            'status' => 'due_soon',
            'message' => 'Due soon',
            'color' => 'yellow',
            'icon' => 'Warning',
            'days_since' => $daysSince,
            'overdue_days' => 0,
        ];
    }

    if ($daysSince <= $criticalDays) {
        return [
            'status' => 'overdue',
            'message' => 'Overdue',
            'color' => 'orange',
            'icon' => 'Orange',
            'days_since' => $daysSince,
            'overdue_days' => $daysSince - $weighingIntervalDays,
        ];
    }

    return [
        'status' => 'critical',
        'message' => 'Critically overdue',
        'color' => 'red',
        'icon' => 'Alert',
        'days_since' => $daysSince,
        'overdue_days' => $daysSince - $weighingIntervalDays,
    ];
}

public function getNextWeighingDate(
    int $weighingIntervalDays = 30,
    int $firstWeightGraceDays = 30
)
{
    $lastWeighing = $this->getLastWeighingDate();

    if (!$lastWeighing) {
        return $this->getFirstWeightDueDate($firstWeightGraceDays);
    }

    return $lastWeighing->copy()->addDays($weighingIntervalDays);
}

// Static methods for weighing alerts
public static function getOverdueForWeighing(int $weighingIntervalDays = 30)
{
    return static::whereDoesntHave('deaths')
        ->whereDoesntHave('sales')
        ->whereHas('weights')
        ->get()
        ->filter(function ($pig) use ($weighingIntervalDays) {
            return $pig->isOverdueForWeighing($weighingIntervalDays);
        });
}

public static function getDueForWeighing(int $weighingIntervalDays = 30)
{
    return static::whereDoesntHave('deaths')
        ->whereDoesntHave('sales')
        ->whereHas('weights')
        ->get()
        ->filter(function ($pig) {
            $status = $pig->getWeighingStatus();
            return $status['status'] === 'due_soon';
        });
}

public static function getFirstWeightPending(int $firstWeightGraceDays = 30)
{
    return static::whereDoesntHave('deaths')
        ->whereDoesntHave('sales')
        ->whereDoesntHave('weights')
        ->whereNotNull('date_of_birth')
        ->get()
        ->filter(function ($pig) use ($firstWeightGraceDays) {
            $firstDueDate = $pig->getFirstWeightDueDate($firstWeightGraceDays);

            return $firstDueDate
                && now()->startOfDay()->greaterThanOrEqualTo($firstDueDate->copy()->startOfDay());
        });
}

public static function countOverdueForWeighing(int $weighingIntervalDays = 30)
{
    return static::getOverdueForWeighing($weighingIntervalDays)->count();
}

public static function countDueForWeighing(int $weighingIntervalDays = 30)
{
    return static::getDueForWeighing($weighingIntervalDays)->count();
}

public static function countFirstWeightPending(int $firstWeightGraceDays = 30)
{
    return static::getFirstWeightPending($firstWeightGraceDays)->count();
}

    public function litters()
    {
        // A Sow (PigManagement) can have many Litters.
        // We assume the foreign key on the 'litters' table is 'sow_id'
        return $this->hasMany(Litter::class, 'sow_id');
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class, 'pig_id');
    }

    public function medicationAdministrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class, 'pig_id');
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
          {
              return $this->belongsTo(\App\Models\Group::class);
          }

    
}
