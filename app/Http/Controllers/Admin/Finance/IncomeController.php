<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreIncomeRequest;
use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\FinancialCategory;
use App\Models\Income;
use App\Models\Location;
use App\Security\Permission;
use App\Services\IncomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomeController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected IncomeService $incomeService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_INCOME_MANAGE);

        $vendor = $this->getActiveVendor();

        $query = Income::where('vendor_id', $vendor->id)
            ->with(['category', 'location', 'cashRegister', 'bankAccount', 'createdByUser']);

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->input('location_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('financial_category_id', (int) $request->input('category_id'));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('income_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhere('customer_payer', 'ilike', "%{$search}%");
            });
        }

        $incomes = $query->orderBy('income_date', 'desc')->paginate(20);

        $categories = FinancialCategory::where('vendor_id', $vendor->id)->where('type', 'income')->get();
        $locations = Location::where('vendor_id', $vendor->id)->get();
        $registers = CashRegister::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $bankAccounts = BankAccount::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        return view('admin.finance.incomes.index', compact('incomes', 'categories', 'locations', 'registers', 'bankAccounts'));
    }

    public function store(StoreIncomeRequest $request): RedirectResponse
    {
        $vendor = $this->getActiveVendor();

        try {
            $income = $this->incomeService->recordIncome(
                $vendor,
                $request->validated(),
                $request->user()
            );

            return redirect()->route('admin.finance.incomes.index')
                ->with('success', "Other Income {$income->income_number} recorded and posted to ledger.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}

