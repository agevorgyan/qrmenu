<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrDepartment;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\HrScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrScheduleController extends Controller
{
    public function __construct(
        protected HrScheduleService $scheduleService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('hr.schedules.view');

        $vendor = Auth::user()->vendor;
        $locId = $request->get('location_id');
        $weekStart = $request->filled('date') ? Carbon::parse($request->get('date'))->startOfWeek() : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $shiftsQuery = WorkShift::where('vendor_id', $vendor->id)
            ->whereBetween('shift_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['user', 'location'])
            ->orderBy('shift_date', 'asc')
            ->orderBy('start_time', 'asc');

        if ($locId) {
            $shiftsQuery->where('location_id', $locId);
        }

        $shifts = $shiftsQuery->get();
        $employees = User::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $locations = $vendor->locations;
        $departments = HrDepartment::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $shifts]);
        }

        return view('admin.hr.schedules.index', compact('vendor', 'shifts', 'employees', 'locations', 'departments', 'weekStart', 'weekEnd'));
    }

    public function storeShift(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.schedules.manage');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'location_id' => 'required|exists:locations,id',
            'shift_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'cross_midnight' => 'nullable|boolean',
            'unpaid_break_minutes' => 'nullable|integer|min:0|max:180',
            'notes' => 'nullable|string',
        ]);

        $user = User::where('vendor_id', $vendor->id)->findOrFail($validated['user_id']);
        $location = Location::where('vendor_id', $vendor->id)->findOrFail($validated['location_id']);

        try {
            $shift = $this->scheduleService->createShift(
                vendor: $vendor,
                user: $user,
                location: $location,
                shiftDate: Carbon::parse($validated['shift_date']),
                startTime: $validated['start_time'],
                endTime: $validated['end_time'],
                crossMidnight: (bool) ($validated['cross_midnight'] ?? false),
                breakMinutes: (int) ($validated['unpaid_break_minutes'] ?? 30),
                creator: Auth::user()
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['shift_date' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Shift scheduled successfully.'), 'data' => $shift], 201);
        }

        return back()->with('success', __('Shift scheduled successfully.'));
    }

    public function updateShift(Request $request, WorkShift $shift): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.schedules.manage');

        if ((int) $shift->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $validated = $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'cross_midnight' => 'nullable|boolean',
            'unpaid_break_minutes' => 'nullable|integer|min:0|max:180',
            'status' => 'required|string|in:scheduled,confirmed,completed,cancelled,swapped',
            'notes' => 'nullable|string',
        ]);

        $shift->update([
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'cross_midnight' => (bool) ($validated['cross_midnight'] ?? false),
            'unpaid_break_minutes' => (int) ($validated['unpaid_break_minutes'] ?? 30),
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $shift->notes,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Shift updated successfully.'), 'data' => $shift]);
        }

        return back()->with('success', __('Shift updated successfully.'));
    }

    public function destroyShift(Request $request, WorkShift $shift): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.schedules.manage');

        if ((int) $shift->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $shift->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Shift cancelled successfully.')]);
        }

        return back()->with('success', __('Shift cancelled successfully.'));
    }

    public function copyWeek(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.schedules.manage');

        $vendor = Auth::user()->vendor;
        $validated = $request->validate([
            'source_date' => 'required|date',
            'target_date' => 'required|date|different:source_date',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $source = Carbon::parse($validated['source_date']);
        $target = Carbon::parse($validated['target_date']);
        $location = ! empty($validated['location_id']) ? Location::find($validated['location_id']) : null;

        $copied = $this->scheduleService->duplicateWeekRoster($vendor, $source, $target, $location);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __(':count shifts copied successfully.', ['count' => $copied])]);
        }

        return back()->with('success', __(':count shifts copied successfully.', ['count' => $copied]));
    }
}
