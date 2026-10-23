<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Services\CrmAutomationService;
use App\Services\CustomerSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerSyncService $customerService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $user = Auth::user();
        $vendor = $user->vendor;
        $locations = $vendor->locations;

        if ($user->location_id) {
            $activeLocationId = $user->location_id;
            if ($request->has('location_id') && (int) $request->get('location_id') !== (int) $user->location_id) {
                abort(403, 'Unauthorized access to other branch customers.');
            }
        } else {
            $activeLocationId = $request->get('location_id', session('active_location_id', $locations->first()?->id));
            if ($request->has('location_id')) {
                session(['active_location_id' => $activeLocationId]);
            }
        }

        $selectedLocation = ($activeLocationId && $activeLocationId !== 'all')
            ? $locations->firstWhere('id', $activeLocationId)
            : null;

        $search = $request->get('search');
        $consentFilter = $request->get('marketing');

        $query = Customer::where('vendor_id', $vendor->id)->with('location');

        if ($activeLocationId && $activeLocationId !== 'all') {
            $query->where(function ($q) use ($activeLocationId) {
                $q->where('location_id', $activeLocationId)
                    ->orWhereHas('orders', function ($oq) use ($activeLocationId) {
                        $oq->where('location_id', $activeLocationId);
                    });
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $driver = DB::getDriverName();
                if ($driver === 'pgsql') {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                } else {
                    $searchTerm = '%'.mb_strtolower($search).'%';
                    $q->where(DB::raw('LOWER(name)'), 'like', $searchTerm)
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere(DB::raw('LOWER(email)'), 'like', $searchTerm);
                }
            });
        }

        if ($consentFilter === 'yes') {
            $query->where('marketing_opt_in', true);
        } elseif ($consentFilter === 'no') {
            $query->where('marketing_opt_in', false);
        }

        $customers = $query->orderBy('updated_at', 'desc')->paginate(15);

        // Stats summary filtered by active location
        $statsQuery = Customer::where('vendor_id', $vendor->id);
        if ($activeLocationId && $activeLocationId !== 'all') {
            $statsQuery->where(function ($q) use ($activeLocationId) {
                $q->where('location_id', $activeLocationId)
                    ->orWhereHas('orders', function ($oq) use ($activeLocationId) {
                        $oq->where('location_id', $activeLocationId);
                    });
            });
        }

        $totalCustomers = (clone $statsQuery)->count();
        $optedInCustomers = (clone $statsQuery)->where('marketing_opt_in', true)->count();

        if ($activeLocationId && $activeLocationId !== 'all') {
            $totalRevenue = Order::where('vendor_id', $vendor->id)
                ->where('location_id', $activeLocationId)
                ->sum('total_amount');
        } else {
            $totalRevenue = Customer::where('vendor_id', $vendor->id)->sum('total_spent');
        }

        $upcomingBirthdays = (new CrmAutomationService)->getUpcomingBirthdays($vendor, 14);

        return view('admin.customers.index', compact(
            'vendor',
            'locations',
            'activeLocationId',
            'selectedLocation',
            'customers',
            'totalCustomers',
            'optedInCustomers',
            'totalRevenue',
            'search',
            'consentFilter',
            'upcomingBirthdays'
        ));
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        $vendor = Auth::user()->vendor;
        $customer->load('location');
        $this->customerService->recalculateStats($customer);
        $orders = $customer->orders()->with('items', 'location')->paginate(20);

        return view('admin.customers.show', compact('vendor', 'customer', 'orders'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Customer::class);

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('vendor_id', $vendor->id)],
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'marketing_opt_in' => 'nullable|boolean',
        ]);

        $activeLocationId = session('active_location_id', $vendor->locations->first()?->id);
        $payload = $validated;
        if (! isset($payload['location_id'])) {
            $fallbackLoc = ($activeLocationId ? $vendor->locations->firstWhere('id', (int) $activeLocationId) : null) ?? $vendor->locations->first();
            $payload['location_id'] = $fallbackLoc?->id;
        }

        $this->customerService->createCustomer($vendor, $payload, $request->has('marketing_opt_in'));

        return back()->with('success', __('Customer added successfully!'));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('vendor_id', $vendor->id)],
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'marketing_opt_in' => 'nullable|boolean',
        ]);

        $this->customerService->updateCustomer($customer, $validated, $request->has('marketing_opt_in'));

        return back()->with('success', __('Customer :name details updated successfully!', ['name' => $customer->name]));
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        $this->customerService->deleteCustomer($customer);

        return redirect()->route('admin.customers.index')->with('success', __('Customer record deleted.'));
    }

    public function export(Request $request)
    {
        $this->authorize('export', Customer::class);

        $vendor = Auth::user()->vendor;
        $activeLocationId = session('active_location_id');

        $query = Customer::where('vendor_id', $vendor->id)->with('location');

        if ($activeLocationId && $activeLocationId !== 'all') {
            $query->where(function ($q) use ($activeLocationId) {
                $q->where('location_id', $activeLocationId)
                    ->orWhereHas('orders', function ($oq) use ($activeLocationId) {
                        $oq->where('location_id', $activeLocationId);
                    });
            });
        }

        $customers = $query->orderBy('name', 'asc')->get();

        $fileName = 'customers_export_'.date('Y_m_d_His').'.csv';

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$fileName}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($customers, $vendor) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($file, [
                'Customer ID',
                'Branch / Location',
                'Full Name',
                'Phone Number',
                'Email Address',
                'Birthday',
                'Address',
                'Marketing Consent',
                'Total Orders Count',
                "Total Spent ({$vendor->currency})",
                'Last Order Date',
                'Registration Date',
                'Notes',
            ]);

            $sanitize = static function (mixed $value): mixed {
                if (! is_string($value) || $value === '') {
                    return $value;
                }
                if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    return "'".$value;
                }

                return $value;
            };

            foreach ($customers as $c) {
                $row = [
                    $c->id,
                    $c->location?->name ?? 'All Branches',
                    $c->name ?? 'Guest',
                    $c->phone ?? '-',
                    $c->email ?? '-',
                    $c->birthdate ? $c->birthdate->format('Y-m-d') : '-',
                    $c->address ?? '-',
                    $c->marketing_opt_in ? 'Yes (Opted In)' : 'No',
                    $c->total_orders_count,
                    number_format($c->total_spent, 2, '.', ''),
                    $c->last_order_at ? $c->last_order_at->format('Y-m-d H:i') : '-',
                    $c->created_at->format('Y-m-d H:i'),
                    $c->notes ?? '',
                ];

                fputcsv($file, array_map($sanitize, $row));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Send personalized birthday discount SMS greeting to customer.
     */
    public function sendBirthdayGreeting(Customer $customer, CrmAutomationService $crmService)
    {
        $this->authorize('update', $customer);

        $vendor = Auth::user()->vendor;

        if (empty($customer->phone)) {
            return back()->with('error', __('Customer phone number is not specified.'));
        }

        $res = $crmService->sendBirthdayGreeting($customer, $vendor);
        if ($res['success']) {
            return back()->with('success', __('Birthday greeting SMS successfully sent to :name.', ['name' => $customer->name ?? __('customer')]));
        }

        return back()->with('error', __('Failed to send SMS: :error', ['error' => $res['error'] ?? '']));
    }
}
