<?php

namespace App\Services;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\Models\Category;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosOperationLog;
use App\Models\PosSetting;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(
        protected CreateOrderAction $createOrderAction,
        protected PosDeviceService $posDeviceService,
    ) {}

    /**
     * Build the complete bootstrap payload for initializing the POS client interface.
     */
    public function getBootstrapPayload(User $user, ?string $deviceUuid = null, ?int $locationId = null): array
    {
        $vendor = $user->vendor;

        if (! $vendor) {
            throw ValidationException::withMessages([
                'vendor' => 'Authenticated user has no associated restaurant vendor.',
            ]);
        }

        // Determine active location
        $locations = $vendor->locations()->where('is_active', true)->get();
        $activeLocationId = $locationId ?? $user->location_id ?? $locations->first()?->id;
        $activeLocation = $locations->firstWhere('id', (int) $activeLocationId);

        // Resolve device if UUID supplied
        $device = null;
        $activeSession = null;
        if (! empty($deviceUuid)) {
            $device = PosDevice::where('vendor_id', $vendor->id)
                ->where('uuid', $deviceUuid)
                ->where('is_active', true)
                ->first();

            if ($device) {
                $device->touchHeartbeat(request()?->ip(), null, false);
                $activeSession = $device->activeSession();
            }
        }

        $posSettings = PosSetting::forLocation($vendor, $activeLocation?->id);

        // Fetch tables for active location
        $tables = RestaurantTable::where('vendor_id', $vendor->id)
            ->when($activeLocation?->id, fn ($q, $locId) => $q->where('location_id', $locId))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return [
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'slug' => $vendor->slug,
                'logo_url' => $vendor->logo_url,
                'currency' => $vendor->currency ?? 'AMD',
                'currency_symbol' => $vendor->currency_symbol ?? '֏',
            ],
            'locations' => $locations->map(fn ($loc) => [
                'id' => $loc->id,
                'name' => $loc->name,
                'address' => $loc->address,
                'is_active' => (bool) $loc->is_active,
            ]),
            'active_location_id' => $activeLocation?->id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name ?: $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
                'employee_id' => $user->employee_id,
                'assigned_table_ids' => $user->assigned_table_ids ?? [],
                'has_pin' => $user->hasPin(),
                'permissions' => $user->getPermissions(),
            ],
            'device' => $device ? [
                'id' => $device->id,
                'uuid' => $device->uuid,
                'name' => $device->name,
                'device_type' => $device->device_type,
                'is_paired' => (bool) $device->is_paired,
                'location_id' => $device->location_id,
            ] : null,
            'session' => $activeSession ? [
                'session_token' => $activeSession->session_token,
                'mode' => $activeSession->mode,
                'is_locked' => $activeSession->isLocked(),
                'expires_at' => $activeSession->expires_at->toIso8601String(),
            ] : null,
            'settings' => [
                'enable_quick_pin' => (bool) $posSettings->enable_quick_pin,
                'require_table_selection' => (bool) $posSettings->require_table_selection,
                'auto_lock_seconds' => (int) $posSettings->auto_lock_seconds,
                'allow_manual_discounts' => (bool) $posSettings->allow_manual_discounts,
                'max_discount_percent' => (float) $posSettings->max_discount_percent,
                'require_manager_approval_discount' => (bool) $posSettings->require_manager_approval_discount,
                'require_manager_approval_void' => (bool) $posSettings->require_manager_approval_void,
                'default_order_type' => $posSettings->default_order_type,
                'default_payment_method' => $posSettings->default_payment_method,
                'allow_offline_orders' => (bool) $posSettings->allow_offline_orders,
                'print_receipt_on_payment' => (bool) $posSettings->print_receipt_on_payment,
                'print_kitchen_ticket_on_order' => (bool) $posSettings->print_kitchen_ticket_on_order,
                'tax_rate' => (float) $posSettings->tax_rate,
                'tax_inclusive' => (bool) $posSettings->tax_inclusive,
                'service_charge_percent' => (float) $posSettings->service_charge_percent,
            ],
            'tables_summary' => [
                'total' => $tables->count(),
                'active' => $tables->where('is_active', true)->count(),
            ],
            'menu' => $this->getMenuPayload($vendor, $activeLocation?->id),
        ];
    }

    /**
     * Get menu catalog formatted for POS order taking.
     */
    public function getMenuPayload(Vendor $vendor, ?int $locationId = null): array
    {
        $categories = Category::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $products = Product::where('vendor_id', $vendor->id)
            ->where('is_available', true)
            ->with(['variations', 'category'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $mappedCategories = $categories->map(function ($cat) use ($products) {
            return [
                'id' => $cat->id,
                'name' => method_exists($cat, 'getTranslatedName') ? $cat->getTranslatedName() : $cat->name,
                'sort_order' => (int) $cat->sort_order,
                'products_count' => $products->where('category_id', $cat->id)->count(),
            ];
        })->values()->all();

        $mappedProducts = $products->map(function ($prod) {
            $station = 'kitchen';
            $catName = mb_strtolower($prod->category?->name ?? '');
            if (str_contains($catName, 'bar') || str_contains($catName, 'խմիչք') || str_contains($catName, 'cocktail') || str_contains($catName, 'напитки')) {
                $station = 'bar';
            } elseif (str_contains($catName, 'coffee') || str_contains($catName, 'սուրճ') || str_contains($catName, 'кофе')) {
                $station = 'coffee';
            } elseif (str_contains($catName, 'dessert') || str_contains($catName, 'աղանդեր') || str_contains($catName, 'десерт')) {
                $station = 'dessert';
            }

            return [
                'id' => $prod->id,
                'category_id' => $prod->category_id,
                'name' => method_exists($prod, 'getTranslatedName') ? $prod->getTranslatedName() : $prod->name,
                'description' => $prod->description,
                'price' => (float) $prod->price,
                'image' => $prod->image ?: Product::DEFAULT_IMAGE,
                'is_available' => (bool) $prod->is_available,
                'is_featured' => (bool) $prod->is_featured,
                'kitchen_station' => $station,
                'variations' => $prod->variations->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => method_exists($v, 'getTranslatedName') ? $v->getTranslatedName() : $v->name,
                    'price' => (float) $v->price,
                ])->values()->all(),
                'modifiers' => [
                    ['id' => 'no_onion', 'name' => __('No Onion'), 'price' => 0.00],
                    ['id' => 'extra_sauce', 'name' => __('Extra Sauce'), 'price' => 200.00],
                    ['id' => 'extra_cheese', 'name' => __('Extra Cheese'), 'price' => 500.00],
                    ['id' => 'spicy', 'name' => __('Spicy'), 'price' => 0.00],
                ],
            ];
        })->values()->all();

        $favorites = collect($mappedProducts)->filter(fn ($p) => $p['is_featured'])->values()->all();
        if (empty($favorites)) {
            $favorites = collect($mappedProducts)->take(8)->values()->all();
        }

        return [
            'categories' => $mappedCategories,
            'products' => $mappedProducts,
            'favorites' => $favorites,
        ];
    }

    /**
     * Create an order directly from the POS interface.
     */
    public function createPosOrder(Vendor $vendor, array $orderData, User $user, ?PosDevice $device = null): array
    {
        if (! $user->hasPermission(Permission::POS_ORDERS) && ! $user->hasPermission(Permission::POS_ACCESS)) {
            throw ValidationException::withMessages([
                'authorization' => 'User lacks permission to create POS orders.',
            ]);
        }

        // Multi-tenant check
        if ((int) $vendor->id !== (int) $user->vendor_id) {
            throw ValidationException::withMessages([
                'vendor_id' => 'Cross-tenant POS order creation forbidden.',
            ]);
        }

        // Attach POS metadata
        $orderData['source'] = 'pos';
        $orderData['pos_device_id'] = $device?->id;
        $orderData['cashier_id'] = $user->isCashier() ? $user->id : ($orderData['cashier_id'] ?? null);
        $orderData['waiter_id'] = $orderData['waiter_id'] ?? ($user->isWaiter() ? $user->id : null);

        // Resolve table details if table id provided
        if (! empty($orderData['restaurant_table_id'])) {
            $table = RestaurantTable::where('vendor_id', $vendor->id)->find($orderData['restaurant_table_id']);
            if ($table) {
                $orderData['table_number'] = $table->table_number;
            }
        }

        if (empty($orderData['table_number'])) {
            $orderData['table_number'] = 'Counter';
        }

        $dto = CreateOrderDTO::fromArray($orderData);
        $result = $this->createOrderAction->execute($vendor, $dto);

        PosOperationLog::log(
            vendor: $vendor,
            action: 'order_create',
            user: $user,
            device: $device,
            entity: $result['order'],
            details: [
                'order_number' => $result['order']->order_number,
                'total_amount' => $result['order']->total_amount,
                'source' => 'pos',
            ]
        );

        return $result;
    }

    /**
     * Update order status with audit trail.
     */
    public function updateOrderStatus(Order $order, string $newStatus, User $user, ?PosDevice $device = null, ?string $reason = null): Order
    {
        $allowed = [
            'draft',
            'held',
            'pending',
            'accepted',
            'confirmed',
            'preparing',
            'partially_ready',
            'ready',
            'served',
            'completed',
            'cancelled',
        ];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid order status '{$newStatus}'.",
            ]);
        }

        $oldStatus = $order->status;
        $order->status = $newStatus;

        if ($newStatus === 'completed' && empty($order->closed_at)) {
            $order->closed_at = Carbon::now();
        }

        $order->save();

        PosOperationLog::log(
            vendor: $order->vendor,
            action: 'order_status_update',
            user: $user,
            device: $device,
            entity: $order,
            details: [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ]
        );

        return $order;
    }

    /**
     * Hold an active order.
     */
    public function holdOrder(Order $order, User $user, ?PosDevice $device = null, ?string $reason = null): Order
    {
        return $this->updateOrderStatus($order, 'held', $user, $device, $reason ?? 'Held by employee');
    }

    /**
     * Resume a held order.
     */
    public function resumeOrder(Order $order, User $user, ?PosDevice $device = null): Order
    {
        return $this->updateOrderStatus($order, 'pending', $user, $device, 'Resumed from hold');
    }

    /**
     * Query orders with POS filters.
     */
    public function getPosOrders(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $status = null,
        ?string $tableNumber = null,
        ?int $waiterId = null,
        int $perPage = 30
    ): LengthAwarePaginator {
        return Order::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($tableNumber, fn ($q) => $q->where('table_number', $tableNumber))
            ->when($waiterId, fn ($q) => $q->where('waiter_id', $waiterId))
            ->with(['items', 'customer', 'waiter', 'cashier', 'restaurantTable', 'posDevice'])
            ->latest('id')
            ->paginate($perPage);
    }
}
