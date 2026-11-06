<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreStockTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id) {
            return false;
        }

        $type = $this->input('type');
        $permission = match ($type) {
            'receipt' => Permission::INVENTORY_RECEIPT,
            'issue', 'waste' => Permission::INVENTORY_ISSUE,
            'transfer' => Permission::INVENTORY_TRANSFER,
            'adjustment' => Permission::INVENTORY_ADJUST,
            default => null,
        };

        if ($permission && ! $user->hasPermission($permission)) {
            return false;
        }

        $warehouse = Warehouse::find($this->input('warehouse_id'));
        if (! $warehouse || (int) $warehouse->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        if ($this->filled('target_warehouse_id')) {
            $target = Warehouse::find($this->input('target_warehouse_id'));
            if (! $target || (int) $target->vendor_id !== (int) $user->vendor_id) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        $vendorId = Auth::user()?->vendor_id;

        $rules = [
            'type' => ['required', 'in:receipt,issue,transfer,adjustment,waste'],
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where('vendor_id', $vendorId),
            ],
            'target_warehouse_id' => [
                'required_if:type,transfer',
                'nullable',
                'different:warehouse_id',
                Rule::exists('warehouses', 'id')->where('vendor_id', $vendorId),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->input('type') === 'adjustment') {
            $rules['ingredient_id'] = [
                'required',
                Rule::exists('ingredients', 'id')->where('vendor_id', $vendorId),
            ];
            $rules['actual_quantity'] = ['required', 'numeric', 'min:0'];
            $rules['reason'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*.ingredient_id'] = [
                'required',
                Rule::exists('ingredients', 'id')->where('vendor_id', $vendorId),
            ];
            $rules['items.*.quantity'] = ['required', 'numeric', 'gt:0'];
            $rules['items.*.unit_id'] = ['required', 'exists:units,id'];
            $rules['items.*.unit_cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['items.*.notes'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
