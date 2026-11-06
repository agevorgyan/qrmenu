<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Security\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->vendor_id || ! $user->hasPermission(Permission::PROCUREMENT_PAY)) {
            return false;
        }

        $supplier = Supplier::find($this->input('supplier_id'));
        if (! $supplier || (int) $supplier->vendor_id !== (int) $user->vendor_id) {
            return false;
        }

        if ($this->filled('supplier_invoice_id')) {
            $invoice = SupplierInvoice::find($this->input('supplier_invoice_id'));
            if (! $invoice || (int) $invoice->vendor_id !== (int) $user->vendor_id) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'supplier_invoice_id' => ['nullable', 'exists:supplier_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', 'in:bank_transfer,cash,card,check'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
