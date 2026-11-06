<?php

namespace App\Http\Requests;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreIngredientCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->vendor_id && $user->hasPermission(Permission::INGREDIENTS_CREATE);
    }

    public function rules(): array
    {
        $vendorId = Auth::user()?->vendor_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'name_translations' => ['nullable', 'array'],
            'parent_id' => [
                'nullable',
                Rule::exists('ingredient_categories', 'id')->where('vendor_id', $vendorId),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
