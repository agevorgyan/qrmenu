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
        $users = User::where('vendor_id', $vendor->id)->get();
        $staffUsers = $users;

        return view('admin.finance.registers.index', compact('registers', 'locations', 'users', 'staffUsers', 'locationId'));
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
            ->with('success', __('Cash Register \':name\' created successfully.', ['name' => $register->name]));
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $register): RedirectResponse
    {
        $vendor = $this->getActiveVendor();
        if ((int) $register->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $this->registerService->updateRegister($register, $request->validated(), $request->user());

        return redirect()->route('admin.finance.registers.index')
            ->with('success', __('Cash Register \':name\' updated successfully.', ['name' => $register->name]));
    }

    public function toggle(CashRegister $register): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_REGISTERS_MANAGE);

        $vendor = $this->getActiveVendor();
        if ((int) $register->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        if ($register->isActive() && $register->hasActiveShift()) {
            return back()->with('error', __('Cannot deactivate a register with an open cash shift.'));
        }

        $newStatus = $register->isActive() ? 'inactive' : 'active';
        $register->update(['status' => $newStatus]);

        $statusMsg = $newStatus === 'active'
            ? __('Cash Register \':name\' activated.', ['name' => $register->name])
            : __('Cash Register \':name\' deactivated.', ['name' => $register->name]);

        return back()->with('success', $statusMsg);
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

        return back()->with('success', __('Cash drawer kick instruction recorded for :name.', ['name' => $register->name]));
    }
}
