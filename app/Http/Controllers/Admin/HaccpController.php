<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaccpCcp;
use App\Models\HaccpChecklist;
use App\Models\HaccpTemperatureLog;
use App\Models\InventoryBatch;
use App\Models\Warehouse;
use App\Security\Permission;
use App\Services\HaccpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HaccpController extends Controller
{
    public function __construct(
        protected HaccpService $haccpService,
    ) {}

    public function dashboard(Request $request): View
    {
        $this->authorizePermission(Permission::HACCP_VIEW);

        $vendor = $request->user()->vendor;
        $expiring = $this->haccpService->getExpiringBatches($vendor, 7);

        $recentLogs = HaccpTemperatureLog::where('vendor_id', $vendor->id)
            ->with(['ccp', 'warehouse', 'loggedBy'])
            ->orderBy('logged_at', 'desc')
            ->take(15)
            ->get();

        $breachesCount = HaccpTemperatureLog::where('vendor_id', $vendor->id)
            ->where('is_breached', true)
            ->where('logged_at', '>=', now()->subDays(7))
            ->count();

        $todayChecklists = HaccpChecklist::where('vendor_id', $vendor->id)
            ->whereDate('completed_at', now()->toDateString())
            ->with('completedBy')
            ->get();

        $ccpsCount = HaccpCcp::where('vendor_id', $vendor->id)->where('is_active', true)->count();

        return view('admin.inventory.haccp.dashboard', compact(
            'expiring',
            'recentLogs',
            'breachesCount',
            'todayChecklists',
            'ccpsCount'
        ));
    }

    public function temperatureLogs(Request $request): View
    {
        $this->authorizePermission(Permission::HACCP_VIEW);

        $vendor = $request->user()->vendor;
        $query = HaccpTemperatureLog::where('vendor_id', $vendor->id)
            ->with(['ccp', 'warehouse', 'ingredient', 'batch', 'loggedBy']);

        if ($request->filled('is_breached')) {
            $query->where('is_breached', (bool) $request->is_breached);
        }

        if ($request->filled('ccp_id')) {
            $query->where('ccp_id', $request->ccp_id);
        }

        $logs = $query->orderBy('logged_at', 'desc')->paginate(25)->withQueryString();

        $ccps = HaccpCcp::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();

        return view('admin.inventory.haccp.temperatures', compact('logs', 'ccps', 'warehouses'));
    }

    public function storeTemperature(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::HACCP_LOG);

        $validated = $request->validate([
            'ccp_id' => ['nullable', 'exists:haccp_ccps,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'equipment_name' => ['nullable', 'string', 'max:150'],
            'measured_value' => ['required', 'numeric'],
            'corrective_action_taken' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $vendor = $request->user()->vendor;
        $log = $this->haccpService->recordTemperature($vendor, $validated, $request->user());

        $msg = $log->is_breached
            ? __('Critical limit breached! Corrective action logged.')
            : __('Temperature recorded successfully.');

        return back()->with($log->is_breached ? 'warning' : 'success', $msg);
    }

    public function checklists(Request $request): View
    {
        $this->authorizePermission(Permission::HACCP_VIEW);

        $vendor = $request->user()->vendor;
        $checklists = HaccpChecklist::where('vendor_id', $vendor->id)
            ->with('completedBy')
            ->orderBy('completed_at', 'desc')
            ->paginate(20);

        return view('admin.inventory.haccp.checklists', compact('checklists'));
    }

    public function storeChecklist(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::HACCP_LOG);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string'],
            'shift' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.task' => ['required', 'string'],
            'items.*.is_passed' => ['nullable', 'boolean'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $vendor = $request->user()->vendor;
        $this->haccpService->createChecklist($vendor, $validated, $request->user());

        return back()->with('success', __('Checklist submitted successfully.'));
    }

    public function ccps(Request $request): View
    {
        $this->authorizePermission(Permission::HACCP_MANAGE);

        $vendor = $request->user()->vendor;
        $ccps = HaccpCcp::where('vendor_id', $vendor->id)->orderBy('code')->get();

        return view('admin.inventory.haccp.ccps', compact('ccps'));
    }

    public function seedCcps(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::HACCP_MANAGE);

        $vendor = $request->user()->vendor;
        $this->haccpService->seedDefaultCcps($vendor);

        return back()->with('success', __('Default standard HACCP CCP points created successfully.'));
    }

    public function batches(Request $request): View
    {
        $this->authorizePermission(Permission::HACCP_VIEW);

        $vendor = $request->user()->vendor;
        $query = InventoryBatch::where('vendor_id', $vendor->id)
            ->with(['ingredient.unit', 'warehouse', 'unit', 'supplier', 'productionOrder']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('filter')) {
            $today = now()->toDateString();
            if ($request->filter === 'expired') {
                $query->where('expiry_date', '<', $today);
            } elseif ($request->filter === 'critical') {
                $query->where('expiry_date', '>=', $today)->where('expiry_date', '<=', now()->addDays(3)->toDateString());
            } elseif ($request->filter === 'warning') {
                $query->where('expiry_date', '>', now()->addDays(3)->toDateString())->where('expiry_date', '<=', now()->addDays(7)->toDateString());
            }
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('batch_number', 'ILIKE', "%{$s}%")
                    ->orWhereHas('ingredient', fn ($iq) => $iq->where('name', 'ILIKE', "%{$s}%"));
            });
        }

        // Default to FEFO (First-Expired, First-Out)
        $batches = $query->orderBy('expiry_date', 'asc')->paginate(25)->withQueryString();

        return view('admin.inventory.haccp.batches', compact('batches'));
    }

    public function traceability(Request $request): View
    {
        $this->authorizePermission(Permission::HACCP_REPORTS);

        $vendor = $request->user()->vendor;
        $traceResult = null;
        $batchNumber = trim($request->input('batch_number', ''));

        if (! empty($batchNumber)) {
            $traceResult = $this->haccpService->traceBatch($vendor, $batchNumber);
        }

        return view('admin.inventory.haccp.traceability', compact('traceResult', 'batchNumber'));
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user() || ! auth()->user()->hasPermission($permission)) {
            abort(403, 'Unauthorized access.');
        }
    }
}
