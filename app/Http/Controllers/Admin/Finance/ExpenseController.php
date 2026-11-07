<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpensePaymentRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\FinancialCategory;
use App\Models\Location;
use App\Models\Supplier;
use App\Security\Permission;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_EXPENSES_VIEW);

        $vendor = $this->getActiveVendor();

        $query = Expense::where('vendor_id', $vendor->id)
            ->with(['category', 'supplier', 'location', 'createdByUser', 'approvedByUser']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('category_id')) {
            $query->where('financial_category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->input('location_id'));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhere('invoice_number', 'ilike', "%{$search}%");
            });
        }

        $expenses = $query->orderBy('expense_date', 'desc')->paginate(20);

        $categories = FinancialCategory::where('vendor_id', $vendor->id)
            ->where('type', 'expense')
            ->get();
        $locations = Location::where('vendor_id', $vendor->id)->get();
        $suppliers = Supplier::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.expenses.index', compact('expenses', 'categories', 'locations', 'suppliers'));
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::FINANCE_EXPENSES_CREATE);

        $vendor = $this->getActiveVendor();

        $categories = FinancialCategory::where('vendor_id', $vendor->id)
            ->where('type', 'expense')
            ->get();
        $locations = Location::where('vendor_id', $vendor->id)->get();
        $suppliers = Supplier::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.expenses.create', compact('categories', 'locations', 'suppliers'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $vendor = $this->getActiveVendor();

        try {
            $expense = $this->expenseService->createExpense(
                $vendor,
                $request->validated(),
                $request->user()
            );

            return redirect()->route('admin.finance.expenses.show', $expense)
                ->with('success', "Expense {$expense->expense_number} recorded successfully.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Expense $expense): View
    {
        $this->authorizePermission(Permission::FINANCE_EXPENSES_VIEW);

        $vendor = $this->getActiveVendor();
        if ((int) $expense->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $expense->load(['category', 'supplier', 'location', 'createdByUser', 'approvedByUser', 'payments.bankAccount', 'payments.cashRegister']);

        $registers = CashRegister::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $bankAccounts = BankAccount::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        return view('admin.finance.expenses.show', compact('expense', 'registers', 'bankAccounts'));
    }

    public function pay(StoreExpensePaymentRequest $request, Expense $expense): RedirectResponse
    {
        $vendor = $this->getActiveVendor();
        if ((int) $expense->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        try {
            $this->expenseService->recordPayment(
                $expense,
                $request->validated(),
                $request->user()
            );

            return redirect()->route('admin.finance.expenses.show', $expense)
                ->with('success', 'Expense payment recorded and posted to ledger.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_EXPENSES_APPROVE);

        $vendor = $this->getActiveVendor();
        if ((int) $expense->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        try {
            $this->expenseService->approveExpense($expense, $request->user());

            return back()->with('success', "Expense {$expense->expense_number} approved.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_EXPENSES_APPROVE);

        $vendor = $this->getActiveVendor();
        if ((int) $expense->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        try {
            $this->expenseService->rejectExpense(
                $expense,
                $request->user(),
                $request->input('notes', 'Rejected by manager')
            );

            return back()->with('success', "Expense {$expense->expense_number} rejected.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
