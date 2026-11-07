<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCashRegisterRequest;
use App\Http\Requests\Finance\UpdateCashRegisterRequest;
use App\Models\CashRegister;
use App\Models\Location;
use App\Models\User;
use App\Security\Permission;
use App\Services\CashDrawerService;
use App\Services\CashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected CashRegisterService $registerService,
        protected CashDrawerService $drawerService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_REGISTERS_MANAGE);

        $vendor = $this->getActiveVendor();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        $registers = CashRegister::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['location', 'assignedUser', 'activeShift.user', 'account'])
            ->orderBy('id', 'desc')
            ->paginate(20);

        $locations = Location::where('vendor_id', $vendor->id)->get();
        $staffUsers = User::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.registers.index', compact('registers', 'locations', 'staffUsers', 'locationId'));
    }

    public function store(StoreCashRegisterRequest $request): RedirectResponse
    {
        $vendor = $this->getActiveVendor();

        $register = $this->registerService->createRegister(
            $vendor,
            $request->validated(),
            $request->user()
        );

        return redirect()->route('admin.finance.registers.index')
            ->with('success', "Cash Register '{$register->name}' created successfully.");
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $register): RedirectResponse
    {
        $vendor = $this->getActiveVendor();
        if ((int) $register->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $register->update($request->validated());

        return redirect()->route('admin.finance.registers.index')
            ->with('success', "Cash Register '{$register->name}' updated successfully.");
    }

    public function toggle(CashRegister $register): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_REGISTERS_MANAGE);

        $vendor = $this->getActiveVendor();
        if ((int) $register->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        if ($register->is_active && $register->hasActiveShift()) {
            return back()->with('error', 'Cannot deactivate a register with an open cash shift.');
        }

        $register->update(['is_active' => ! $register->is_active]);

        $status = $register->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Cash Register '{$register->name}' {$status}.");
    }

    public function kickDrawer(Request $request, CashRegister $register): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_SHIFTS_MANAGE);

        $vendor = $this->getActiveVendor();
        if ((int) $register->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $this->drawerService->recordHardwareKick(
            $register,
            $request->user(),
            $request->input('reason', 'Manual drawer open')
        );

        return back()->with('success', "Cash drawer kick instruction recorded for {$register->name}.");
    }
}
