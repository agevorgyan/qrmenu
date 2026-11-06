<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Location;
use App\Models\User;
use App\Services\HrAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrAttendanceController extends Controller
{
    public function __construct(
        protected HrAttendanceService $attendanceService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('hr.attendance.view');

        $vendor = Auth::user()->vendor;
        $date = $request->get('date', Carbon::today()->toDateString());
        $locId = $request->get('location_id');
        $status = $request->get('status');

        $query = AttendanceRecord::where('vendor_id', $vendor->id)
            ->whereDate('check_in_at', $date)
            ->with(['user.employeeProfile.position', 'location', 'adjustments.adjuster'])
            ->latest('check_in_at');

        if ($locId) {
            $query->where('location_id', $locId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $records = $query->paginate(25)->withQueryString();
        $locations = $vendor->locations;
        $employees = User::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $records]);
        }

        return view('admin.hr.attendance.index', compact('vendor', 'records', 'locations', 'employees', 'date'));
    }

    public function punch(Request $request): JsonResponse
    {
        $this->authorize('hr.attendance.clock');

        $validated = $request->validate([
            'action' => 'required|string|in:clock_in,clock_out,break_start,break_end',
            'pin' => 'nullable|string',
            'qr_token' => 'nullable|string',
            'location_id' => 'nullable|exists:locations,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        $vendor = $user->vendor;

        // If PIN is provided, allow employee identification at kiosk
        if (! empty($validated['pin'])) {
            $matchedUser = User::where('vendor_id', $vendor->id)->get()->first(function ($u) use ($validated) {
                return $u->verifyPin($validated['pin']);
            });

            if (! $matchedUser) {
                return response()->json(['success' => false, 'message' => __('Invalid PIN code.')], 422);
            }
            $user = $matchedUser;
        }

        // Resolve location
        $location = null;
        if (! empty($validated['qr_token'])) {
            $verified = $this->attendanceService->verifyClockQrToken($validated['qr_token']);
            if (! $verified || (int) $verified['vendor_id'] !== (int) $vendor->id) {
                return response()->json(['success' => false, 'message' => __('Invalid or expired QR token.')], 422);
            }
            $location = Location::find($verified['location_id']);
        } elseif (! empty($validated['location_id'])) {
            $location = Location::where('vendor_id', $vendor->id)->find($validated['location_id']);
        } else {
            $location = $user->location ?? $vendor->locations->first();
        }

        if (! $location) {
            return response()->json(['success' => false, 'message' => __('Branch location could not be determined.')], 422);
        }

        try {
            $source = ! empty($validated['qr_token']) ? 'qr_badge' : 'pin';
            $record = match ($validated['action']) {
                'clock_in' => $this->attendanceService->clockIn(
                    user: $user,
                    location: $location,
                    source: $source,
                    lat: $validated['latitude'] ?? null,
                    lng: $validated['longitude'] ?? null
                ),
                'clock_out' => $this->attendanceService->clockOut($user),
                'break_start' => $this->attendanceService->startBreak($user),
                'break_end' => $this->attendanceService->endBreak($user),
            };

            return response()->json([
                'success' => true,
                'message' => __(':action successful for :name.', ['action' => ucfirst(str_replace('_', ' ', $validated['action'])), 'name' => $user->name]),
                'data' => $record,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function adjust(Request $request, AttendanceRecord $record): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.attendance.manage');

        if ((int) $record->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $validated = $request->validate([
            'adjusted_check_in' => 'required|date',
            'adjusted_check_out' => 'required|date|after:adjusted_check_in',
            'reason' => 'required|string|max:500',
        ]);

        $this->attendanceService->adjustAttendance(
            record: $record,
            adjuster: Auth::user(),
            adjustedCheckIn: Carbon::parse($validated['adjusted_check_in']),
            adjustedCheckOut: Carbon::parse($validated['adjusted_check_out']),
            reason: $validated['reason']
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Attendance adjusted successfully.')]);
        }

        return back()->with('success', __('Attendance adjusted successfully.'));
    }

    public function manualEntry(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.attendance.manage');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'location_id' => 'required|exists:locations,id',
            'check_in_at' => 'required|date',
            'check_out_at' => 'required|date|after:check_in_at',
            'notes' => 'nullable|string',
        ]);

        $user = User::where('vendor_id', $vendor->id)->findOrFail($validated['user_id']);
        $location = Location::where('vendor_id', $vendor->id)->findOrFail($validated['location_id']);

        $record = AttendanceRecord::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'user_id' => $user->id,
            'check_in_at' => Carbon::parse($validated['check_in_at']),
            'check_out_at' => Carbon::parse($validated['check_out_at']),
            'source' => 'manual',
            'status' => 'present',
            'is_verified' => true,
            'verified_by' => Auth::id(),
            'notes' => $validated['notes'] ?? 'Manual manager entry',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Attendance recorded successfully.'), 'data' => $record], 201);
        }

        return back()->with('success', __('Attendance recorded successfully.'));
    }

    public function getQrToken(Request $request): JsonResponse
    {
        $this->authorize('hr.attendance.clock');

        $vendor = Auth::user()->vendor;
        $locId = $request->get('location_id');
        $location = $locId ? Location::where('vendor_id', $vendor->id)->find($locId) : $vendor->locations->first();

        if (! $location) {
            return response()->json(['success' => false, 'message' => 'No branch found.'], 404);
        }

        $token = $this->attendanceService->generateClockQrToken($vendor, $location);

        return response()->json([
            'success' => true,
            'token' => $token,
            'location_id' => $location->id,
            'expires_in' => 60,
        ]);
    }
}
