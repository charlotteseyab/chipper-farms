<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CounterpartyDocument extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    public const TYPE_TIN_CERT     = 'tin_certificate';
    public const TYPE_BUSINESS_REG = 'business_registration';
    public const TYPE_VAT_CERT     = 'vat_certificate';
    public const TYPE_CONTRACT     = 'contract';
    public const TYPE_CREDIT_APP   = 'credit_application';
    public const TYPE_KYC          = 'kyc';
    public const TYPE_OTHER        = 'other';

    protected $fillable = [
        'organization_id',
        'counterparty_id',
        'document_type',
        'document_number',
        'issuing_authority',
        'issue_date',
        'expiry_date',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'notes',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'  => 'date',
            'expiry_date' => 'date',
            'size_bytes'  => 'integer',
        ];
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('document_type', $type);
    }

    public function scopeExpiringWithin(Builder $q, int $days): Builder
    {
        return $q->whereNotNull('expiry_date')
                 ->where('expiry_date', '<=', now()->addDays($days)->toDateString())
                 ->where('expiry_date', '>=', now()->toDateString());
    }

    public function scopeExpired(Builder $q): Builder
    {
        return $q->whereNotNull('expiry_date')
                 ->where('expiry_date', '<', now()->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(Carbon::today());
    }

    public function expiresInDays(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }
        return (int) Carbon::today()->diffInDays($this->expiry_date, false);
    }
}
