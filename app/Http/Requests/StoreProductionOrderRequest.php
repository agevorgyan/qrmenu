<?php

namespace App\Http\Requests;

use App\Models\Recipe;
use App\Models\Warehouse;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id || ! $user->hasPermission(Permission::PRODUCTION_CREATE)) {
            return false;
        }

        $recipe = Recipe::find($this->input('recipe_id'));
        if (! $recipe || (int) $recipe->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        $sourceWarehouse = Warehouse::find($this->input('source_warehouse_id'));
        if (! $sourceWarehouse || (int) $sourceWarehouse->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        $destinationWarehouse = Warehouse::find($this->input('destination_warehouse_id'));
        if (! $destinationWarehouse || (int) $destinationWarehouse->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'recipe_id' => ['required', 'exists:recipes,id'],
            'source_warehouse_id' => ['required', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'exists:warehouses,id'],
            'planned_quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'produced_ingredient_id' => ['nullable', 'exists:ingredients,id'],
            'planned_start_at' => ['nullable', 'date'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
