<?php

namespace App\Http\Requests;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ReceiveGoodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        $po = $this->route('purchaseOrder');

        return $user && $po && (int) $po->vendor_id === (int) $user->vendor_id && $user->hasPermission(Permission::PROCUREMENT_RECEIVE);
    }

    public function rules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
