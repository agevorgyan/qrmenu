<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrDepartmentPositionController extends Controller
{
    public function departmentsIndex(Request $request): View|JsonResponse
    {
        $this->authorize('hr.departments.view');

        $vendor = Auth::user()->vendor;
        $departments = HrDepartment::where('vendor_id', $vendor->id)
            ->with(['manager:id,name', 'positions'])
            ->withCount('employeeProfiles')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
        $managers = User::where('vendor_id', $vendor->id)->select('id', 'name')->orderBy('name')->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $departments]);
        }

        return view('admin.hr.departments.index', compact('vendor', 'departments', 'managers'));
    }

    public function storeDepartment(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.departments.manage');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'sort_order' => 'nullable|integer',
        ]);

        $department = HrDepartment::create([
            'vendor_id' => $vendor->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Department created successfully.'), 'data' => $department], 201);
        }

        return back()->with('success', __('Department created successfully.'));
    }

    public function updateDepartment(Request $request, HrDepartment $department): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.departments.manage');

        if ((int) $department->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $department->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $department->description,
            'manager_id' => array_key_exists('manager_id', $validated) ? $validated['manager_id'] : $department->manager_id,
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : $department->is_active,
            'sort_order' => $validated['sort_order'] ?? $department->sort_order,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Department updated successfully.'), 'data' => $department]);
        }

        return back()->with('success', __('Department updated successfully.'));
    }

    public function destroyDepartment(Request $request, HrDepartment $department): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.departments.manage');

        if ((int) $department->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $department->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Department deleted successfully.')]);
        }

        return back()->with('success', __('Department deleted successfully.'));
    }

    public function positionsIndex(Request $request): View|JsonResponse
    {
        $this->authorize('hr.positions.view');

        $vendor = Auth::user()->vendor;
        $positions = HrPosition::where('vendor_id', $vendor->id)
            ->with(['department:id,name'])
            ->withCount('employeeProfiles')
            ->orderBy('title', 'asc')
            ->get();

        $departments = HrDepartment::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $positions]);
        }

        return view('admin.hr.positions.index', compact('vendor', 'positions', 'departments'));
    }

    public function storePosition(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.positions.manage');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'nullable|exists:hr_departments,id',
            'description' => 'nullable|string',
            'default_pay_type' => 'required|string|in:monthly,hourly,daily,shift',
            'default_pay_rate' => 'required|numeric|min:0',
        ]);

        $position = HrPosition::create([
            'vendor_id' => $vendor->id,
            'department_id' => $validated['department_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'default_pay_type' => $validated['default_pay_type'],
            'default_pay_rate' => $validated['default_pay_rate'],
            'is_active' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Position created successfully.'), 'data' => $position], 201);
        }

        return back()->with('success', __('Position created successfully.'));
    }

    public function updatePosition(Request $request, HrPosition $position): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.positions.manage');

        if ((int) $position->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'nullable|exists:hr_departments,id',
            'description' => 'nullable|string',
            'default_pay_type' => 'required|string|in:monthly,hourly,daily,shift',
            'default_pay_rate' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $position->update([
            'title' => $validated['title'],
            'department_id' => array_key_exists('department_id', $validated) ? $validated['department_id'] : $position->department_id,
            'description' => $validated['description'] ?? $position->description,
            'default_pay_type' => $validated['default_pay_type'],
            'default_pay_rate' => $validated['default_pay_rate'],
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : $position->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Position updated successfully.'), 'data' => $position]);
        }

        return back()->with('success', __('Position updated successfully.'));
    }

    public function destroyPosition(Request $request, HrPosition $position): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.positions.manage');

        if ((int) $position->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $position->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Position deleted successfully.')]);
        }

        return back()->with('success', __('Position deleted successfully.'));
    }
}
