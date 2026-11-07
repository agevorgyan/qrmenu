<?php

namespace App\Http\Requests\Finance;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreFinancialTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->vendor_id && $user->hasPermission(Permission::FINANCE_TRANSFERS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:till_to_safe,safe_to_till,safe_to_bank,bank_to_safe,bank_to_bank,register_to_register'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'source_type' => ['required', 'string', 'in:register,safe,bank_account'],
            'source_id' => ['nullable', 'integer'],
            'destination_type' => ['required', 'string', 'in:register,safe,bank_account'],
            'destination_id' => ['nullable', 'integer'],
            'transfer_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
