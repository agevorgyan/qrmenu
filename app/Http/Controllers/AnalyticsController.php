<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsLog;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('reports.view');

        $user = Auth::user();
        $vendor = $user->vendor;
        $activeLocationId = $user->location_id ?: session('active_location_id', $vendor->locations->first()?->id);

        $tz = $vendor->timezone ?: config('app.timezone', 'Asia/Yerevan');
        $days = (int) $request->get('range', 7);
        $startDate = Carbon::now($tz)->subDays($days - 1)->startOfDay()->format('Y-m-d');

        $analyticsQuery = AnalyticsLog::where('vendor_id', $vendor->id)
            ->where('visit_date', '>=', $startDate)
            ->when($activeLocationId, function ($query, $locId) {
                return $query->where('location_id', $locId);
            });

        $totalVisits = (clone $analyticsQuery)->count();
        $dineInVisits = (clone $analyticsQuery)->where('channel', 'dine_in')->count();
        $orderingVisits = (clone $analyticsQuery)->where('channel', 'ordering')->count();

        // Daily breakdown for charts
        $dailyStats = (clone $analyticsQuery)
            ->select('visit_date', 'channel', DB::raw('count(*) as count'))
            ->groupBy('visit_date', 'channel')
            ->orderBy('visit_date', 'asc')
            ->get();

        // Top dishes ordered
        $topDishes = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendor->id)
            ->select('order_items.product_name', DB::raw('SUM(order_items.quantity) as total_qty'), DB::raw('SUM(order_items.subtotal) as total_revenue'))
            ->groupBy('order_items.product_name')
            ->orderBy('total_qty', 'desc')
            ->take(5)
            ->get();

        return view('admin.analytics.index', compact(
            'vendor',
            'days',
            'totalVisits',
            'dineInVisits',
            'orderingVisits',
            'dailyStats',
            'topDishes'
        ));
    }
}
