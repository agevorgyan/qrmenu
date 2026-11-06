<?php

namespace App\Http\Requests;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreUnitConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->vendor_id && $user->hasPermission(Permission::INGREDIENTS_UPDATE);
    }

    public function rules(): array
    {
        $vendorId = Auth::user()?->vendor_id;

        return [
            'ingredient_id' => [
                'nullable',
                Rule::exists('ingredients', 'id')->where('vendor_id', $vendorId),
            ],
            'from_unit_id' => ['required', 'exists:units,id'],
            'to_unit_id' => ['required', 'exists:units,id', 'different:from_unit_id'],
            'factor' => ['required', 'numeric', 'min:0.000001'],
        ];
    }
}
