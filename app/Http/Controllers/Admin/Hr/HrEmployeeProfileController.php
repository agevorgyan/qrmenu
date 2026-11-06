<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\Location;
use App\Models\User;
use App\Services\HrEmployeeService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HrEmployeeProfileController extends Controller
{
    public function __construct(
        protected HrEmployeeService $employeeService,
        protected StorageService $storageService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('employees.view');

        $vendor = Auth::user()->vendor;
        $search = $request->get('search');
        $deptId = $request->get('department_id');
        $posId = $request->get('position_id');
        $status = $request->get('status');
        $locId = $request->get('location_id');

        $query = User::where('vendor_id', $vendor->id)
            ->with(['employeeProfile.department', 'employeeProfile.position', 'location'])
            ->orderBy('name', 'asc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($locId) {
            $query->where('location_id', $locId);
        }

        if ($deptId || $posId || $status) {
            $query->whereHas('employeeProfile', function ($q) use ($deptId, $posId, $status) {
                if ($deptId) {
                    $q->where('department_id', $deptId);
                }
                if ($posId) {
                    $q->where('position_id', $posId);
                }
                if ($status) {
                    $q->where('employment_status', $status);
                }
            });
        }

        $employees = $query->paginate(20)->withQueryString();
        $departments = HrDepartment::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $positions = HrPosition::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $locations = $vendor->locations;

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $employees]);
        }

        return view('admin.hr.employees.index', compact('vendor', 'employees', 'departments', 'positions', 'locations'));
    }

    public function show(Request $request, User $employee): View|JsonResponse
    {
        $this->authorize('employees.view');

        $vendor = Auth::user()->vendor;
        if ((int) $employee->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $profile = $employee->employeeProfile()->firstOrCreate(
            ['user_id' => $employee->id],
            [
                'vendor_id' => $vendor->id,
                'hired_at' => $employee->created_at ? $employee->created_at->toDateString() : now()->toDateString(),
                'employment_status' => $employee->is_active ? 'active' : 'suspended',
            ]
        );

        $employee->load([
            'employeeProfile.department',
            'employeeProfile.position',
            'employeeProfile.manager',
            'location',
            'employmentContracts' => fn ($q) => $q->latest('start_date'),
            'employeeDocuments',
            'workShifts' => fn ($q) => $q->latest('shift_date')->limit(10),
            'attendanceRecords' => fn ($q) => $q->latest('check_in_at')->limit(10),
            'timesheets' => fn ($q) => $q->latest('period_start')->limit(6),
            'leaveRequests' => fn ($q) => $q->latest('start_date')->limit(10),
            'leaveBalances.leaveType',
            'payrollItems.period',
            'employeeAdvances' => fn ($q) => $q->latest('requested_date'),
        ]);

        $departments = HrDepartment::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $positions = HrPosition::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $locations = $vendor->locations;
        $managers = User::where('vendor_id', $vendor->id)->whereIn('role', ['vendor_owner', 'manager'])->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $employee]);
        }

        return view('admin.hr.employees.show', compact('vendor', 'employee', 'profile', 'departments', 'positions', 'locations', 'managers'));
    }

    public function update(Request $request, User $employee): RedirectResponse|JsonResponse
    {
        $this->authorize('employees.manage');

        if ((int) $employee->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        if ($request->filled('employee_code') && ! $request->filled('internal_employee_code')) {
            $request->merge(['internal_employee_code' => $request->input('employee_code')]);
        }
        if ($request->filled('birth_date') && ! $request->filled('date_of_birth')) {
            $request->merge(['date_of_birth' => $request->input('birth_date')]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:hr_departments,id',
            'position_id' => 'nullable|exists:hr_positions,id',
            'location_id' => 'nullable|exists:locations,id',
            'manager_id' => 'nullable|exists:users,id',
            'national_id_ssn' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_relation' => 'nullable|string|max:100',
            'internal_employee_code' => 'nullable|string|max:100',
            'employment_status' => 'required|string|in:active,on_leave,suspended,terminated',
            'employment_type' => 'required|string|in:full_time,part_time,contract,seasonal,temporary,internship',
            'hired_at' => 'required|date',
            'probation_ends_at' => 'nullable|date',
            'terminated_at' => 'nullable|date',
            'termination_reason' => 'nullable|string',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'pin_code' => 'nullable|string|min:4|max:10',
        ]);

        $userUpdates = [
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? $employee->phone,
            'is_active' => $validated['employment_status'] === 'active',
        ];

        if (array_key_exists('location_id', $validated)) {
            $userUpdates['location_id'] = $validated['location_id'];
        }

        $employee->update($userUpdates);

        if (! empty($validated['pin_code'])) {
            $employee->setPin($validated['pin_code']);
        }

        $profile = $employee->employeeProfile;
        $profileData = $validated;
        unset($profileData['pin_code'], $profileData['location_id']);
        $profile->update($profileData);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Employee profile updated successfully.'), 'data' => $employee->fresh('employeeProfile')]);
        }

        return back()->with('success', __('Employee profile updated successfully.'));
    }

    public function transfer(Request $request, User $employee): RedirectResponse|JsonResponse
    {
        $this->authorize('employees.manage');

        if ((int) $employee->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        if ($request->filled('change_reason') && ! $request->filled('reason')) {
            $request->merge(['reason' => $request->input('change_reason')]);
        }
        if ($request->filled('start_date') && ! $request->filled('effective_from')) {
            $request->merge(['effective_from' => $request->input('start_date')]);
        }

        $validated = $request->validate([
            'department_id' => 'nullable|exists:hr_departments,id',
            'position_id' => 'nullable|exists:hr_positions,id',
            'location_id' => 'nullable|exists:locations,id',
            'reason' => 'nullable|string|max:500',
            'effective_from' => 'nullable|date',
        ]);

        $dept = ! empty($validated['department_id']) ? HrDepartment::find($validated['department_id']) : null;
        $pos = ! empty($validated['position_id']) ? HrPosition::find($validated['position_id']) : null;
        $loc = ! empty($validated['location_id']) ? Location::find($validated['location_id']) : null;

        $this->employeeService->transferEmployee(
            employee: $employee,
            actor: Auth::user(),
            department: $dept,
            position: $pos,
            location: $loc,
            reason: $validated['reason'] ?? null,
            effectiveFrom: $validated['effective_from'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Employee transferred successfully.')]);
        }

        return back()->with('success', __('Employee transferred successfully.'));
    }

    public function reviseSalary(Request $request, User $employee): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.payroll.view_sensitive');

        if ((int) $employee->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        if ($request->filled('salary_type') && ! $request->filled('compensation_type')) {
            $type = $request->input('salary_type') === 'shift_based' ? 'shift' : $request->input('salary_type');
            $request->merge(['compensation_type' => $type]);
        }
        if ($request->filled('change_reason') && ! $request->filled('reason')) {
            $request->merge(['reason' => $request->input('change_reason')]);
        }
        if ($request->filled('effective_date') && ! $request->filled('effective_from')) {
            $request->merge(['effective_from' => $request->input('effective_date')]);
        }

        $validated = $request->validate([
            'compensation_type' => 'required|string|in:monthly,hourly,daily,shift,mixed',
            'base_salary' => 'required|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:500',
            'effective_from' => 'nullable|date',
        ]);

        $this->employeeService->reviseSalary(
            employee: $employee,
            actor: Auth::user(),
            compensationType: $validated['compensation_type'],
            baseSalary: (float) $validated['base_salary'],
            hourlyRate: (float) ($validated['hourly_rate'] ?? 0.00),
            reason: $validated['reason'] ?? null,
            effectiveFrom: $validated['effective_from'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Salary revised successfully.')]);
        }

        return back()->with('success', __('Salary revised successfully.'));
    }

    public function storeContract(Request $request, User $employee): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.contracts.manage');

        if ((int) $employee->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        if ($request->filled('base_salary') && ! $request->filled('wage_amount')) {
            $request->merge(['wage_amount' => $request->input('base_salary')]);
        }
        if ($request->filled('weekly_hours') && ! $request->filled('working_hours_per_week')) {
            $request->merge(['working_hours_per_week' => (int) $request->input('weekly_hours')]);
        }
        if (! $request->filled('wage_type')) {
            $request->merge(['wage_type' => 'monthly']);
        }
        if ($request->input('contract_type') === 'probation') {
            $request->merge(['contract_type' => 'fixed_term']);
        }

        $validated = $request->validate([
            'contract_number' => 'required|string|max:100',
            'contract_type' => 'required|string|in:indefinite,fixed_term,service,internship',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'probation_end_date' => 'nullable|date',
            'wage_amount' => 'required|numeric|min:0',
            'wage_type' => 'required|string|in:monthly,hourly,daily,shift',
            'working_hours_per_week' => 'nullable|integer|min:1|max:80',
            'contract_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'nullable|string',
        ]);

        $filePath = null;
        if ($request->hasFile('contract_file')) {
            $filePath = $request->file('contract_file')->store("contracts/{$employee->vendor_id}", 'local');
        }

        $validated['file_path'] = $filePath;

        $contract = $this->employeeService->issueContract(
            vendor: Auth::user()->vendor,
            employee: $employee,
            creator: Auth::user(),
            data: $validated
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Contract issued successfully.'), 'data' => $contract], 201);
        }

        return back()->with('success', __('Contract issued successfully.'));
    }

    public function uploadDocument(Request $request, User $employee): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.documents.manage');

        if ((int) $employee->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        if ($request->filled('document_title') && ! $request->filled('title')) {
            $request->merge(['title' => $request->input('document_title')]);
        }
        if ($request->filled('document_type') && ! $request->filled('category')) {
            $catMap = [
                'id_card' => 'id',
                'order' => 'decree',
                'nda' => 'other',
            ];
            $cat = $catMap[$request->input('document_type')] ?? $request->input('document_type');
            $request->merge(['category' => $cat]);
        }
        if (! $request->hasFile('document') && $request->hasFile('file')) {
            $request->files->set('document', $request->file('file'));
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:contract,id,medical,decree,other',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
            'expires_at' => 'nullable|date',
        ]);

        $file = $request->file('document');
        $path = $file->store("hr_docs/{$employee->vendor_id}/{$employee->id}", 'local');

        $doc = EmployeeDocument::create([
            'vendor_id' => $employee->vendor_id,
            'user_id' => $employee->id,
            'title' => $request->input('title'),
            'category' => $request->input('category'),
            'file_path' => $path,
            'file_size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'expires_at' => $request->input('expires_at'),
            'uploaded_by' => Auth::id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Document uploaded successfully.'), 'data' => $doc], 201);
        }

        return back()->with('success', __('Document uploaded successfully.'));
    }

    public function downloadDocument(Request $request, User $employee, EmployeeDocument $document): BinaryFileResponse
    {
        $this->authorize('hr.documents.view');

        if ((int) $employee->vendor_id !== (int) Auth::user()->vendor_id || (int) $document->user_id !== (int) $employee->id) {
            abort(403);
        }

        $fullPath = storage_path('app/'.$document->file_path);
        if (! file_exists($fullPath)) {
            abort(404, __('Document file not found.'));
        }

        return response()->download($fullPath, $document->title.'.'.pathinfo($document->file_path, PATHINFO_EXTENSION));
    }

    public function destroyDocument(Request $request, User $employee, EmployeeDocument $document): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.documents.manage');

        if ((int) $employee->vendor_id !== (int) Auth::user()->vendor_id || (int) $document->user_id !== (int) $employee->id) {
            abort(403);
        }

        $document->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Document deleted successfully.')]);
        }

        return back()->with('success', __('Document deleted successfully.'));
    }
}
