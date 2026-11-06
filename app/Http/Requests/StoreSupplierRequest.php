<?php

namespace App\Http\Requests;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->vendor_id && $user->hasPermission(Permission::SUPPLIERS_CREATE);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:60'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['required', 'string', 'in:cash,net_7,net_15,net_30,prepayment'],
            'delivery_schedule' => ['nullable', 'array'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.ingredient_id' => ['required_with:ingredients', 'exists:ingredients,id'],
            'ingredients.*.price' => ['required_with:ingredients', 'numeric', 'min:0'],
            'ingredients.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ingredients.*.unit_id' => ['required_with:ingredients', 'exists:units,id'],
            'ingredients.*.supplier_sku' => ['nullable', 'string', 'max:60'],
            'ingredients.*.is_primary_supplier' => ['nullable', 'boolean'],
        ];
    }
}
