<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\ReservationAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminTableController extends Controller
{
    public function __construct(
        protected ReservationAvailabilityService $availabilityService,
    ) {}

    /**
     * Display restaurant tables list and capacity management.
     */
    public function index(Request $request): View
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('viewAny', RestaurantTable::class);
        $adminLocale = session('admin_locale') ?: ($vendor->admin_locale ?: 'hy');
        app()->setLocale($adminLocale);

        $this->availabilityService->ensureTablesExistForVendor($vendor);

        if ($request->has('location_id')) {
            $locationId = $request->input('location_id');
        } else {
            $locationId = session('active_location_id', $vendor->locations->first()?->id);
        }

        $section = $request->input('section');

        $query = RestaurantTable::where('vendor_id', $vendor->id)
            ->with(['location'])
            ->withCount(['reservations' => fn ($q) => $q->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED, Reservation::STATUS_SEATED])])
            ->orderBy('section')
            ->orderBy('sort_order')
            ->orderBy('table_number');

        if ($locationId !== null && $locationId !== '') {
            $query->where('location_id', (int) $locationId);
        }

        if ($section) {
            $query->where('section', $section);
        }

        $tables = $query->paginate(25)->withQueryString();

        $sections = RestaurantTable::where('vendor_id', $vendor->id)
            ->whereNotNull('section')
            ->distinct()
            ->pluck('section');

        $locations = $vendor->locations;

        return view('admin.tables.index', compact('vendor', 'tables', 'sections', 'locations', 'locationId', 'section'));
    }

    /**
     * Store a newly created restaurant table.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', RestaurantTable::class);

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'table_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('restaurant_tables')->where(function ($query) use ($vendor, $request) {
                    return $query->where('vendor_id', $vendor->id)
                        ->where('location_id', $request->input('location_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'name' => 'nullable|string|max:100',
            'capacity' => 'required|integer|min:1|max:50',
            'min_capacity' => 'required|integer|min:1|max:50',
            'section' => 'nullable|string|max:100',
            'shape' => ['nullable', Rule::in(['square', 'round', 'rectangle'])],
            'is_active' => 'nullable|boolean',
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('vendor_id', $vendor->id)],
        ]);

        if ($validated['min_capacity'] > $validated['capacity']) {
            throw ValidationException::withMessages([
                'min_capacity' => 'Minimum capacity cannot exceed maximum capacity.',
            ]);
        }

        RestaurantTable::create([
            'vendor_id' => $vendor->id,
            'location_id' => ! empty($validated['location_id']) ? (int) $validated['location_id'] : null,
            'table_number' => trim($validated['table_number']),
            'name' => ! empty($validated['name']) ? trim($validated['name']) : null,
            'capacity' => (int) $validated['capacity'],
            'min_capacity' => (int) $validated['min_capacity'],
            'section' => ! empty($validated['section']) ? trim($validated['section']) : 'Main Hall',
            'shape' => $validated['shape'] ?? 'square',
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.tables.index')->with('success', __('Table created successfully.'));
    }

    /**
     * Update an existing restaurant table.
     */
    public function update(Request $request, RestaurantTable $table): RedirectResponse
    {
        $this->authorize('update', $table);

        $vendor = Auth::user()->vendor;
        if ((int) $table->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized cross-vendor table modification.');
        }

        $validated = $request->validate([
            'table_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('restaurant_tables')->where(function ($query) use ($vendor, $request, $table) {
                    return $query->where('vendor_id', $vendor->id)
                        ->where('location_id', $request->input('location_id', $table->location_id))
                        ->where('id', '!=', $table->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'name' => 'nullable|string|max:100',
            'capacity' => 'required|integer|min:1|max:50',
            'min_capacity' => 'required|integer|min:1|max:50',
            'section' => 'nullable|string|max:100',
            'shape' => ['nullable', Rule::in(['square', 'round', 'rectangle'])],
            'is_active' => 'nullable|boolean',
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('vendor_id', $vendor->id)],
        ]);

        if ($validated['min_capacity'] > $validated['capacity']) {
            throw ValidationException::withMessages([
                'min_capacity' => 'Minimum capacity cannot exceed maximum capacity.',
            ]);
        }

        $table->update([
            'table_number' => trim($validated['table_number']),
            'name' => ! empty($validated['name']) ? trim($validated['name']) : null,
            'capacity' => (int) $validated['capacity'],
            'min_capacity' => (int) $validated['min_capacity'],
            'section' => ! empty($validated['section']) ? trim($validated['section']) : $table->section,
            'shape' => $validated['shape'] ?? $table->shape,
            'is_active' => $request->boolean('is_active'),
            'location_id' => ! empty($validated['location_id']) ? (int) $validated['location_id'] : null,
        ]);

        return redirect()->route('admin.tables.index')->with('success', __('Table updated successfully.'));
    }

    /**
     * Toggle table active status.
     */
    public function toggleActive(RestaurantTable $table): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $table);

        $vendor = Auth::user()->vendor;
        if ((int) $table->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized table access.');
        }

        $table->is_active = ! $table->is_active;
        $table->save();

        if (request()->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'is_active' => $table->is_active,
                'message' => __('Table status updated.'),
            ]);
        }

        return redirect()->back()->with('success', __('Table status toggled.'));
    }

    /**
     * Remove a table if no active reservations exist.
     */
    public function destroy(RestaurantTable $table): RedirectResponse
    {
        $this->authorize('delete', $table);

        $vendor = Auth::user()->vendor;
        if ((int) $table->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized table deletion.');
        }

        $hasActiveBookings = Reservation::where('restaurant_table_id', $table->id)
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED, Reservation::STATUS_SEATED])
            ->where('reservation_datetime', '>=', now())
            ->exists();

        if ($hasActiveBookings) {
            return redirect()->back()->with('error', __('Cannot delete table with active upcoming reservations.'));
        }

        $table->delete();

        return redirect()->route('admin.tables.index')->with('success', __('Table deleted successfully.'));
    }

    /**
     * Re-sync tables from visual floor plan data.
     */
    public function syncFloorPlan(): RedirectResponse
    {
        $this->authorize('create', RestaurantTable::class);

        $vendor = Auth::user()->vendor;
        $this->availabilityService->ensureTablesExistForVendor($vendor);

        return redirect()->route('admin.tables.index')->with('success', __('Tables synchronized successfully.'));
    }
}
