<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id) {
            return false;
        }

        if (! $user->hasPermission(Permission::INGREDIENTS_UPDATE)) {
            return false;
        }

        $ingredient = $this->route('ingredient');
        if (! $ingredient instanceof Ingredient) {
            $ingredient = Ingredient::find($ingredient);
        }

        if (! $ingredient || (int) $ingredient->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        if ($this->filled('category_id')) {
            $cat = IngredientCategory::find($this->input('category_id'));
            if (! $cat || (int) $cat->vendor_id !== (int) $user->vendor_id) {
                return false;
            }
        }

        if ($this->filled('unit_id')) {
            $unit = Unit::find($this->input('unit_id'));
            if (! $unit || ($unit->vendor_id !== null && (int) $unit->vendor_id !== (int) $user->vendor_id)) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        $vendorId = Auth::user()?->vendor_id;

        return [
            'name' => ['required', 'string', 'max:200'],
            'name_translations' => ['nullable', 'array'],
            'category_id' => [
                'nullable',
                Rule::exists('ingredient_categories', 'id')->where('vendor_id', $vendorId),
            ],
            'unit_id' => ['required', 'exists:units,id'],
            'sku' => ['nullable', 'string', 'max:60'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:raw_material,ingredient,semi_finished,finished,packaging,consumable'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'last_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'current_cost' => ['nullable', 'numeric', 'min:0'],
            'storage_conditions' => ['nullable', 'string', 'max:100'],
            'storage_temperature_min' => ['nullable', 'numeric', 'between:-50,50'],
            'storage_temperature_max' => ['nullable', 'numeric', 'between:-50,50'],
            'shelf_life_days' => ['nullable', 'integer', 'min:1'],
            'calories' => ['nullable', 'numeric', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'carbs_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['nullable', 'numeric', 'min:0'],
            'density_kg_per_l' => ['nullable', 'numeric', 'min:0.01'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['exists:allergens,id'],
            'is_active' => ['boolean'],
        ];
    }
}
