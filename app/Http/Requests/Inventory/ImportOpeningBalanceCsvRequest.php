<?php

namespace App\Http\Requests\Inventory;

use App\Models\Finance\InventoryOpeningBalanceImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportOpeningBalanceCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('manage_opening_balances');
    }

    public function rules(): array
    {
        return [
            'item_kind'    => ['required', Rule::in(InventoryOpeningBalanceImport::ALLOWED_KINDS)],
            'cutover_date' => ['required', 'date', 'before_or_equal:today'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'file' => [
                'required',
                'file',
                // Accept text/csv plus common variants (Excel CSV export
                // uses application/vnd.ms-excel; some uploaders mark CSV
                // as octet-stream).
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/octet-stream',
                'max:5120',
            ],
        ];
    }
}
