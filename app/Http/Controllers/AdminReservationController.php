<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\RestaurantTable;
use App\Models\Vendor;
use App\Services\ReservationNotificationService;
use App\Services\TenantCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function __construct(
        protected ReservationNotificationService $notificationService,
    ) {}

    /**
     * Display reservations dashboard with filters, metrics, and search.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $this->authorize('viewAny', Reservation::class);

        // Admin interface strictly uses the restaurant administration language
        $adminLocale = session('admin_locale') ?: ($vendor->admin_locale ?: 'hy');
        app()->setLocale($adminLocale);

        $tz = $vendor->timezone ?: 'Asia/Yerevan';
        $todayStr = Carbon::now($tz)->format('Y-m-d');

        $status = $request->input('status');
        $locationId = $request->input('location_id');
        $search = trim((string) $request->input('search'));

        // Resolve date range preset & boundaries (Requirement 3: upcoming 30 days default)
        $datePreset = $request->input('date_preset');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Backward compatibility for single 'date' query parameter
        if ($request->has('date') && ! $request->has('date_preset') && ! $request->has('date_from')) {
            $legacyDate = $request->input('date');
            if ($legacyDate === 'all') {
                $datePreset = 'all';
            } else {
                $datePreset = 'custom';
                $dateFrom = $legacyDate;
                $dateTo = $legacyDate;
            }
        }

        if (! $datePreset) {
            $datePreset = 'upcoming_30';
        }

        switch ($datePreset) {
            case 'today':
                $dateFrom = $todayStr;
                $dateTo = $todayStr;
                break;
            case 'tomorrow':
                $dateFrom = Carbon::tomorrow($tz)->format('Y-m-d');
                $dateTo = Carbon::tomorrow($tz)->format('Y-m-d');
                break;
            case 'next_7_days':
                $dateFrom = $todayStr;
                $dateTo = Carbon::now($tz)->addDays(7)->format('Y-m-d');
                break;
            case 'this_month':
                $dateFrom = Carbon::now($tz)->startOfMonth()->format('Y-m-d');
                $dateTo = Carbon::now($tz)->endOfMonth()->format('Y-m-d');
                break;
            case 'upcoming_30':
                $dateFrom = $todayStr;
                $dateTo = Carbon::now($tz)->addDays(30)->format('Y-m-d');
                break;
            case 'custom':
                $dateFrom = $dateFrom ?: $todayStr;
                $dateTo = $dateTo ?: Carbon::now($tz)->addDays(30)->format('Y-m-d');
                break;
            case 'all':
                $dateFrom = null;
                $dateTo = null;
                break;
            default:
                $datePreset = 'upcoming_30';
                $dateFrom = $todayStr;
                $dateTo = Carbon::now($tz)->addDays(30)->format('Y-m-d');
                break;
        }

        $query = Reservation::where('vendor_id', $vendor->id)
            ->with(['restaurantTable', 'location', 'preOrder']);

        if ($datePreset !== 'all') {
            if ($dateFrom && $dateTo) {
                $query->whereBetween('reservation_date', [$dateFrom, $dateTo]);
            } elseif ($dateFrom) {
                $query->where('reservation_date', '>=', $dateFrom);
            } elseif ($dateTo) {
                $query->where('reservation_date', '<=', $dateTo);
            }
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($locationId) {
            $query->where('location_id', (int) $locationId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'ilike', "%{$search}%")
                    ->orWhere('customer_name', 'ilike', "%{$search}%")
                    ->orWhere('customer_email', 'ilike', "%{$search}%")
                    ->orWhere('customer_phone', 'ilike', "%{$search}%")
                    ->orWhere('table_number', 'ilike', "%{$search}%");
            });
        }

        // Sort upcoming reservations chronologically
        $reservations = $query->orderBy('reservation_date', 'asc')
            ->orderBy('reservation_time', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(25)
            ->withQueryString();

        // Metrics calculations
        $periodQuery = Reservation::where('vendor_id', $vendor->id);
        if ($datePreset !== 'all' && $dateFrom && $dateTo) {
            $periodQuery->whereBetween('reservation_date', [$dateFrom, $dateTo]);
        }

        $metrics = [
            'period_bookings' => (clone $periodQuery)->count(),
            'total_today' => Reservation::where('vendor_id', $vendor->id)->where('reservation_date', $todayStr)->count(),
            'pending' => Reservation::where('vendor_id', $vendor->id)->where('status', Reservation::STATUS_PENDING)->count(),
            'confirmed' => (clone $periodQuery)->where('status', Reservation::STATUS_CONFIRMED)->count(),
            'seated' => Reservation::where('vendor_id', $vendor->id)->where('reservation_date', $todayStr)->where('status', Reservation::STATUS_SEATED)->count(),
            'preorders_count' => ReservationPreOrder::where('vendor_id', $vendor->id)->whereHas('reservation', function ($q) use ($datePreset, $dateFrom, $dateTo) {
                if ($datePreset !== 'all' && $dateFrom && $dateTo) {
                    $q->whereBetween('reservation_date', [$dateFrom, $dateTo]);
                }
            })->count(),
        ];

        $locations = $vendor->locations;
        $tables = RestaurantTable::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('table_number')->get();

        return view('admin.reservations.index', compact(
            'vendor',
            'reservations',
            'metrics',
            'locations',
            'tables',
            'datePreset',
            'dateFrom',
            'dateTo',
            'status',
            'locationId',
            'search',
            'todayStr'
        ));
    }

    /**
     * Show reservation details JSON for live inspection drawer.
     */
    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);

        $vendor = Auth::user()->vendor;
        if ((int) $reservation->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized cross-vendor reservation access.');
        }

        $reservation->load(['restaurantTable', 'location', 'preOrder']);

        return response()->json([
            'status' => 'success',
            'reservation' => [
                'id' => $reservation->id,
                'uuid' => $reservation->uuid,
                'reference' => $reservation->reference,
                'customer_name' => $reservation->customer_name,
                'customer_email' => $reservation->customer_email,
                'customer_phone' => $reservation->customer_phone,
                'guest_count' => $reservation->guest_count,
                'table_number' => $reservation->table_number,
                'table_name' => $reservation->table_name,
                'reservation_date' => $reservation->reservation_date,
                'reservation_time' => $reservation->getFormattedTime(),
                'duration_minutes' => $reservation->duration_minutes ?: 90,
                'status' => $reservation->status,
                'notes' => $reservation->notes,
                'special_requests' => $reservation->special_requests,
                'cancellation_reason' => $reservation->cancellation_reason,
                'pre_order' => $reservation->preOrder ? [
                    'id' => $reservation->preOrder->id,
                    'status' => $reservation->preOrder->status,
                    'items' => $reservation->preOrder->items,
                    'subtotal' => (float) $reservation->preOrder->subtotal,
                    'total_amount' => (float) $reservation->preOrder->total_amount,
                    'currency' => $reservation->preOrder->currency,
                    'notes' => $reservation->preOrder->notes,
                    'summary' => $reservation->preOrder->getFormattedSummary(app()->getLocale()),
                ] : null,
                'created_at' => $reservation->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Update reservation status with validation and notification dispatch.
     */
    public function updateStatus(Request $request, Reservation $reservation): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $reservation);

        $vendor = Auth::user()->vendor;
        if ((int) $reservation->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized cross-vendor reservation access.');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,seated,completed,cancelled,no_show,rejected',
            'table_id' => 'nullable|integer',
            'cancellation_reason' => 'nullable|string|max:500',
            'preorder_status' => 'nullable|in:pending,confirmed,preparing,ready,cancelled',
        ]);

        $oldStatus = $reservation->status;
        $newStatus = $validated['status'];

        // Enforce valid state transitions
        if (in_array($oldStatus, [Reservation::STATUS_CANCELLED, Reservation::STATUS_REJECTED]) && ! in_array($newStatus, [Reservation::STATUS_CANCELLED, Reservation::STATUS_REJECTED])) {
            throw ValidationException::withMessages([
                'status' => 'Cancelled or rejected reservations cannot be reopened.',
            ]);
        }

        // Table reassignment check
        if (! empty($validated['table_id']) && (int) $validated['table_id'] !== (int) $reservation->restaurant_table_id) {
            $newTable = RestaurantTable::where('vendor_id', $vendor->id)->find($validated['table_id']);
            if ($newTable) {
                $reservation->restaurant_table_id = $newTable->id;
                $reservation->table_number = $newTable->table_number;
                $reservation->table_name = $newTable->display_name;
            }
        }

        $reservation->status = $newStatus;
        if (! empty($validated['cancellation_reason'])) {
            $reservation->cancellation_reason = trim($validated['cancellation_reason']);
        }
        $reservation->save();

        // Update pre-order status if requested or cancel it if reservation cancelled/rejected
        if ($reservation->preOrder) {
            if (in_array($newStatus, [Reservation::STATUS_CANCELLED, Reservation::STATUS_REJECTED])) {
                $reservation->preOrder->status = ReservationPreOrder::STATUS_CANCELLED;
                $reservation->preOrder->save();
            } elseif (! empty($validated['preorder_status'])) {
                $reservation->preOrder->status = $validated['preorder_status'];
                $reservation->preOrder->save();
            }
        }

        // Dispatch notifications on status change
        try {
            if ($newStatus === Reservation::STATUS_CONFIRMED && $oldStatus !== Reservation::STATUS_CONFIRMED) {
                $this->notificationService->sendReservationNotification($reservation, 'customer_confirmed');
            } elseif ($newStatus === Reservation::STATUS_REJECTED && $oldStatus !== Reservation::STATUS_REJECTED) {
                $this->notificationService->sendReservationNotification($reservation, 'customer_rejected');
            } elseif ($newStatus === Reservation::STATUS_CANCELLED && $oldStatus !== Reservation::STATUS_CANCELLED) {
                $this->notificationService->sendReservationNotification($reservation, 'customer_cancelled');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Reservation status updated to :status.', ['status' => ucfirst($newStatus)]),
                'reservation_status' => $newStatus,
            ]);
        }

        return redirect()->back()->with('success', __('Reservation status updated successfully.'));
    }

    /**
     * Display reservation settings page.
     */
    public function settingsIndex(): View
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('manageSettings', $vendor);
        $adminLocale = session('admin_locale') ?: ($vendor->admin_locale ?: 'hy');
        app()->setLocale($adminLocale);

        $settings = $vendor->reservation_settings;
        $superAdminPermittedMode = $vendor->getSuperAdminPermittedMode();
        $effectiveMode = $vendor->getEffectiveReservationMode();
        $reservationDisabledReason = $vendor->getReservationDisabledReason();
        $preOrderDisabledReason = $vendor->getPreOrderDisabledReason();

        return view('admin.settings.reservations', compact(
            'vendor',
            'settings',
            'superAdminPermittedMode',
            'effectiveMode',
            'reservationDisabledReason',
            'preOrderDisabledReason',
            'adminLocale'
        ));
    }

    /**
     * Update restaurant reservation settings and booking rules.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('manageSettings', $vendor);

        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:disabled,reservation_only,reservation_and_preorder'],
            'admin_locale' => ['nullable', 'string', 'in:hy,en,ru'],
            'enabled' => 'nullable|boolean',
            'auto_confirm' => 'nullable|boolean',
            'default_duration_minutes' => 'required|integer|min:30|max:360',
            'buffer_minutes' => 'required|integer|min:0|max:120',
            'min_party_size' => 'required|integer|min:1|max:50',
            'max_party_size' => 'required|integer|min:1|max:100',
            'advance_booking_days' => 'required|integer|min:1|max:180',
            'booking_cutoff_minutes' => 'required|integer|min:0|max:1440',
            'slot_interval_minutes' => 'required|integer|in:15,30,60',
            'opening_time' => 'required|date_format:H:i',
            'closing_time' => 'required|date_format:H:i',
            'operating_days' => 'nullable|array',
            'pre_orders_mode' => 'required|in:disabled,optional,mandatory',
            'cancellation_cutoff_hours' => 'required|integer|min:0|max:72',
            'cancellation_policy' => 'nullable|string|max:2000',
            'reservation_notes' => 'nullable|string|max:2000',
        ]);

        if ($validated['min_party_size'] > $validated['max_party_size']) {
            throw ValidationException::withMessages([
                'min_party_size' => 'Minimum party size cannot exceed maximum party size.',
            ]);
        }

        // Determine requested mode
        $requestedMode = $validated['mode'] ?? null;
        if (! $requestedMode) {
            $enabled = $request->boolean('enabled');
            if (! $enabled) {
                $requestedMode = Vendor::RESERVATION_MODE_DISABLED;
            } elseif ($validated['pre_orders_mode'] === 'disabled') {
                $requestedMode = Vendor::RESERVATION_MODE_RESERVATION_ONLY;
            } else {
                $requestedMode = Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER;
            }
        }

        $maxPermitted = $vendor->getMaxPermittedReservationMode();

        // 1. Authoritative Super Admin / Plan restrictions on reservations
        if ($maxPermitted === Vendor::RESERVATION_MODE_DISABLED && $requestedMode !== Vendor::RESERVATION_MODE_DISABLED) {
            $reason = $vendor->getReservationDisabledReason() === 'subscription'
                ? __('Table reservations are not included in your current subscription plan.')
                : __('Table reservations are disabled by Super Admin for your restaurant.');

            throw ValidationException::withMessages(['mode' => $reason]);
        }

        // 2. Authoritative Super Admin / Plan restrictions on preorders
        if ($maxPermitted === Vendor::RESERVATION_MODE_RESERVATION_ONLY && $requestedMode === Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER) {
            $reason = $vendor->getPreOrderDisabledReason() === 'subscription'
                ? __('Pre-orders are not included in your current subscription plan.')
                : __('Pre-orders are disabled by Super Admin for your restaurant.');

            throw ValidationException::withMessages(['mode' => $reason]);
        }

        // 3. Dependency rule: pre-orders cannot be enabled when reservations are disabled
        $enabled = $requestedMode !== Vendor::RESERVATION_MODE_DISABLED;
        if (! $enabled) {
            $preOrdersMode = 'disabled';
        } elseif ($requestedMode === Vendor::RESERVATION_MODE_RESERVATION_ONLY) {
            $preOrdersMode = 'disabled';
        } else {
            $preOrdersMode = $validated['pre_orders_mode'] === 'disabled' ? 'optional' : $validated['pre_orders_mode'];
        }

        $adminLocale = ! empty($validated['admin_locale']) ? strtolower($validated['admin_locale']) : ($vendor->admin_locale ?: 'hy');
        session(['admin_locale' => $adminLocale]);
        if ($user = Auth::user()) {
            $user->locale = $adminLocale;
            $user->saveQuietly();
        }

        $operatingDays = array_map('intval', $request->input('operating_days', [0, 1, 2, 3, 4, 5, 6]));

        $currentSettings = $vendor->reservation_settings;
        $updatedSettings = array_merge($currentSettings, [
            'mode' => $requestedMode,
            'enabled' => $enabled,
            'admin_locale' => $adminLocale,
            'auto_confirm' => $request->boolean('auto_confirm'),
            'default_duration_minutes' => (int) $validated['default_duration_minutes'],
            'buffer_minutes' => (int) $validated['buffer_minutes'],
            'min_party_size' => (int) $validated['min_party_size'],
            'max_party_size' => (int) $validated['max_party_size'],
            'advance_booking_days' => (int) $validated['advance_booking_days'],
            'booking_cutoff_minutes' => (int) $validated['booking_cutoff_minutes'],
            'slot_interval_minutes' => (int) $validated['slot_interval_minutes'],
            'opening_time' => $validated['opening_time'],
            'closing_time' => $validated['closing_time'],
            'operating_days' => $operatingDays,
            'pre_orders_mode' => $preOrdersMode,
            'cancellation_cutoff_hours' => (int) $validated['cancellation_cutoff_hours'],
            'cancellation_policy' => $validated['cancellation_policy'] ?? '',
            'reservation_notes' => $validated['reservation_notes'] ?? '',
        ]);

        $vendor->update(['reservation_settings' => $updatedSettings]);

        TenantCache::invalidateAll($vendor);

        return redirect()->back()->with('success', __('Reservation settings updated successfully.'));
    }
}
