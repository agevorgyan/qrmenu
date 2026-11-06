<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\DTOs\CashbackTransactionDTO;
use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Models\LoyaltyAuditLog;
use App\Domains\Loyalty\Models\LoyaltyCashbackTransaction;
use App\Domains\Loyalty\Models\LoyaltyLedgerTransaction;
use App\Domains\Loyalty\Models\LoyaltyPointsAccount;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\WalletPass;
use App\Domains\Loyalty\Services\CashbackService;
use App\Domains\Loyalty\Services\PointsLedgerService;
use App\Domains\Loyalty\Services\TierService;
use App\Domains\Loyalty\Services\TransactionReconciliationService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Security\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoyaltyDashboardController extends Controller
{
    public function __construct(
        protected PointsLedgerService $pointsService,
        protected CashbackService $cashbackService,
        protected TierService $tierService,
        protected TransactionReconciliationService $reconciliationService,
    ) {}

    /**
     * Show Loyalty Management Overview Dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeLoyaltyAccess($user);
        $vendor = $user->vendor;

        $program = LoyaltyProgram::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => "{$vendor->name} Loyalty Club"]
        );

        $this->tierService->ensureDefaultTiers($vendor, $program);

        // Core KPIs
        $totalMembers = Customer::where('vendor_id', $vendor->id)->count();
        $activeMembers = LoyaltyPointsAccount::where('vendor_id', $vendor->id)
            ->where('last_activity_at', '>=', now()->subDays(30))
            ->count();

        $totalPointsIssued = (int) DB::table('loyalty_ledger_transactions')
            ->where('vendor_id', $vendor->id)
            ->where('type', LoyaltyTransactionType::Earned->value)
            ->sum('points');

        $totalPointsRedeemed = (int) DB::table('loyalty_ledger_transactions')
            ->where('vendor_id', $vendor->id)
            ->where('type', LoyaltyTransactionType::Redeemed->value)
            ->sum('points');

        $outstandingPoints = (int) LoyaltyPointsAccount::where('vendor_id', $vendor->id)->sum('current_balance');
        $outstandingCashback = (float) DB::table('loyalty_cashback_accounts')->where('vendor_id', $vendor->id)->sum('current_balance');
        $walletPassesCount = WalletPass::where('vendor_id', $vendor->id)->count();

        // Loyalty-associated revenue
        $loyaltyRevenue = (float) Order::where('vendor_id', $vendor->id)
            ->whereNotNull('customer_id')
            ->whereIn('status', ['completed', 'served'])
            ->sum('total_amount');

        // Recent Transactions
        $recentTransactions = LoyaltyLedgerTransaction::where('vendor_id', $vendor->id)
            ->with(['customer', 'order'])
            ->latest('created_at')
            ->take(10)
            ->get();

        // Tier distribution
        $tierDistribution = Customer::where('vendor_id', $vendor->id)
            ->select('loyalty_tier', DB::raw('count(*) as count'))
            ->groupBy('loyalty_tier')
            ->pluck('count', 'loyalty_tier')
            ->all();

        return view('admin.loyalty.index', compact(
            'vendor',
            'program',
            'totalMembers',
            'activeMembers',
            'totalPointsIssued',
            'totalPointsRedeemed',
            'outstandingPoints',
            'outstandingCashback',
            'walletPassesCount',
            'loyaltyRevenue',
            'recentTransactions',
            'tierDistribution'
        ));
    }

    /**
     * Show customer list with loyalty accounts, search, and filtering.
     */
    public function customers(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeLoyaltyAccess($user);
        $vendor = $user->vendor;

        $search = trim((string) $request->input('search'));
        $tier = $request->input('tier');

        $query = Customer::where('vendor_id', $vendor->id)
            ->with(['pointsAccount', 'cashbackAccount']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($tier && $tier !== 'all') {
            $query->where('loyalty_tier', $tier);
        }

        $customers = $query->latest('updated_at')->paginate(20);

        return view('admin.loyalty.customers', compact('vendor', 'customers', 'search', 'tier'));
    }

    /**
     * Show transaction history with type and customer filters.
     */
    public function transactions(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeLoyaltyAccess($user);
        $vendor = $user->vendor;

        $type = $request->input('type');
        $ledger = $request->input('ledger', 'points'); // 'points' or 'cashback'

        if ($ledger === 'cashback') {
            $query = LoyaltyCashbackTransaction::where('vendor_id', $vendor->id)->with('customer');
            if ($type && $type !== 'all') {
                $query->where('type', $type);
            }
            $transactions = $query->latest('created_at')->paginate(25);
        } else {
            $query = LoyaltyLedgerTransaction::where('vendor_id', $vendor->id)->with(['customer', 'order']);
            if ($type && $type !== 'all') {
                $query->where('type', $type);
            }
            $transactions = $query->latest('created_at')->paginate(25);
        }

        return view('admin.loyalty.transactions', compact('vendor', 'transactions', 'type', 'ledger'));
    }

    /**
     * Export transaction history to CSV.
     */
    public function exportTransactions(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $this->authorizeLoyaltyAccess($user);
        $vendor = $user->vendor;

        $ledger = $request->input('ledger', 'points');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"loyalty_{$ledger}_".date('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($vendor, $ledger) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Date', 'Customer', 'Phone', 'Type', 'Amount/Points', 'Balance Before', 'Balance After', 'Notes']);

            if ($ledger === 'cashback') {
                $rows = LoyaltyCashbackTransaction::where('vendor_id', $vendor->id)->with('customer')->cursor();
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->created_at->format('Y-m-d H:i:s'),
                        $row->customer?->name,
                        $row->customer?->phone,
                        $row->type->value,
                        $row->amount,
                        $row->balance_before,
                        $row->balance_after,
                        $row->notes,
                    ]);
                }
            } else {
                $rows = LoyaltyLedgerTransaction::where('vendor_id', $vendor->id)->with('customer')->cursor();
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->created_at->format('Y-m-d H:i:s'),
                        $row->customer?->name,
                        $row->customer?->phone,
                        $row->type->value,
                        $row->points,
                        $row->balance_before,
                        $row->balance_after,
                        $row->notes,
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Manual adjustment of customer loyalty points or cashback.
     */
    public function adjustBalance(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasPermission(Permission::LOYALTY_ADJUST) && $user->role !== 'superadmin' && $user->role !== 'owner') {
            abort(403, 'Permission loyalty.adjust required for manual balance modifications.');
        }

        $vendor = $user->vendor;
        $request->validate([
            'customer_id' => 'required|integer',
            'type' => 'required|in:points,cashback',
            'amount' => 'required|numeric',
            'notes' => 'required|string|max:255',
        ]);

        $customer = Customer::where('vendor_id', $vendor->id)->where('id', $request->input('customer_id'))->firstOrFail();
        $amount = (float) $request->input('amount');

        if ($request->input('type') === 'points') {
            $points = (int) $amount;
            if ($points >= 0) {
                $this->pointsService->creditPoints(new PointsTransactionDTO(
                    vendorId: $vendor->id,
                    customerId: $customer->id,
                    points: $points,
                    type: LoyaltyTransactionType::ManualAdjustment,
                    staffId: $user->id,
                    notes: $request->input('notes')
                ));
            } else {
                $this->pointsService->debitPoints(new PointsTransactionDTO(
                    vendorId: $vendor->id,
                    customerId: $customer->id,
                    points: abs($points),
                    type: LoyaltyTransactionType::ManualAdjustment,
                    staffId: $user->id,
                    notes: $request->input('notes')
                ));
            }
        } else {
            if ($amount >= 0) {
                $this->cashbackService->creditCashback(new CashbackTransactionDTO(
                    vendorId: $vendor->id,
                    customerId: $customer->id,
                    amount: $amount,
                    type: LoyaltyTransactionType::ManualAdjustment,
                    staffId: $user->id,
                    notes: $request->input('notes')
                ));
            } else {
                $this->cashbackService->debitCashback(new CashbackTransactionDTO(
                    vendorId: $vendor->id,
                    customerId: $customer->id,
                    amount: abs($amount),
                    type: LoyaltyTransactionType::ManualAdjustment,
                    staffId: $user->id,
                    notes: $request->input('notes')
                ));
            }
        }

        LoyaltyAuditLog::create([
            'vendor_id' => $vendor->id,
            'staff_id' => $user->id,
            'customer_id' => $customer->id,
            'action' => 'balance_manually_adjusted',
            'details' => ['type' => $request->input('type'), 'amount' => $amount, 'reason' => $request->input('notes')],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Customer balance successfully adjusted.');
    }

    /**
     * Show audit log of all loyalty administrative and staff operations.
     */
    public function auditLogs(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeLoyaltyAccess($user);
        $vendor = $user->vendor;

        $logs = LoyaltyAuditLog::where('vendor_id', $vendor->id)
            ->with(['staff', 'customer'])
            ->latest('created_at')
            ->paginate(25);

        return view('admin.loyalty.audit_logs', compact('vendor', 'logs'));
    }

    /**
     * Run ledger reconciliation check.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeLoyaltyAccess($user);
        $vendor = $user->vendor;

        $fix = (bool) $request->input('fix', false);
        $pointsDiscrepancies = $this->reconciliationService->reconcilePointsAccounts($vendor->id, $fix);
        $cashbackDiscrepancies = $this->reconciliationService->reconcileCashbackAccounts($vendor->id, $fix);

        return response()->json([
            'success' => true,
            'points_discrepancies' => $pointsDiscrepancies,
            'cashback_discrepancies' => $cashbackDiscrepancies,
            'is_healthy' => empty($pointsDiscrepancies) && empty($cashbackDiscrepancies),
        ]);
    }

    protected function authorizeLoyaltyAccess($user): void
    {
        if (! $user) {
            abort(401);
        }
        if ($user->role === 'superadmin' || $user->role === 'owner' || $user->role === 'vendor_owner') {
            return;
        }
        if ($user->hasPermission(Permission::LOYALTY_VIEW) || $user->hasPermission(Permission::LOYALTY_MANAGE)) {
            return;
        }
        abort(403);
    }
}
