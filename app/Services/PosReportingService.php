<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\PosOperationLog;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Tip;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PosReportingService
{
    /**
     * Parse date range or default to today.
     *
     * @return array{Carbon, Carbon}
     */
    protected function resolveDateRange(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::today()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::today()->endOfDay();

        return [$start, $end];
    }

    /**
     * 1. Sales Analytics: Gross sales, net sales, taxes, discounts, refunds, payment breakdown, and source breakdown.
     */
    public function getSalesAnalytics(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $orderQuery = Order::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereBetween('closed_at', [$start, $end]);

        if ($locationId) {
            $orderQuery->where('location_id', $locationId);
        }

        $orders = $orderQuery->get();

        $grossSales = (float) $orders->sum('subtotal');
        $discountTotal = (float) $orders->sum('discount_amount');
        $taxTotal = (float) $orders->sum('tax_amount');
        $tipTotal = (float) $orders->sum('tip_amount');
        $netSales = (float) $orders->sum('total_amount');
        $ordersCount = $orders->count();
        $totalGuests = (int) $orders->sum('guest_count');
        $aov = $ordersCount > 0 ? round($netSales / $ordersCount, 2) : 0.0;
        $avgGuestSpend = $totalGuests > 0 ? round($netSales / $totalGuests, 2) : 0.0;

        // Payment Methods Breakdown (from pos_payments)
        $paymentQuery = PosPayment::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end]);

        if ($locationId) {
            $paymentQuery->whereHas('order', function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            });
        }

        $payments = $paymentQuery->get();

        $byPaymentMethod = [];
        foreach ($payments->groupBy('payment_method') as $method => $group) {
            $byPaymentMethod[$method] = [
                'method' => $method,
                'count' => $group->count(),
                'total' => (float) $group->sum('amount'),
                'tips' => (float) $group->sum('tip_amount'),
            ];
        }

        // Order Sources Breakdown (POS, QR Menu, Online, Takeaway, Delivery)
        $bySource = [];
        foreach ($orders->groupBy('source') as $source => $group) {
            $sourceKey = $source ?: 'pos';
            $bySource[$sourceKey] = [
                'source' => $sourceKey,
                'count' => $group->count(),
                'total' => (float) $group->sum('total_amount'),
            ];
        }

        // Hourly distribution for trend charts
        $hourly = [];
        foreach ($orders as $o) {
            $hour = $o->closed_at ? $o->closed_at->format('H:00') : '00:00';
            if (! isset($hourly[$hour])) {
                $hourly[$hour] = ['hour' => $hour, 'orders' => 0, 'sales' => 0.0];
            }
            $hourly[$hour]['orders']++;
            $hourly[$hour]['sales'] += (float) $o->total_amount;
        }
        ksort($hourly);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'summary' => [
                'gross_sales' => $grossSales,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'tip_total' => $tipTotal,
                'net_sales' => $netSales,
                'orders_count' => $ordersCount,
                'total_guests' => $totalGuests,
                'average_order_value' => $aov,
                'average_guest_spend' => $avgGuestSpend,
            ],
            'by_payment_method' => array_values($byPaymentMethod),
            'by_source' => array_values($bySource),
            'hourly_trend' => array_values($hourly),
        ];
    }

    /**
     * 2. Employee Performance Reports: Waiter and Cashier breakdown.
     */
    public function getEmployeeReports(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $orderQuery = Order::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereBetween('closed_at', [$start, $end])
            ->with(['waiter', 'cashier']);

        if ($locationId) {
            $orderQuery->where('location_id', $locationId);
        }

        $orders = $orderQuery->get();

        // 1. Waiter Breakdown
        $waiterStats = [];
        foreach ($orders as $order) {
            $waiter = $order->waiter;
            $waiterId = $waiter?->id ?: 0;
            $waiterName = $waiter?->name ?: 'Unassigned / Counter';

            if (! isset($waiterStats[$waiterId])) {
                $waiterStats[$waiterId] = [
                    'user_id' => $waiterId,
                    'name' => $waiterName,
                    'role' => 'waiter',
                    'orders_count' => 0,
                    'total_sales' => 0.0,
                    'total_tips' => 0.0,
                    'total_guests' => 0,
                ];
            }

            $waiterStats[$waiterId]['orders_count']++;
            $waiterStats[$waiterId]['total_sales'] += (float) $order->total_amount;
            $waiterStats[$waiterId]['total_tips'] += (float) ($order->tip_amount ?? 0);
            $waiterStats[$waiterId]['total_guests'] += (int) ($order->guest_count ?? 1);
        }

        // 2. Cashier Breakdown
        $cashierStats = [];
        foreach ($orders as $order) {
            $cashier = $order->cashier;
            $cashierId = $cashier?->id ?: 0;
            $cashierName = $cashier?->name ?: 'Self-checkout / System';

            if (! isset($cashierStats[$cashierId])) {
                $cashierStats[$cashierId] = [
                    'user_id' => $cashierId,
                    'name' => $cashierName,
                    'role' => 'cashier',
                    'orders_count' => 0,
                    'total_sales' => 0.0,
                    'total_discounts' => 0.0,
                ];
            }

            $cashierStats[$cashierId]['orders_count']++;
            $cashierStats[$cashierId]['total_sales'] += (float) $order->total_amount;
            $cashierStats[$cashierId]['total_discounts'] += (float) ($order->discount_amount ?? 0);
        }

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'waiters' => array_values($waiterStats),
            'cashiers' => array_values($cashierStats),
        ];
    }

    /**
     * 3. Product Performance Reports: Top sellers, volume, revenue.
     */
    public function getProductReports(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 50
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.closed_at', [$start, $end]);

        if ($locationId) {
            $query->where('orders.location_id', $locationId);
        }

        $results = $query->select(
            'order_items.product_id',
            DB::raw('COALESCE(products.name, order_items.product_name) as product_name'),
            DB::raw("COALESCE(categories.name, 'Uncategorized') as category_name"),
            DB::raw('SUM(order_items.quantity) as total_quantity'),
            DB::raw('SUM(order_items.subtotal) as total_revenue'),
            DB::raw('products.tracks_inventory'),
            DB::raw('products.stock_quantity')
        )
            ->groupBy('order_items.product_id', 'products.name', 'order_items.product_name', 'categories.name', 'products.tracks_inventory', 'products.stock_quantity')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'products' => $results->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'category_name' => $r->category_name,
                'quantity_sold' => (float) $r->total_quantity,
                'revenue' => (float) $r->total_revenue,
                'tracks_inventory' => (bool) $r->tracks_inventory,
                'current_stock' => $r->stock_quantity !== null ? (float) $r->stock_quantity : null,
            ]),
        ];
    }

    /**
     * 4. Category Performance Reports: Revenue and item volume per category.
     */
    public function getCategoryReports(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.closed_at', [$start, $end]);

        if ($locationId) {
            $query->where('orders.location_id', $locationId);
        }

        $results = $query->select(
            DB::raw('COALESCE(categories.id, 0) as category_id'),
            DB::raw("COALESCE(categories.name, 'Uncategorized') as category_name"),
            DB::raw('SUM(order_items.quantity) as total_items_sold'),
            DB::raw('SUM(order_items.subtotal) as total_revenue')
        )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        $overallRevenue = (float) $results->sum('total_revenue');

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_revenue' => $overallRevenue,
            'categories' => $results->map(fn ($r) => [
                'category_id' => (int) $r->category_id,
                'category_name' => $r->category_name,
                'items_sold' => (float) $r->total_items_sold,
                'revenue' => (float) $r->total_revenue,
                'percentage' => $overallRevenue > 0 ? round(((float) $r->total_revenue / $overallRevenue) * 100, 1) : 0,
            ]),
        ];
    }

    /**
     * 5. Cash Drawer & Shift Reconciliation Reports.
     */
    public function getCashReports(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $query = PosShift::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$start, $end])
            ->with(['user', 'closedByUser', 'device']);

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $shifts = $query->orderByDesc('created_at')->get();

        $totalCashSales = (float) $shifts->sum('total_cash_sales');
        $totalCashIn = (float) $shifts->sum('total_cash_in');
        $totalCashOut = (float) $shifts->sum('total_cash_out');
        $totalDiscrepancy = (float) $shifts->where('status', 'closed')->sum('discrepancy');

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'summary' => [
                'shifts_count' => $shifts->count(),
                'total_cash_sales' => $totalCashSales,
                'total_cash_in' => $totalCashIn,
                'total_cash_out' => $totalCashOut,
                'net_cash_flow' => $totalCashSales + $totalCashIn - $totalCashOut,
                'total_discrepancy' => $totalDiscrepancy,
            ],
            'shifts' => $shifts->map(fn (PosShift $s) => $s->generateReportPayload()),
        ];
    }

    /**
     * 6. Tip Reports: Aggregated by location, waiter, and tender.
     */
    public function getTipReports(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        $query = Tip::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$start, $end])
            ->with(['waiter']);

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $tips = $query->get();

        $totalTips = (float) $tips->sum('amount');
        $cashTips = (float) $tips->where('is_cash', true)->sum('amount');
        $cardTips = (float) $tips->where('is_cash', false)->sum('amount');

        $byWaiter = [];
        foreach ($tips->groupBy('waiter_id') as $waiterId => $group) {
            $waiter = $group->first()->waiter;
            $byWaiter[] = [
                'waiter_id' => $waiterId ?: null,
                'waiter_name' => $waiter?->name ?: 'Direct / General Pool',
                'tips_count' => $group->count(),
                'total_amount' => (float) $group->sum('amount'),
                'cash_amount' => (float) $group->where('is_cash', true)->sum('amount'),
                'card_amount' => (float) $group->where('is_cash', false)->sum('amount'),
            ];
        }

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'summary' => [
                'total_tips' => $totalTips,
                'cash_tips' => $cashTips,
                'card_tips' => $cardTips,
                'transactions_count' => $tips->count(),
            ],
            'waiters' => $byWaiter,
        ];
    }

    /**
     * 7. Discount & Refund Supervision Reports.
     */
    public function getDiscountAndRefundReports(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        [$start, $end] = $this->resolveDateRange($startDate, $endDate);

        // Discounts from Orders
        $orderQuery = Order::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('discount_amount', '>', 0)
            ->whereBetween('created_at', [$start, $end])
            ->with(['cashier']);

        if ($locationId) {
            $orderQuery->where('location_id', $locationId);
        }

        $discountOrders = $orderQuery->get();

        $byReason = [];
        foreach ($discountOrders->groupBy('discount_type') as $type => $group) {
            $byReason[] = [
                'type' => $type ?: 'manual',
                'count' => $group->count(),
                'total_discount' => (float) $group->sum('discount_amount'),
            ];
        }

        // Refunds from PosPayments
        $refundQuery = PosPayment::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('refunded_amount', '>', 0)
            ->whereBetween('created_at', [$start, $end]);

        if ($locationId) {
            $refundQuery->whereHas('order', function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            });
        }

        $refundPayments = $refundQuery->get();

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'discounts' => [
                'total_discount_amount' => (float) $discountOrders->sum('discount_amount'),
                'discounted_orders_count' => $discountOrders->count(),
                'breakdown' => $byReason,
            ],
            'refunds' => [
                'total_refund_amount' => (float) $refundPayments->sum('refunded_amount'),
                'refund_transactions_count' => $refundPayments->count(),
            ],
        ];
    }

    /**
     * 8. Operational Live Dashboard for Manager Mode.
     */
    public function getOperationalDashboard(Vendor $vendor, ?int $locationId = null): array
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        // 1. Live sales today
        $todayOrders = Order::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereBetween('closed_at', [$todayStart, $todayEnd])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->get();

        // 2. Active open tables
        $tablesQuery = RestaurantTable::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true);

        if ($locationId) {
            $tablesQuery->where('location_id', $locationId);
        }

        $tables = $tablesQuery->get();
        $occupiedCount = $tables->where('status', 'occupied')->count();
        $availableCount = $tables->where('status', 'available')->count();

        // 3. Current active shifts
        $openShifts = PosShift::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'open')
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['user', 'device'])
            ->get();

        // 4. Low stock inventory alerts
        $lowStockItems = Product::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('tracks_inventory', true)
            ->whereRaw('stock_quantity <= low_stock_threshold')
            ->select('id', 'name', 'stock_quantity', 'low_stock_threshold')
            ->take(10)
            ->get();

        // 5. Recent audit logs
        $recentLogs = PosOperationLog::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['user', 'device'])
            ->orderByDesc('id')
            ->take(15)
            ->get();

        return [
            'today' => [
                'gross_sales' => (float) $todayOrders->sum('subtotal'),
                'net_sales' => (float) $todayOrders->sum('total_amount'),
                'orders_count' => $todayOrders->count(),
                'tips' => (float) $todayOrders->sum('tip_amount'),
            ],
            'tables' => [
                'total' => $tables->count(),
                'occupied' => $occupiedCount,
                'available' => $availableCount,
            ],
            'active_shifts' => $openShifts->map(fn ($s) => [
                'shift_id' => $s->id,
                'cashier' => $s->user?->name,
                'device' => $s->device?->name,
                'opened_at' => $s->opened_at?->toIso8601String(),
                'expected_cash' => (float) $s->expected_cash,
                'sales' => (float) ($s->total_cash_sales + $s->total_card_sales),
            ]),
            'low_stock_alerts' => $lowStockItems,
            'recent_logs' => $recentLogs->map(fn ($l) => [
                'id' => $l->id,
                'action' => $l->action,
                'user' => $l->user?->name,
                'device' => $l->device?->name,
                'details' => $l->details,
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
        ];
    }
}
