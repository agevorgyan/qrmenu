<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CashMovementRequest;
use App\Http\Requests\Finance\CloseCashShiftRequest;
use App\Http\Requests\Finance\OpenCashShiftRequest;
use App\Models\CashRegister;
use App\Models\Location;
use App\Models\PosShift;
use App\Security\Permission;
use App\Services\CashMovementService;
use App\Services\CashShiftService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashShiftController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected CashShiftService $shiftService,
        protected CashMovementService $movementService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_SHIFTS_VIEW);

        $vendor = $this->getActiveVendor();

        $query = PosShift::where('vendor_id', $vendor->id)
            ->with(['user', 'location', 'cashRegister', 'managerApprovedByUser']);

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->input('location_id'));
        }

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', (int) $request->input('cash_register_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $date = Carbon::parse($request->input('date'));
            $query->whereDate('opened_at', $date);
        }

        $shifts = $query->orderBy('id', 'desc')->paginate(20);
        $locations = Location::where('vendor_id', $vendor->id)->get();
        $registers = CashRegister::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.shifts.index', compact('shifts', 'locations', 'registers'));
    }

    public function show(PosShift $shift): View
    {
        $this->authorizePermission(Permission::FINANCE_SHIFTS_VIEW);

        $vendor = $this->getActiveVendor();
        if ((int) $shift->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $shift->load(['user', 'location', 'cashRegister', 'cashMovements.user', 'payments', 'managerApprovedByUser']);

        $expectedBreakdown = $this->shiftService->calculateExpectedCashBreakdown($shift);
        $zReport = $shift->status === 'closed' ? $this->shiftService->generateZReport($shift) : null;

        return view('admin.finance.shifts.show', compact('shift', 'expectedBreakdown', 'zReport'));
    }

    public function open(OpenCashShiftRequest $request): RedirectResponse
    {
        $vendor = $this->getActiveVendor();
        $register = CashRegister::findOrFail($request->input('cash_register_id'));

        if ((int) $register->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        try {
            $shift = $this->shiftService->openShift(
                $vendor,
                [
                    'cash_register_id' => $register->id,
                    'opening_float' => (float) $request->input('opening_float'),
                    'notes' => $request->input('notes'),
                ],
                $request->user(),
                $register
            );

            return redirect()->route('admin.finance.shifts.show', $shift)
                ->with('success', "Cash shift #{$shift->id} opened on {$register->name}.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function close(CloseCashShiftRequest $request, PosShift $shift): RedirectResponse
    {
        $vendor = $this->getActiveVendor();
        if ((int) $shift->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        try {
            $this->shiftService->closeShift(
                $shift,
                (float) $request->input('actual_cash'),
                $request->user(),
                $request->input('notes')
            );

            return redirect()->route('admin.finance.shifts.show', $shift)
                ->with('success', "Cash shift #{$shift->id} closed successfully.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function movement(CashMovementRequest $request, PosShift $shift): RedirectResponse
    {
        $vendor = $this->getActiveVendor();
        if ((int) $shift->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        try {
            $type = $request->input('type');
            $amount = (float) $request->input('amount');
            $reason = $request->input('reason');

            if ($type === 'cash_in') {
                $this->movementService->recordCashIn($shift, $amount, $reason, $request->user());
            } elseif ($type === 'cash_out') {
                $this->movementService->recordCashOut($shift, $amount, $reason, $request->user());
            } elseif ($type === 'safe_drop') {
                $this->movementService->recordSafeDrop($shift, $amount, $reason, $request->user());
            }

            return back()->with('success', 'Cash movement recorded successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, PosShift $shift): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_SHIFTS_APPROVE);

        $vendor = $this->getActiveVendor();
        if ((int) $shift->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        try {
            $this->shiftService->approveShiftVariance(
                $shift,
                $request->user(),
                $request->input('approval_notes', 'Discrepancy approved by manager.')
            );

            return back()->with('success', 'Shift variance approved and posted to General Ledger.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
