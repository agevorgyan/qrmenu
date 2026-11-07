@extends('layouts.app')

@section('title', __('Record New Expense') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <a href="{{ route('admin.finance.expenses.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Expenses') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-receipt" style="color: #ef4444;"></i>
                    {{ __('Record Operational Expense') }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card" style="padding: 2rem;">
        <form method="POST" action="{{ route('admin.finance.expenses.store') }}" style="display: flex; flex-direction: column; gap: 1.25rem;">
            @csrf

            <div>
                <label class="form-label">{{ __('Expense Title / Description') }} *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Monthly High-Speed Internet & POS SIM cards" required autofocus>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label class="form-label">{{ __('Category') }}</label>
                    <select name="financial_category_id" class="form-control">
                        <option value="">{{ __('General Expense') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Branch / Location') }}</label>
                    <select name="location_id" class="form-control">
                        <option value="">{{ __('All Branches (Centralized Overhead)') }}</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label class="form-label">{{ __('Total Amount') }} *</label>
                    <input type="number" step="0.01" min="0.01" name="total_amount" class="form-control" placeholder="0.00" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Included VAT / Tax Amount') }}</label>
                    <input type="number" step="0.01" min="0" name="tax_amount" class="form-control" placeholder="0.00">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label class="form-label">{{ __('Expense Date') }} *</label>
                    <input type="date" name="expense_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Due Date (Optional)') }}</label>
                    <input type="date" name="due_date" class="form-control">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label class="form-label">{{ __('Supplier / Payee (Optional)') }}</label>
                    <select name="supplier_id" class="form-control">
                        <option value="">{{ __('One-time Payee / Retail') }}</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Vendor Invoice / Tax Reference #') }}</label>
                    <input type="text" name="invoice_number" class="form-control" placeholder="e.g. INV-2026-994">
                </div>
            </div>

            <div>
                <label class="form-label">{{ __('Notes / Memo') }}</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Provide any additional accounting context or approval notes..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <a href="{{ route('admin.finance.expenses.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Save & Submit Expense') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

