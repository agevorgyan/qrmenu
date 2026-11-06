<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use App\Models\Warehouse;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id || ! $user->hasPermission(Permission::PROCUREMENT_CREATE)) {
            return false;
        }

        $po = $this->route('purchaseOrder');
        if (! $po || (int) $po->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        $supplier = Supplier::find($this->input('supplier_id'));
        if (! $supplier || (int) $supplier->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        $warehouse = Warehouse::find($this->input('warehouse_id'));
        if (! $warehouse || (int) $warehouse->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'exists:ingredients,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
