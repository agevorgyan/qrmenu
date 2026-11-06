<?php

namespace App\Http\Requests;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->vendor_id && $user->hasPermission(Permission::INGREDIENTS_CREATE);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash'],
            'name' => ['required', 'string', 'max:100'],
            'name_translations' => ['nullable', 'array'],
            'type' => ['required', 'in:mass,volume,piece,packaging'],
            'base_unit_id' => ['nullable', 'exists:units,id'],
            'base_factor' => ['nullable', 'numeric', 'min:0.000001'],
        ];
    }
}
