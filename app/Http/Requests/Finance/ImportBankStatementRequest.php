<?php

namespace App\Http\Requests\Finance;

use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ImportBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->vendor_id && $user->hasPermission(Permission::FINANCE_BANK_MANAGE);
    }

    public function rules(): array
    {
        return [
            'statement_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'raw_csv' => ['nullable', 'string'],
        ];
    }
}
