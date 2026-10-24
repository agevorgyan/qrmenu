<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\Vendor;
use App\Security\QuotaCatalog;
use App\Services\PreOrderService;
use App\Services\QuotaService;
use App\Services\ReservationAvailabilityService;
use App\Services\ReservationNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontReservationController extends Controller
{
    public function __construct(
        protected ReservationAvailabilityService $availabilityService,
        protected PreOrderService $preOrderService,
        protected ReservationNotificationService $notificationService,
    ) {}

    /**
     * Display the public reservation and pre-order booking page.
     */
    public function showReservationForm(Request $request, string $vendor_slug, ?string $location_slug = null): View
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->with('locations')->firstOrFail();

        if (! $vendor->isReservationFeatureEnabled()) {
            abort(404, __('Table reservations are not available for this restaurant.'));
        }

        $lang = $request->query('lang') ?: $request->query('locale');
        if ($lang && in_array(strtolower((string) $lang), ['hy', 'en', 'ru'], true)) {
            $locale = strtolower((string) $lang);
            app()->setLocale($locale);
            session(['app_locale' => $locale, 'locale' => $locale]);
        }
        Carbon::setLocale(app()->getLocale());

        $location = null;
        if ($location_slug) {
            $location = $vendor->locations->firstWhere('slug', $location_slug);
        }
        if (! $location) {
            $location = $vendor->locations->first();
        }

        $settings = $vendor->reservation_settings;
        $availableDates = $this->availabilityService->getAvailableDates($vendor, $location);

        // Fetch active menu categories only if pre-orders are enabled
        $categories = collect();
        if ($vendor->isPreOrderFeatureEnabled()) {
            $categories = Category::where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->with(['products' => function ($q) {
                    $q->where('is_available', true)
                        ->with(['variations', 'overrides'])
                        ->orderBy('sort_order', 'asc');
                }])
                ->orderBy('sort_order', 'asc')
                ->get();
        }

        return view('storefront.reserve', compact(
            'vendor',
            'location',
            'settings',
            'availableDates',
            'categories'
        ));
    }

    /**
     * API: Get available time slots and eligible tables for selected date and party size.
     */
    public function getAvailability(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->with('locations')->firstOrFail();

        if (! $vendor->isReservationFeatureEnabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Table reservations are not available for this restaurant.',
            ], 404);
        }

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'guest_count' => 'required|integer|min:1|max:50',
            'location_id' => 'nullable|integer',
            'table_id' => 'nullable|integer',
        ]);

        $location = null;
        if (! empty($validated['location_id'])) {
            $location = $vendor->locations->firstWhere('id', (int) $validated['location_id']);
        }

        $slots = $this->availabilityService->getAvailableTimeSlots(
            vendor: $vendor,
            date: $validated['date'],
            guestCount: (int) $validated['guest_count'],
            location: $location,
            preferredTableId: ! empty($validated['table_id']) ? (int) $validated['table_id'] : null
        );

        return response()->json([
            'status' => 'success',
            'date' => $validated['date'],
            'guest_count' => (int) $validated['guest_count'],
            'slots' => $slots,
        ]);
    }

    /**
     * API: Atomically create a table reservation and optional pre-order.
     */
    public function submitReservation(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->with('locations')->firstOrFail();

        if (! $vendor->isReservationFeatureEnabled()) {
            throw ValidationException::withMessages([
                'reservation' => 'Table reservations are currently not available at this restaurant.',
            ]);
        }

        $settings = $vendor->reservation_settings;

        $validated = $request->validate([
            'customer_name' => 'required|string|max:100',
            'customer_email' => 'required|email|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'locale' => 'nullable|string|max:10',
            'guest_count' => 'required|integer|min:1|max:50',
            'reservation_date' => 'required|date_format:Y-m-d',
            'reservation_time' => 'required|string|regex:/^\d{2}:\d{2}$/',
            'table_id' => 'nullable|integer',
            'special_requests' => 'nullable|string|max:1000',
            'location_id' => 'nullable|integer',
            'pre_order_items' => 'nullable|array',
            'pre_order_items.*.product_id' => 'required_with:pre_order_items|integer',
            'pre_order_items.*.quantity' => 'required_with:pre_order_items|integer|min:1',
            'pre_order_items.*.variation_id' => 'nullable|integer',
            'pre_order_items.*.variation_name' => 'nullable|string|max:100',
            'pre_order_items.*.notes' => 'nullable|string|max:255',
            'pre_order_notes' => 'nullable|string|max:500',
        ]);

        $rawItems = $validated['pre_order_items'] ?? [];
        if (! empty($rawItems) && ! $vendor->isPreOrderFeatureEnabled()) {
            throw ValidationException::withMessages([
                'pre_order' => 'Pre-orders are currently disabled for this restaurant.',
            ]);
        }

        $requestedLocale = $validated['locale'] ?? ($request->query('lang') ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy')));
        $customerLocale = in_array(strtolower((string) $requestedLocale), ['hy', 'en', 'ru'], true)
            ? strtolower((string) $requestedLocale)
            : ($vendor->getDefaultLanguageCode() ?: 'hy');

        $location = null;
        if (! empty($validated['location_id'])) {
            $location = $vendor->locations->firstWhere('id', (int) $validated['location_id']);
            if (! $location) {
                throw ValidationException::withMessages([
                    'location_id' => 'Invalid location specified.',
                ]);
            }
        } else {
            $location = $vendor->locations->first();
        }

        $durationMinutes = max(30, (int) ($settings['default_duration_minutes'] ?? 90));
        $bufferMinutes = max(0, (int) ($settings['buffer_minutes'] ?? 15));
        $autoConfirm = (bool) ($settings['auto_confirm'] ?? false);
        $initialStatus = $autoConfirm ? Reservation::STATUS_CONFIRMED : Reservation::STATUS_PENDING;

        $reservation = null;
        $preOrder = null;

        DB::transaction(function () use (
            $vendor,
            $location,
            $validated,
            $rawItems,
            $customerLocale,
            $durationMinutes,
            $bufferMinutes,
            $initialStatus,
            &$reservation,
            &$preOrder
        ) {
            // 1. Atomic table allocation with PostgreSQL row-level locks
            $allocatedTable = $this->availabilityService->allocateTableAtomic(
                vendor: $vendor,
                date: $validated['reservation_date'],
                time: $validated['reservation_time'],
                guestCount: (int) $validated['guest_count'],
                preferredTableId: ! empty($validated['table_id']) ? (int) $validated['table_id'] : null,
                location: $location,
                durationMinutes: $durationMinutes,
                bufferMinutes: $bufferMinutes
            );

            // Record reservations.period quota usage (fails closed if quota exceeded)
            app(QuotaService::class)->recordUsage(
                vendor: $vendor,
                quotaKey: QuotaCatalog::KEY_RESERVATIONS_PERIOD,
                quantity: 1,
                idempotencyKey: 'res_'.$vendor->id.'_'.$validated['reservation_date'].'_'.$validated['reservation_time'].'_'.$allocatedTable->id,
                metadata: ['date' => $validated['reservation_date'], 'time' => $validated['reservation_time']]
            );

            // 2. Persist reservation
            $reservation = new Reservation([
                'vendor_id' => $vendor->id,
                'location_id' => $location?->id,
                'customer_name' => trim($validated['customer_name']),
                'customer_email' => strtolower(trim($validated['customer_email'])),
                'customer_phone' => ! empty($validated['customer_phone']) ? trim($validated['customer_phone']) : null,
                'locale' => $customerLocale,
                'guest_count' => (int) $validated['guest_count'],
                'restaurant_table_id' => $allocatedTable->id,
                'table_number' => $allocatedTable->table_number,
                'table_name' => $allocatedTable->display_name,
                'reservation_date' => $validated['reservation_date'],
                'reservation_time' => $validated['reservation_time'],
                'duration_minutes' => $durationMinutes,
                'status' => $initialStatus,
                'special_requests' => ! empty($validated['special_requests']) ? trim($validated['special_requests']) : null,
                'cancellation_policy' => $vendor->reservation_settings['cancellation_policy'] ?? null,
            ]);
            $reservation->save();

            // 3. Attach pre-order items and enforce mandatory/disabled pre-order policies
            $rawItems = $validated['pre_order_items'] ?? [];
            $preOrder = $this->preOrderService->attachPreOrder(
                reservation: $reservation,
                rawItems: $rawItems,
                notes: ! empty($validated['pre_order_notes']) ? trim($validated['pre_order_notes']) : null
            );
        });

        // 4. Asynchronously queue customer & restaurant notifications
        try {
            $customerEvent = $initialStatus === Reservation::STATUS_CONFIRMED
                ? 'customer_confirmed'
                : 'customer_request_received';

            $this->notificationService->sendReservationNotification($reservation, $customerEvent);
            $this->notificationService->sendRestaurantReservationNotification($reservation, 'restaurant_new_request');

            if ($preOrder) {
                $this->notificationService->sendCustomerPreOrderNotification($preOrder, 'preorder_received');
                $this->notificationService->sendRestaurantPreOrderNotification($preOrder, 'restaurant_preorder_received');
            }
        } catch (\Throwable $e) {
            // Notification dispatch errors should never fail the database reservation
            report($e);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'reference' => $reservation->reference,
            'token' => $reservation->uuid,
            'details_url' => $reservation->getPublicDetailsUrl(),
            'message' => $initialStatus === Reservation::STATUS_CONFIRMED
                ? __('Your reservation is confirmed! We look forward to seeing you.')
                : __('Your reservation request has been received and is pending confirmation.'),
        ], 201);
    }

    /**
     * Display public customer reservation status and pre-order breakdown with cryptographic token check.
     */
    public function showReservationDetails(Request $request, string $vendor_slug, string $reference): View
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        $reservation = Reservation::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('reference', $reference)
            ->with(['restaurantTable', 'location', 'preOrder'])
            ->firstOrFail();

        // Cryptographic security token verification (IDOR protection)
        $token = (string) $request->query('token');
        if (empty($token) || ! hash_equals($reservation->uuid, $token)) {
            abort(403, 'Unauthorized access to reservation details.');
        }

        // Customer sees confirmation and tracking in their selected reservation language
        $locale = $reservation->locale ?: ($vendor->getDefaultLanguageCode() ?: 'hy');
        if (in_array($locale, ['hy', 'en', 'ru'], true)) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        return view('storefront.reservation_status', compact('vendor', 'reservation'));
    }

    /**
     * API: Customer self-service cancellation guarded by token and cutoff policy.
     */
    public function cancelReservation(Request $request, string $vendor_slug, string $reference): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        $reservation = Reservation::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('reference', $reference)
            ->with(['preOrder'])
            ->firstOrFail();

        // Verify cryptographic UUID token
        $token = (string) $request->input('token');
        if (empty($token) || ! hash_equals($reservation->uuid, $token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or missing reservation authorization token.',
            ], 403);
        }

        if (! $reservation->canCancel()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This reservation can no longer be cancelled.',
            ], 422);
        }

        // Check cancellation cutoff window
        $cutoffHours = (int) ($vendor->reservation_settings['cancellation_cutoff_hours'] ?? 2);
        if ($cutoffHours > 0 && $reservation->reservation_datetime) {
            $cutoffDt = $reservation->reservation_datetime->copy()->subHours($cutoffHours);
            if (Carbon::now('UTC')->gt($cutoffDt)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Reservations cannot be cancelled within {$cutoffHours} hours of arrival. Please contact the restaurant directly.",
                ], 422);
            }
        }

        $reason = $request->input('reason', 'Cancelled by customer');

        DB::transaction(function () use ($reservation, $reason) {
            $reservation->status = Reservation::STATUS_CANCELLED;
            $reservation->cancellation_reason = $reason;
            $reservation->save();

            if ($reservation->preOrder) {
                $reservation->preOrder->status = ReservationPreOrder::STATUS_CANCELLED;
                $reservation->preOrder->save();
            }
        });

        // Notify customer and restaurant
        try {
            $this->notificationService->sendReservationNotification($reservation, 'customer_cancelled');
            $this->notificationService->sendRestaurantReservationNotification($reservation, 'restaurant_cancelled');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Your reservation has been cancelled.',
        ]);
    }
}
