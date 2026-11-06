<?php

namespace App\Http\Requests;

use App\Models\ProductionOrder;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CompleteProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id || ! $user->hasPermission(Permission::PRODUCTION_COMPLETE)) {
            return false;
        }

        /** @var ProductionOrder|null $order */
        $order = $this->route('production_order') ?? $this->route('productionOrder');
        if (! $order || (int) $order->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'actual_quantity' => ['required', 'numeric', 'min:0.0001'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:60'],
            'expiry_date' => ['nullable', 'date'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer'],
            'items.*.actual_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.waste_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
