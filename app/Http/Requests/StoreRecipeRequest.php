<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id) {
            return false;
        }

        if (! $user->hasPermission(Permission::RECIPES_CREATE)) {
            return false;
        }

        if ($this->filled('category_id')) {
            $cat = Category::find($this->input('category_id'));
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

    protected function prepareForValidation(): void
    {
        if ($this->has('items') && is_array($this->items)) {
            $items = $this->items;
            foreach ($items as $k => $item) {
                $qty = $item['quantity'] ?? $item['gross_weight'] ?? null;
                if ($qty !== null && $qty !== '') {
                    $items[$k]['quantity'] = $qty;
                    $items[$k]['gross_weight'] = $qty;
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    public function rules(): array
    {
        $vendorId = Auth::user()?->vendor_id;

        return [
            'name' => ['required', 'string', 'max:200'],
            'name_translations' => ['nullable', 'array'],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('recipes', 'code')->where('vendor_id', $vendorId),
            ],
            'type' => ['required', 'in:dish,semi_finished,sauce,bakery,beverage,prep'],
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
                Rule::exists('categories', 'id')->where('vendor_id', $vendorId),
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
            'items' => ['nullable', 'array'],
            'items.*.type' => ['required_with:items', 'in:ingredient,sub_recipe'],
            'items.*.ingredient_id' => ['nullable', 'required_if:items.*.type,ingredient', 'exists:ingredients,id'],
            'items.*.sub_recipe_id' => ['nullable', 'required_if:items.*.type,sub_recipe', 'exists:recipes,id'],
            'items.*.gross_weight' => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'items.*.unit_id' => ['required_with:items', 'exists:units,id'],
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

    public function messages(): array
    {
        return [
            'name.required' => __('The recipe name is required.'),
            'code.required' => __('The recipe code is required.'),
            'code.unique' => __('The recipe code has already been taken.'),
            'type.required' => __('The recipe type is required.'),
            'yield_quantity.required' => __('Yield quantity is required.'),
            'yield_quantity.min' => __('Yield quantity must be at least 0.0001.'),
            'yield_unit_id.required' => __('Yield unit is required.'),
            'items.*.ingredient_id.required_if' => __('Please select an ingredient for all recipe rows.'),
            'items.*.sub_recipe_id.required_if' => __('Please select a sub-recipe for all recipe rows.'),
            'items.*.gross_weight.required_with' => __('Ingredient quantity is required.'),
            'items.*.gross_weight.min' => __('Ingredient quantity must be greater than 0.'),
            'items.*.quantity.min' => __('Ingredient quantity must be greater than 0.'),
            'items.*.unit_id.required_with' => __('Unit of measure is required for all ingredients.'),
            'packagings.*.ingredient_id.required_with' => __('Please select a packaging item.'),
            'packagings.*.quantity.required_with' => __('Packaging quantity is required.'),
            'packagings.*.quantity.min' => __('Packaging quantity must be greater than 0.'),
            'packagings.*.unit_id.required_with' => __('Unit is required for packaging item.'),
        ];
    }
}
