<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\User;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id) {
            return false;
        }

        if (! $user->hasPermission(Permission::INVENTORY_VIEW)) {
            return false;
        }

        if ($this->filled('location_id')) {
            $loc = Location::find($this->input('location_id'));
            if (! $loc || (int) $loc->vendor_id !== (int) $user->vendor_id) {
                return false;
            }
        }

        if ($this->filled('responsible_user_id')) {
            $resp = User::find($this->input('responsible_user_id'));
            if (! $resp || (int) $resp->vendor_id !== (int) $user->vendor_id) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        $vendorId = Auth::user()?->vendor_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where('vendor_id', $vendorId),
            ],
            'type' => ['required', 'in:main,kitchen,cold_room,freezer,production,finished_goods,bar'],
            'address' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('vendor_id', $vendorId),
            ],
            'storage_conditions' => ['nullable', 'string', 'max:100'],
            'temperature_min' => ['nullable', 'numeric', 'between:-50,50'],
            'temperature_max' => ['nullable', 'numeric', 'between:-50,50'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }
}

