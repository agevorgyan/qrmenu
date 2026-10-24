<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\WaiterCall;
use App\Services\ReservationAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FloorPlanController extends Controller
{
    public function __construct(
        protected ReservationAvailabilityService $availabilityService,
    ) {}

    /**
     * Display the visual interactive restaurant halls and tables floor plan.
     */
    public function index(Request $request): View
    {
        $this->authorize('locations.view');

        $user = Auth::user();
        $vendor = $user->vendor;
        $adminLocale = session('admin_locale') ?: ($vendor->admin_locale ?: 'hy');
        app()->setLocale($adminLocale);

        $activeLocationId = $user->location_id ?: session('active_location_id', $vendor->locations->first()?->id);
        $location = ($activeLocationId ? $vendor->locations->firstWhere('id', (int) $activeLocationId) : null) ?? $vendor->locations->first();

        // Ensure tables exist in database so floor plan and reservations share the exact same records
        $this->availabilityService->ensureTablesExistForVendor($vendor, $location);

        $floorPlanData = $vendor->getFloorPlanData();
        $halls = $this->resolveHalls($vendor, $floorPlanData);

        $tablesData = $this->buildEnrichedTablesData($vendor, $location, $halls);

        $tables = $tablesData['tables'];
        $totalTables = count($tables);
        $availableCount = $tablesData['available_count'];
        $occupiedCount = $tablesData['occupied_count'];
        $reservedCount = $tablesData['reserved_count'];
        $waiterCallCount = $tablesData['waiter_call_count'];
        $mergedCount = $tablesData['merged_count'];

        return view('admin.floor_plan.index', compact(
            'vendor',
            'location',
            'halls',
            'tables',
            'totalTables',
            'availableCount',
            'occupiedCount',
            'reservedCount',
            'waiterCallCount',
            'mergedCount'
        ));
    }

    /**
     * Save updated visual floor plan coordinates and halls layout.
     */
    public function saveFloorPlan(Request $request): JsonResponse
    {
        $this->authorize('locations.manage');

        $vendor = Auth::user()->vendor;

        $request->validate([
            'halls' => 'nullable|array',
            'tables' => 'required|array',
            'tables.*.id' => 'required',
            'tables.*.x' => 'required|numeric',
            'tables.*.y' => 'required|numeric',
        ]);

        $rawHalls = $request->input('halls', []);
        $normalizedHalls = $this->normalizeHallsInput($rawHalls);

        $rawTables = $request->input('tables', []);
        $normalizedTables = [];

        DB::transaction(function () use ($vendor, $rawTables, &$normalizedTables) {
            foreach ($rawTables as $t) {
                $id = $t['id'] ?? null;
                $num = (string) ($t['number'] ?? $id);

                // Find matching RestaurantTable
                $dbTable = null;
                if (is_numeric($id)) {
                    $dbTable = RestaurantTable::where('vendor_id', $vendor->id)->find($id);
                }
                if (! $dbTable) {
                    $dbTable = RestaurantTable::where('vendor_id', $vendor->id)
                        ->where('table_number', $num)
                        ->first();
                }

                $shape = in_array($t['shape'] ?? '', ['round', 'rectangle', 'square']) ? $t['shape'] : 'square';
                $x = (int) round(floatval($t['x'] ?? 50));
                $y = (int) round(floatval($t['y'] ?? 50));
                $section = (string) ($t['section'] ?? $t['hall'] ?? ($dbTable?->section ?: __('Main Hall')));

                if ($dbTable) {
                    $dbTable->update([
                        'shape' => $shape,
                        'x' => $x,
                        'y' => $y,
                        'section' => $section,
                    ]);
                    $tableRecordId = $dbTable->id;
                } else {
                    $tableRecordId = $id;
                }

                $normalizedTables[] = [
                    'id' => $tableRecordId,
                    'number' => $num,
                    'hall_id' => (string) ($t['hall_id'] ?? Str::slug($section)),
                    'section' => $section,
                    'name' => (string) ($t['name'] ?? ($dbTable?->name ?: 'Table '.$num)),
                    'capacity' => intval($t['capacity'] ?? ($dbTable?->capacity ?: 4)),
                    'shape' => $shape,
                    'x' => $x,
                    'y' => $y,
                ];
            }
        });

        $vendor->update([
            'floor_plan_data' => [
                'halls' => $normalizedHalls,
                'tables' => $normalizedTables,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => __('Table floor plan layout saved successfully.'),
        ]);
    }

    /**
     * Store a newly created table directly into database from the floor plan.
     */
    public function storeTable(Request $request): JsonResponse
    {
        $this->authorize('locations.manage');

        $vendor = Auth::user()->vendor;
        $location = $vendor->locations->first();

        $validated = $request->validate([
            'table_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('restaurant_tables')->where(function ($query) use ($vendor, $location) {
                    return $query->where('vendor_id', $vendor->id)
                        ->where('location_id', $location?->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'name' => 'nullable|string|max:100',
            'capacity' => 'required|integer|min:1|max:50',
            'min_capacity' => 'required|integer|min:1|max:50',
            'section' => 'required|string|max:100',
            'shape' => ['required', Rule::in(['square', 'round', 'rectangle'])],
            'x' => 'nullable|integer',
            'y' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validated['min_capacity'] > $validated['capacity']) {
            throw ValidationException::withMessages([
                'min_capacity' => __('Minimum capacity cannot exceed maximum capacity.'),
            ]);
        }

        $table = RestaurantTable::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location?->id,
            'table_number' => trim($validated['table_number']),
            'name' => ! empty($validated['name']) ? trim($validated['name']) : null,
            'capacity' => (int) $validated['capacity'],
            'min_capacity' => (int) $validated['min_capacity'],
            'section' => trim($validated['section']),
            'shape' => $validated['shape'],
            'x' => intval($validated['x'] ?? 60),
            'y' => intval($validated['y'] ?? 60),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncTableToVendorFloorPlan($vendor, $table);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => __('Table created successfully.'),
            'table' => $this->formatTableResponse($table, $vendor),
        ]);
    }

    /**
     * Update an existing table directly from the floor plan.
     */
    public function updateTable(Request $request, RestaurantTable $table): JsonResponse
    {
        $this->authorize('locations.manage');

        $vendor = Auth::user()->vendor;
        if ((int) $table->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized table update.');
        }

        $validated = $request->validate([
            'table_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('restaurant_tables')->where(function ($query) use ($vendor, $table) {
                    return $query->where('vendor_id', $vendor->id)
                        ->where('location_id', $table->location_id)
                        ->where('id', '!=', $table->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'name' => 'nullable|string|max:100',
            'capacity' => 'required|integer|min:1|max:50',
            'min_capacity' => 'required|integer|min:1|max:50',
            'section' => 'required|string|max:100',
            'shape' => ['required', Rule::in(['square', 'round', 'rectangle'])],
            'is_active' => 'nullable|boolean',
        ]);

        if ($validated['min_capacity'] > $validated['capacity']) {
            throw ValidationException::withMessages([
                'min_capacity' => __('Minimum capacity cannot exceed maximum capacity.'),
            ]);
        }

        $table->update([
            'table_number' => trim($validated['table_number']),
            'name' => ! empty($validated['name']) ? trim($validated['name']) : null,
            'capacity' => (int) $validated['capacity'],
            'min_capacity' => (int) $validated['min_capacity'],
            'section' => trim($validated['section']),
            'shape' => $validated['shape'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        // If table was merged, recalculate combined capacity
        if ($table->isMerged() && $table->merged_with_id) {
            $partner = RestaurantTable::find($table->merged_with_id);
            if ($partner) {
                $combined = $table->capacity + $partner->capacity;
                $table->update(['combined_capacity' => $combined]);
                $partner->update(['combined_capacity' => $combined]);
            }
        }

        $this->syncTableToVendorFloorPlan($vendor, $table);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => __('Table updated successfully.'),
            'table' => $this->formatTableResponse($table->fresh(['mergedWith']), $vendor),
        ]);
    }

    /**
     * Delete a table if no active reservations or orders exist.
     */
    public function destroyTable(RestaurantTable $table): JsonResponse
    {
        $this->authorize('locations.manage');

        $vendor = Auth::user()->vendor;
        if ((int) $table->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized table deletion.');
        }

        $hasActiveBookings = Reservation::where('restaurant_table_id', $table->id)
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED, Reservation::STATUS_SEATED])
            ->where('reservation_datetime', '>=', now()->subHours(4))
            ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => __('Cannot delete table with active upcoming reservations.'),
            ], 422);
        }

        // Check active dine-in orders
        $numClean = trim(strtolower((string) $table->table_number));
        $hasActiveOrders = Order::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->where(function ($q) use ($numClean, $table) {
                $q->where('table_number', $table->table_number)
                    ->orWhereRaw('LOWER(TRIM(table_number)) = ?', [$numClean]);
            })
            ->exists();

        if ($hasActiveOrders) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => __('Cannot delete table with active kitchen orders.'),
            ], 422);
        }

        // If merged, unmerge first
        if ($table->isMerged()) {
            $table->unmerge();
        }

        $tableId = $table->id;
        $table->delete();

        // Remove from vendor floor plan cache
        $floorPlan = $vendor->getFloorPlanData();
        if (! empty($floorPlan['tables'])) {
            $floorPlan['tables'] = array_values(array_filter($floorPlan['tables'], function ($t) use ($tableId) {
                return (string) ($t['id'] ?? '') !== (string) $tableId;
            }));
            $vendor->update(['floor_plan_data' => $floorPlan]);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => __('Table deleted successfully.'),
        ]);
    }

    /**
     * Combine two tables into a joined group for large reservations.
     */
    public function combineTables(Request $request): JsonResponse
    {
        $this->authorize('locations.manage');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'table_id_1' => 'required|integer',
            'table_id_2' => 'required|integer|different:table_id_1',
        ]);

        $table1 = RestaurantTable::where('vendor_id', $vendor->id)->findOrFail($validated['table_id_1']);
        $table2 = RestaurantTable::where('vendor_id', $vendor->id)->findOrFail($validated['table_id_2']);

        // Check if either table is already merged with another
        if ($table1->isMerged() && (int) $table1->merged_with_id !== (int) $table2->id) {
            $table1->unmerge();
        }
        if ($table2->isMerged() && (int) $table2->merged_with_id !== (int) $table1->id) {
            $table2->unmerge();
        }

        $table1->mergeWith($table2);

        // Position table2 visually next to table1 if far apart
        if (abs($table2->x - $table1->x) > 220 || abs($table2->y - $table1->y) > 220) {
            $table2->update([
                'x' => $table1->x + 130,
                'y' => $table1->y,
            ]);
        }

        $this->syncTableToVendorFloorPlan($vendor, $table1);
        $this->syncTableToVendorFloorPlan($vendor, $table2);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => __('Tables combined successfully. Combined capacity: :cap seats.', ['cap' => $table1->combined_capacity]),
            'table1' => $this->formatTableResponse($table1->fresh(['mergedWith']), $vendor),
            'table2' => $this->formatTableResponse($table2->fresh(['mergedWith']), $vendor),
        ]);
    }

    /**
     * Uncombine / separate merged tables back into individual tables.
     */
    public function uncombineTable(RestaurantTable $table): JsonResponse
    {
        $this->authorize('locations.manage');

        $vendor = Auth::user()->vendor;
        if ((int) $table->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized table uncombine.');
        }

        $partnerId = $table->merged_with_id;
        $table->unmerge();

        $this->syncTableToVendorFloorPlan($vendor, $table);
        if ($partnerId) {
            $partner = RestaurantTable::find($partnerId);
            if ($partner) {
                $this->syncTableToVendorFloorPlan($vendor, $partner);
            }
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => __('Tables separated successfully.'),
            'table' => $this->formatTableResponse($table->fresh(['mergedWith']), $vendor),
        ]);
    }

    /**
     * Manage halls: add, edit, or delete halls and persist to vendor floor plan.
     */
    public function saveHalls(Request $request): JsonResponse
    {
        $this->authorize('locations.manage');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'halls' => 'required|array|min:1',
            'halls.*.id' => 'required|string',
            'halls.*.name' => 'required|string|max:100',
            'halls.*.color' => 'nullable|string|max:30',
        ]);

        $normalizedHalls = [];
        $allowedIds = [];
        foreach ($validated['halls'] as $hall) {
            $hallId = Str::slug($hall['id'] ?: $hall['name']);
            if (empty($hallId)) {
                $hallId = 'hall-'.count($normalizedHalls);
            }
            $normalizedHalls[] = [
                'id' => $hallId,
                'name' => trim($hall['name']),
                'color' => $hall['color'] ?: '#3b82f6',
            ];
            $allowedIds[] = $hallId;
        }

        $fallbackHall = $normalizedHalls[0];

        // Ensure any RestaurantTable whose section was deleted is moved to primary fallback hall
        $hallsMap = collect($normalizedHalls)->keyBy('id');
        $existingTables = RestaurantTable::where('vendor_id', $vendor->id)->get();
        foreach ($existingTables as $t) {
            $sectionSlug = Str::slug($t->section ?: '');
            if (! empty($t->section) && ! $hallsMap->has($sectionSlug) && ! collect($normalizedHalls)->contains('name', $t->section)) {
                $t->update(['section' => $fallbackHall['name']]);
            }
        }

        $floorPlan = $vendor->getFloorPlanData();
        $floorPlan['halls'] = $normalizedHalls;
        $vendor->update(['floor_plan_data' => $floorPlan]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => __('Halls updated successfully.'),
            'halls' => $normalizedHalls,
        ]);
    }

    /**
     * Real-time polling endpoint for floor plan status.
     */
    public function liveStatus(Request $request): JsonResponse
    {
        $this->authorize('locations.view');

        $user = Auth::user();
        $vendor = $user->vendor;
        $activeLocationId = $user->location_id ?: session('active_location_id', $vendor->locations->first()?->id);
        $location = ($activeLocationId ? $vendor->locations->firstWhere('id', (int) $activeLocationId) : null) ?? $vendor->locations->first();

        $floorPlanData = $vendor->getFloorPlanData();
        $halls = $this->resolveHalls($vendor, $floorPlanData);

        $tablesData = $this->buildEnrichedTablesData($vendor, $location, $halls);

        return response()->json([
            'status' => 'success',
            'tables' => $tablesData['tables'],
            'counts' => [
                'total' => count($tablesData['tables']),
                'available' => $tablesData['available_count'],
                'occupied' => $tablesData['occupied_count'],
                'reserved' => $tablesData['reserved_count'],
                'waiter_calls' => $tablesData['waiter_call_count'],
                'merged' => $tablesData['merged_count'],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Resolve halls from floor plan or construct standard defaults.
     *
     * @return array<int, array{id: string, name: string, color: string}>
     */
    protected function resolveHalls($vendor, array $floorPlanData): array
    {
        $rawHalls = $floorPlanData['halls'] ?? [];
        if (! empty($rawHalls)) {
            return $this->normalizeHallsInput($rawHalls);
        }

        return [
            ['id' => 'main', 'name' => __('Main Hall'), 'color' => '#3b82f6'],
            ['id' => 'terrace', 'name' => __('Terrace / Balcony'), 'color' => '#10b981'],
            ['id' => 'vip', 'name' => __('VIP Room'), 'color' => '#f59e0b'],
        ];
    }

    /**
     * Normalize halls from varied input formats.
     *
     * @return array<int, array{id: string, name: string, color: string}>
     */
    protected function normalizeHallsInput(array $rawHalls): array
    {
        $normalized = [];
        foreach ($rawHalls as $hall) {
            if (is_array($hall)) {
                $id = (string) ($hall['id'] ?? Str::slug($hall['name'] ?? 'hall-'.count($normalized)));
                $normalized[] = [
                    'id' => $id,
                    'name' => (string) ($hall['name'] ?? 'Hall '.(count($normalized) + 1)),
                    'color' => (string) ($hall['color'] ?? '#3b82f6'),
                ];
            } elseif (is_string($hall)) {
                $normalized[] = [
                    'id' => Str::slug($hall),
                    'name' => $hall,
                    'color' => '#3b82f6',
                ];
            }
        }

        return $normalized;
    }

    /**
     * Build unified and real-time operational table dataset.
     *
     * @return array{tables: array, available_count: int, occupied_count: int, reserved_count: int, waiter_call_count: int, merged_count: int}
     */
    protected function buildEnrichedTablesData($vendor, $location, array $halls): array
    {
        $tz = $vendor->timezone ?: 'Asia/Yerevan';

        // 1. Fetch DB tables
        $dbTables = RestaurantTable::where('vendor_id', $vendor->id)
            ->when($location, fn ($q) => $q->where(fn ($sub) => $sub->where('location_id', $location->id)->orWhereNull('location_id')))
            ->with(['mergedWith'])
            ->orderBy('sort_order')
            ->orderBy('table_number')
            ->get();

        // 2. Fetch live active orders (pending, preparing, ready)
        $activeOrders = Order::where('vendor_id', $vendor->id)
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->with('items:id,order_id,quantity')
            ->latest()
            ->get();

        $ordersByTable = [];
        foreach ($activeOrders as $order) {
            $cleanedTable = trim(strtolower((string) $order->table_number));
            $digitsOnly = preg_replace('/[^0-9]/', '', $cleanedTable);
            if (! isset($ordersByTable[$cleanedTable])) {
                $ordersByTable[$cleanedTable] = $order;
            }
            if ($digitsOnly && ! isset($ordersByTable[$digitsOnly])) {
                $ordersByTable[$digitsOnly] = $order;
            }
        }

        // 3. Fetch active waiter calls
        $waiterCalls = WaiterCall::where('vendor_id', $vendor->id)
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->where('status', 'pending')
            ->latest()
            ->get();

        $waiterCallsByTable = [];
        foreach ($waiterCalls as $call) {
            $cleanedTable = trim(strtolower((string) $call->table_number));
            $digitsOnly = preg_replace('/[^0-9]/', '', $cleanedTable);
            $waiterCallsByTable[$cleanedTable] = $call;
            if ($digitsOnly) {
                $waiterCallsByTable[$digitsOnly] = $call;
            }
        }

        // 4. Fetch today's reservations
        $todayStartUtc = Carbon::today($tz)->utc();
        $todayEndUtc = Carbon::today($tz)->endOfDay()->utc();

        $todayReservations = Reservation::where('vendor_id', $vendor->id)
            ->when($location, fn ($q) => $q->where(fn ($sub) => $sub->where('location_id', $location->id)->orWhereNull('location_id')))
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED, Reservation::STATUS_SEATED])
            ->whereBetween('reservation_datetime', [$todayStartUtc, $todayEndUtc])
            ->orderBy('reservation_datetime')
            ->get();

        $reservationsByTableId = [];
        foreach ($todayReservations as $res) {
            if ($res->restaurant_table_id) {
                // Keep seated reservation or earliest upcoming reservation
                if (! isset($reservationsByTableId[$res->restaurant_table_id]) || $res->status === Reservation::STATUS_SEATED) {
                    $reservationsByTableId[$res->restaurant_table_id] = $res;
                }
            }
        }

        // Hall name to id map
        $hallsMap = collect($halls)->keyBy(fn ($h) => Str::slug($h['name']));

        $tables = [];
        $occupiedCount = 0;
        $reservedCount = 0;
        $waiterCallCount = 0;
        $mergedCount = 0;

        foreach ($dbTables as $index => $table) {
            $numKey = trim(strtolower((string) $table->table_number));
            $digitsKey = preg_replace('/[^0-9]/', '', $numKey);

            $matchedOrder = $ordersByTable[$numKey] ?? ($digitsKey ? ($ordersByTable[$digitsKey] ?? null) : null);
            $matchedCall = $waiterCallsByTable[$numKey] ?? ($digitsKey ? ($waiterCallsByTable[$digitsKey] ?? null) : null);
            $matchedReservation = $reservationsByTableId[$table->id] ?? null;

            // Check if merged partner has active order/reservation
            $partnerTable = $table->mergedWith;
            if ($table->isMerged()) {
                $mergedCount++;
                if (! $matchedOrder && $partnerTable) {
                    $pNumKey = trim(strtolower((string) $partnerTable->table_number));
                    $pDigitsKey = preg_replace('/[^0-9]/', '', $pNumKey);
                    $matchedOrder = $ordersByTable[$pNumKey] ?? ($pDigitsKey ? ($ordersByTable[$pDigitsKey] ?? null) : null);
                }
                if (! $matchedReservation && $partnerTable && isset($reservationsByTableId[$partnerTable->id])) {
                    $matchedReservation = $reservationsByTableId[$partnerTable->id];
                }
            }

            // Determine status
            $status = 'available';
            $orderData = null;
            $reservationData = null;

            if ($matchedOrder) {
                $status = 'occupied';
                $occupiedCount++;
                $orderData = [
                    'id' => $matchedOrder->id,
                    'order_number' => $matchedOrder->order_number,
                    'status' => $matchedOrder->status,
                    'total_amount' => (float) $matchedOrder->total_amount,
                    'items_count' => $matchedOrder->items->sum('quantity'),
                    'elapsed_minutes' => $matchedOrder->created_at->diffInMinutes(),
                    'created_at_time' => $matchedOrder->created_at->timezone($tz)->format('H:i'),
                    'customer_name' => $matchedOrder->customer_name ?: __('Guest'),
                    'payment_status' => $matchedOrder->payment_status,
                ];
            } elseif ($matchedReservation && $matchedReservation->status === Reservation::STATUS_SEATED) {
                $status = 'occupied';
                $occupiedCount++;
                $reservationData = [
                    'id' => $matchedReservation->id,
                    'customer_name' => $matchedReservation->customer_name,
                    'customer_phone' => $matchedReservation->customer_phone,
                    'guest_count' => $matchedReservation->guest_count,
                    'time' => $matchedReservation->reservation_datetime->timezone($tz)->format('H:i'),
                    'status' => 'seated',
                    'status_label' => __('Seated Guest'),
                    'special_requests' => $matchedReservation->special_requests,
                ];
            } elseif ($matchedReservation) {
                $status = 'reserved';
                $reservedCount++;
                $reservationData = [
                    'id' => $matchedReservation->id,
                    'customer_name' => $matchedReservation->customer_name,
                    'customer_phone' => $matchedReservation->customer_phone,
                    'guest_count' => $matchedReservation->guest_count,
                    'time' => $matchedReservation->reservation_datetime->timezone($tz)->format('H:i'),
                    'status' => $matchedReservation->status,
                    'status_label' => $matchedReservation->status === Reservation::STATUS_CONFIRMED ? __('Confirmed') : __('Pending Confirmation'),
                    'special_requests' => $matchedReservation->special_requests,
                ];
            }

            $hasCall = (bool) $matchedCall;
            $callData = null;
            if ($hasCall) {
                $waiterCallCount++;
                $callData = [
                    'id' => $matchedCall->id,
                    'type' => $matchedCall->type,
                    'type_label' => $matchedCall->type_label,
                    'created_at_time' => $matchedCall->created_at->timezone($tz)->format('H:i'),
                ];
            }

            // Hall mapping
            $section = $table->section ?: ($halls[0]['name'] ?? __('Main Hall'));
            $hallSlug = Str::slug($section);
            $matchedHall = $hallsMap->get($hallSlug) ?? ($halls[0] ?? ['id' => 'main', 'name' => $section, 'color' => '#3b82f6']);

            // Coordinate fallback
            $col = $index % 4;
            $row = floor($index / 4);
            $x = $table->x ?? ($col * 160 + 40);
            $y = $table->y ?? ($row * 150 + 40);

            $tables[] = [
                'id' => $table->id,
                'number' => (string) $table->table_number,
                'name' => (string) ($table->name ?: 'Table '.$table->table_number),
                'display_name' => $table->display_name,
                'capacity' => (int) $table->capacity,
                'min_capacity' => (int) $table->min_capacity,
                'combined_capacity' => $table->combined_capacity,
                'effective_capacity' => $table->effective_capacity,
                'shape' => in_array($table->shape, ['round', 'rectangle', 'square']) ? $table->shape : 'square',
                'section' => $section,
                'hall_id' => $matchedHall['id'],
                'hall_name' => $matchedHall['name'],
                'hall_color' => $matchedHall['color'],
                'x' => (int) $x,
                'y' => (int) $y,
                'is_active' => (bool) $table->is_active,
                'is_merged' => $table->isMerged(),
                'merged_with_id' => $table->merged_with_id,
                'merged_with_number' => $partnerTable?->table_number,
                'status' => $status,
                'order' => $orderData,
                'reservation' => $reservationData,
                'has_waiter_call' => $hasCall,
                'waiter_call' => $callData,
            ];
        }

        $availableCount = max(0, count($tables) - $occupiedCount - $reservedCount);

        return [
            'tables' => $tables,
            'available_count' => $availableCount,
            'occupied_count' => $occupiedCount,
            'reserved_count' => $reservedCount,
            'waiter_call_count' => $waiterCallCount,
            'merged_count' => $mergedCount,
        ];
    }

    /**
     * Format single table for JSON responses.
     */
    protected function formatTableResponse(RestaurantTable $table, $vendor): array
    {
        return [
            'id' => $table->id,
            'number' => (string) $table->table_number,
            'name' => (string) ($table->name ?: 'Table '.$table->table_number),
            'display_name' => $table->display_name,
            'capacity' => (int) $table->capacity,
            'min_capacity' => (int) $table->min_capacity,
            'combined_capacity' => $table->combined_capacity,
            'effective_capacity' => $table->effective_capacity,
            'shape' => in_array($table->shape, ['round', 'rectangle', 'square']) ? $table->shape : 'square',
            'section' => (string) ($table->section ?: __('Main Hall')),
            'hall_id' => Str::slug($table->section ?: 'main'),
            'x' => (int) ($table->x ?? 50),
            'y' => (int) ($table->y ?? 50),
            'is_active' => (bool) $table->is_active,
            'is_merged' => $table->isMerged(),
            'merged_with_id' => $table->merged_with_id,
            'merged_with_number' => $table->mergedWith?->table_number,
            'status' => 'available',
        ];
    }

    /**
     * Helper to keep vendor floor plan JSON in sync with restaurant_tables model.
     */
    protected function syncTableToVendorFloorPlan($vendor, RestaurantTable $table): void
    {
        $floorPlan = $vendor->getFloorPlanData();
        $tables = $floorPlan['tables'] ?? [];
        $found = false;

        foreach ($tables as &$t) {
            if ((string) ($t['id'] ?? '') === (string) $table->id || (string) ($t['number'] ?? '') === (string) $table->table_number) {
                $t['id'] = $table->id;
                $t['number'] = (string) $table->table_number;
                $t['name'] = (string) ($table->name ?: 'Table '.$table->table_number);
                $t['capacity'] = $table->capacity;
                $t['shape'] = $table->shape;
                $t['hall_id'] = Str::slug($table->section ?: 'main');
                $t['section'] = $table->section;
                $found = true;
                break;
            }
        }
        unset($t);

        if (! $found) {
            $tables[] = [
                'id' => $table->id,
                'number' => (string) $table->table_number,
                'name' => (string) ($table->name ?: 'Table '.$table->table_number),
                'capacity' => $table->capacity,
                'shape' => $table->shape,
                'hall_id' => Str::slug($table->section ?: 'main'),
                'section' => $table->section,
                'x' => $table->x ?? 60,
                'y' => $table->y ?? 60,
            ];
        }

        $floorPlan['tables'] = $tables;
        $vendor->update(['floor_plan_data' => $floorPlan]);
    }
}
