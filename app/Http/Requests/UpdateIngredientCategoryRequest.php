<?php

namespace App\Http\Requests;

use App\Models\IngredientCategory;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateIngredientCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id || ! $user->hasPermission(Permission::INGREDIENTS_UPDATE)) {
            return false;
        }

        $category = $this->route('category');
        if (! $category instanceof IngredientCategory) {
            $category = IngredientCategory::find($category);
        }

        return $category && (int) $category->vendor_id === (int) $user->vendor_id;
    }

    public function rules(): array
    {
        $vendorId = Auth::user()?->vendor_id;
        $categoryId = $this->route('category') instanceof IngredientCategory
            ? $this->route('category')->id
            : $this->route('category');

        return [
            'name' => ['required', 'string', 'max:150'],
            'name_translations' => ['nullable', 'array'],
            'parent_id' => [
                'nullable',
                Rule::exists('ingredient_categories', 'id')->where('vendor_id', $vendorId)->whereNot('id', $categoryId),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
