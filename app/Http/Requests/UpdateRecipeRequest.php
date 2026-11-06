<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Product;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id) {
            return false;
        }

        if (! $user->hasPermission(Permission::RECIPES_UPDATE)) {
            return false;
        }

        $recipe = $this->route('recipe');
        if (! $recipe || (int) $recipe->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        if ($this->filled('category_id')) {
            $cat = IngredientCategory::find($this->input('category_id'));
            if (! $cat || (int) $cat->vendor_id !== (int) $user->vendor_id) {
                return false;
            }
        }

        if ($this->filled('product_id')) {
            $product = Product::find($this->input('product_id'));
            if (! $product || (int) $product->vendor_id !== (int) $user->vendor_id) {
                return false;
            }
        }

        if ($this->filled('produced_ingredient_id')) {
            $ingredient = Ingredient::find($this->input('produced_ingredient_id'));
            if (! $ingredient || (int) $ingredient->vendor_id !== (int) $user->vendor_id) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        $vendorId = Auth::user()?->vendor_id;
        $recipeId = $this->route('recipe')?->id;

        return [
            'name' => ['required', 'string', 'max:200'],
            'name_translations' => ['nullable', 'array'],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('recipes', 'code')->where('vendor_id', $vendorId)->ignore($recipeId),
            ],
            'type' => ['required', 'in:dish,semi_finished,sauce,bakery,beverage,prep'],
            'status' => ['nullable', 'in:draft,pending_approval,approved,active,archived'],
            'product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where('vendor_id', $vendorId),
            ],
            'produced_ingredient_id' => [
                'nullable',
                Rule::exists('ingredients', 'id')->where('vendor_id', $vendorId),
            ],
            'category_id' => [
                'nullable',
                Rule::exists('ingredient_categories', 'id')->where('vendor_id', $vendorId),
            ],
            'yield_quantity' => ['required', 'numeric', 'min:0.0001'],
            'yield_unit_id' => ['required', 'exists:units,id'],
            'portion_size' => ['nullable', 'numeric', 'min:0'],
            'portion_unit_id' => ['nullable', 'exists:units,id'],
            'prep_time_minutes' => ['nullable', 'integer', 'min:0'],
            'cook_time_minutes' => ['nullable', 'integer', 'min:0'],
            'storage_conditions' => ['nullable', 'string', 'max:150'],
            'shelf_life_hours' => ['nullable', 'integer', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'target_food_cost_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'critical_control_points' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.type' => ['required_with:items', 'in:ingredient,sub_recipe'],
            'items.*.ingredient_id' => ['nullable', 'required_if:items.*.type,ingredient', 'exists:ingredients,id'],
            'items.*.sub_recipe_id' => ['nullable', 'required_if:items.*.type,sub_recipe', 'exists:recipes,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.unit_id' => ['required_with:items', 'exists:units,id'],
            'items.*.gross_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.net_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.prep_loss_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'items.*.cooking_loss_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.is_optional' => ['nullable', 'boolean'],
            'packagings' => ['nullable', 'array'],
            'packagings.*.ingredient_id' => ['required_with:packagings', 'exists:ingredients,id'],
            'packagings.*.quantity' => ['required_with:packagings', 'numeric', 'min:0.0001'],
            'packagings.*.unit_id' => ['required_with:packagings', 'exists:units,id'],
        ];
    }
}
