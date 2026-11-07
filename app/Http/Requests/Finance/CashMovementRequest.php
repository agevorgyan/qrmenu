<?php

namespace App\Http\Requests\Finance;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->vendor_id && $user->hasPermission(Permission::FINANCE_SHIFTS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:cash_in,cash_out,safe_drop'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'destination_type' => ['nullable', 'string', 'in:safe,bank,register'],
            'destination_id' => ['nullable', 'integer'],
        ];
    }
}
