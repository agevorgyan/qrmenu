<?php

namespace App\Http\Requests\Finance;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->vendor_id && $user->hasPermission(Permission::FINANCE_INCOME_MANAGE);
    }

    public function rules(): array
    {
        return [
            'financial_category_id' => ['nullable', 'exists:financial_categories,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'income_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,card,other'],
            'cash_register_id' => ['nullable', 'exists:cash_registers,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'customer_payer' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
