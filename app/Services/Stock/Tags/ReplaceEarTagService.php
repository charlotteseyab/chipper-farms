<?php

namespace App\Services\Stock\Tags;

use App\Models\EarTag;
use App\Models\EarTagAssignment;
use App\Models\EarTagColor;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\Stock\StockValidationException;
use Illuminate\Support\Facades\DB;

class ReplaceEarTagService
{
    public function __construct(private EarTagAssignmentService $assignments)
    {
    }

    public function assignFirst(PigManagement $pig, User $actor, array $data): EarTagAssignment
    {
        return DB::transaction(function () use ($pig, $actor, $data) {
            \App\Models\Organization::whereKey($pig->organization_id)->lockForUpdate()->firstOrFail();
            $pig = PigManagement::whereKey($pig->id)->lockForUpdate()->firstOrFail();
            if ($pig->currentEarTagAssignment()->exists()) {
                throw new StockValidationException('ear_tag_number', 'This animal already has a tag. Use Replace physical tag.');
            }
            if ($pig->ear_tag_number && ! $pig->earTagAssignments()->exists()) {
                throw new StockValidationException('ear_tag_number', 'This animal has an unreconciled legacy tag. Reconcile its history before assigning a new tag.');
            }
            $color = EarTagColor::whereKey($data['ear_tag_color_id'])->where('is_active', true)->firstOrFail();

            return $this->assignments->assign($pig, $color, $data['ear_tag_number'], $actor, now(), $data['reason']);
        }, 3);
    }

    public function execute(PigManagement $pig, User $actor, array $data): EarTagAssignment
    {
        $oldStatus = $data['old_tag_status'];
        if (! in_array($oldStatus, [EarTag::AVAILABLE, EarTag::LOST, EarTag::DAMAGED, EarTag::RETIRED], true)) {
            throw new StockValidationException('old_tag_status', 'Select what happened to the old physical tag.');
        }

        return DB::transaction(function () use ($pig, $actor, $data, $oldStatus) {
            \App\Models\Organization::whereKey($pig->organization_id)->lockForUpdate()->firstOrFail();
            $pig = PigManagement::whereKey($pig->id)->lockForUpdate()->firstOrFail();
            $current = EarTagAssignment::where('pig_id', $pig->id)->where('is_current', 1)
                ->lockForUpdate()->first();
            if (! $current) {
                throw new StockValidationException('ear_tag_number', 'This animal has no current tag assignment to replace.');
            }

            $this->assignments->release(
                $current, $oldStatus, 'replacement', $actor, now(), $data['reason'],
            );
            $color = EarTagColor::whereKey($data['ear_tag_color_id'])->where('is_active', true)->firstOrFail();

            return $this->assignments->assign(
                $pig, $color, $data['ear_tag_number'], $actor, now(),
                'Replacement reason: '.$data['reason'],
            );
        }, 3);
    }
}
