<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A vet visit. The vet is a typed name (most have no account); whoever
 * entered the notes is recorded separately (decision D13). Only animals with
 * an examination row were examined; the planned list never counts.
 */
class VetVisit extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes, \App\Models\Concerns\HasAttachments;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALISED = 'finalised';

    protected $fillable = [
        'organization_id', 'visited_at', 'veterinarian_name', 'veterinarian_user_id', 'clinic', 'contact',
        'room_id', 'location', 'reason', 'general_notes', 'recommendations', 'entered_by_user_id',
        'status', 'finalised_at', 'finalised_by', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = ['visited_at' => 'datetime', 'finalised_at' => 'datetime', 'voided_at' => 'datetime'];

    public function examinations(): HasMany
    {
        return $this->hasMany(VetExamination::class);
    }

    public function plannedAnimals(): HasMany
    {
        return $this->hasMany(VetVisitPlannedAnimal::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function isFinalised(): bool
    {
        return $this->status === self::STATUS_FINALISED;
    }
}
