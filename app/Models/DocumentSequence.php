<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * DocumentSequence.
 *
 * Writes should ALWAYS go through App\Services\Numbering\NumberingService,
 * which acquires a row-level lock before incrementing. Never call save() or
 * update() on this model directly for `next_number` changes — you'll race.
 */
class DocumentSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'sequence_type',
        'prefix',
        'year',
        'next_number',
        'padding',
        'format_template',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year'        => 'integer',
            'next_number' => 'integer',
            'padding'     => 'integer',
            'is_active'   => 'boolean',
        ];
    }

    /**
     * Render a number against this sequence's format.
     * Used by NumberingService after incrementing.
     */
    public function renderNumber(int $number): string
    {
        $padded = str_pad((string) $number, (int) $this->padding, '0', STR_PAD_LEFT);

        if ($this->format_template) {
            return strtr($this->format_template, [
                '{prefix}' => $this->prefix,
                '{year}'   => (string) $this->year,
                '{number}' => $padded,
            ]);
        }

        return sprintf('%s-%d-%s', $this->prefix, $this->year, $padded);
    }
}