<?php

namespace App\Services;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            'tables' => $this->getTablesPayload($vendor, $activeLocation?->id),
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
            if ($order->restaurant_table_id) {
                $table = RestaurantTable::find($order->restaurant_table_id);
                $table?->markCleaning();
            }
        } elseif ($newStatus === 'cancelled') {
            if ($order->restaurant_table_id) {
                $table = RestaurantTable::find($order->restaurant_table_id);
                $table?->markAvailable();
            }
        } elseif (in_array($newStatus, ['preparing', 'ready', 'awaiting_payment'], true)) {
            if ($order->restaurant_table_id) {
                $table = RestaurantTable::find($order->restaurant_table_id);
                $table?->update(['status' => $newStatus]);
            }
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

    /**
     * Get real-time enriched tables dataset for POS interactive floor plans and table pickers.
     */
    public function getTablesPayload(Vendor $vendor, ?int $locationId = null): array
    {
        $tables = RestaurantTable::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('is_active', true)
            ->with(['currentOrder.waiter', 'currentOrder.items', 'currentWaiter', 'assignedWaiters', 'mergedWith'])
            ->orderBy('sort_order')
            ->orderBy('table_number')
            ->get();

        $sections = $tables->pluck('section')->filter()->unique()->values()->all();
        if (empty($sections)) {
            $sections = ['Main Hall'];
        }

        $mapped = $tables->map(function ($t) {
            $currentOrder = $t->currentOrder;
            $orderData = null;

            if ($currentOrder && ! in_array($currentOrder->status, ['completed', 'cancelled'], true)) {
                $orderData = [
                    'id' => $currentOrder->id,
                    'order_number' => $currentOrder->order_number,
                    'source' => $currentOrder->source ?: 'pos',
                    'status' => $currentOrder->status,
                    'guest_count' => (int) $currentOrder->guest_count,
                    'waiter_id' => $currentOrder->waiter_id,
                    'waiter_name' => $currentOrder->waiter?->name,
                    'total_amount' => (float) $currentOrder->total_amount,
                    'subtotal' => (float) $currentOrder->subtotal,
                    'items_count' => $currentOrder->items->sum('quantity'),
                    'opened_at' => $currentOrder->opened_at?->toIso8601String(),
                ];
            }

            $status = $t->status ?: 'available';
            if ($orderData) {
                if (in_array($orderData['status'], ['preparing', 'ready', 'held'], true)) {
                    $status = $orderData['status'];
                } elseif ($status === 'available') {
                    $status = 'occupied';
                }
            }

            return [
                'id' => $t->id,
                'uuid' => $t->uuid,
                'table_number' => $t->table_number,
                'name' => $t->name,
                'display_name' => $t->display_name,
                'capacity' => (int) $t->capacity,
                'min_capacity' => (int) $t->min_capacity,
                'combined_capacity' => $t->combined_capacity,
                'effective_capacity' => $t->effective_capacity,
                'section' => $t->section ?: 'Main Hall',
                'dining_area' => $t->dining_area ?: ($t->section ?: 'Main Floor'),
                'shape' => in_array($t->shape, ['round', 'rectangle', 'square'], true) ? $t->shape : 'square',
                'x' => (int) ($t->x ?? 50),
                'y' => (int) ($t->y ?? 50),
                'width' => (int) ($t->width ?? 80),
                'height' => (int) ($t->height ?? 80),
                'status' => $status,
                'occupied_at' => $t->occupied_at?->toIso8601String(),
                'occupancy_minutes' => $t->getOccupancyMinutes(),
                'lock_version' => (int) $t->lock_version,
                'current_waiter_id' => $t->current_waiter_id,
                'current_waiter_name' => $t->currentWaiter?->name,
                'assigned_waiters' => $t->assignedWaiters->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
                'is_merged' => $t->isMerged(),
                'merged_with_id' => $t->merged_with_id,
                'merged_with_number' => $t->mergedWith?->table_number,
                'order' => $orderData,
            ];
        })->values()->all();

        return [
            'sections' => $sections,
            'tables' => $mapped,
            'summary' => [
                'total' => count($mapped),
                'available' => collect($mapped)->where('status', 'available')->count(),
                'occupied' => collect($mapped)->whereIn('status', ['occupied', 'ordering', 'preparing', 'ready'])->count(),
                'reserved' => collect($mapped)->where('status', 'reserved')->count(),
                'cleaning' => collect($mapped)->where('status', 'cleaning')->count(),
            ],
        ];
    }

    /**
     * Update table status directly (e.g. cleaning, available).
     */
    public function updateTableStatus(RestaurantTable $table, string $status, User $user, ?int $expectedLockVersion = null): RestaurantTable
    {
        $validStatuses = ['available', 'occupied', 'reserved', 'ordering', 'preparing', 'ready', 'awaiting_payment', 'cleaning'];
        if (! in_array($status, $validStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid table status '{$status}'.",
            ]);
        }

        if ($expectedLockVersion !== null && (int) $table->lock_version !== (int) $expectedLockVersion) {
            throw ValidationException::withMessages([
                'concurrency' => 'This table was updated by another terminal. Please refresh.',
            ])->status(409);
        }

        $oldStatus = $table->status;
        $table->status = $status;
        $table->lock_version++;

        if (in_array($status, ['available', 'cleaning'], true)) {
            $table->current_order_id = null;
            $table->current_waiter_id = null;
            $table->occupied_at = null;
        }

        $table->save();

        PosOperationLog::log(
            vendor: $table->vendor,
            action: 'pos_table_status_update',
            user: $user,
            entity: $table,
            details: [
                'table_number' => $table->table_number,
                'old_status' => $oldStatus,
                'new_status' => $status,
            ]
        );

        return $table;
    }

    /**
     * Transfer an active order to a new destination table.
     */
    public function transferTable(
        Order $order,
        RestaurantTable $destinationTable,
        User $user,
        ?string $reason = null,
        ?int $expectedOrderLockVersion = null
    ): Order {
        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order transfer.');
        }

        if ((int) $destinationTable->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized destination table.');
        }

        if ($expectedOrderLockVersion !== null && (int) $order->lock_version !== (int) $expectedOrderLockVersion) {
            throw ValidationException::withMessages([
                'concurrency' => 'This order was modified by another terminal. Please refresh.',
            ])->status(409);
        }

        if (! $destinationTable->is_active) {
            throw ValidationException::withMessages([
                'destination_table' => 'Destination table is inactive.',
            ]);
        }

        // Verify destination table has no other active order
        $hasConflict = Order::where('vendor_id', $order->vendor_id)
            ->where('restaurant_table_id', $destinationTable->id)
            ->whereIn('status', ['draft', 'held', 'pending', 'confirmed', 'preparing', 'partially_ready', 'ready', 'served'])
            ->where('id', '!=', $order->id)
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'destination_table' => "Table '{$destinationTable->table_number}' is already occupied with another active order.",
            ]);
        }

        return DB::transaction(function () use ($order, $destinationTable, $user, $reason) {
            $oldTable = $order->restaurantTable;
            $oldTableNumber = $order->table_number;

            // Release old table
            if ($oldTable && $oldTable->id !== $destinationTable->id) {
                $oldTable->markCleaning();
            }

            // Occupy destination table
            $destinationTable->markOccupied($order, $user);

            // Update order
            $order->restaurant_table_id = $destinationTable->id;
            $order->table_number = $destinationTable->table_number;
            $order->lock_version++;
            $order->save();

            PosOperationLog::log(
                vendor: $order->vendor,
                action: 'pos_table_transfer',
                user: $user,
                entity: $order,
                details: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'from_table' => $oldTableNumber,
                    'to_table' => $destinationTable->table_number,
                    'reason' => $reason,
                ]
            );

            return $order->load(['restaurantTable', 'waiter', 'items']);
        });
    }

    /**
     * Merge a source order into a target order.
     */
    public function mergeOrders(
        Order $sourceOrder,
        Order $targetOrder,
        User $user,
        ?string $reason = null,
        ?int $expectedTargetLockVersion = null
    ): Order {
        if ((int) $sourceOrder->vendor_id !== (int) $user->vendor_id || (int) $targetOrder->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order merge.');
        }

        if ($sourceOrder->id === $targetOrder->id) {
            throw ValidationException::withMessages([
                'merge' => 'Cannot merge an order into itself.',
            ]);
        }

        $inactiveStatuses = ['completed', 'cancelled'];
        if (in_array($sourceOrder->status, $inactiveStatuses, true) || in_array($targetOrder->status, $inactiveStatuses, true)) {
            throw ValidationException::withMessages([
                'merge' => 'Only active open orders can be merged.',
            ]);
        }

        if ($expectedTargetLockVersion !== null && (int) $targetOrder->lock_version !== (int) $expectedTargetLockVersion) {
            throw ValidationException::withMessages([
                'concurrency' => 'Target order was modified by another terminal. Please refresh.',
            ])->status(409);
        }

        return DB::transaction(function () use ($sourceOrder, $targetOrder, $user, $reason) {
            // Reassign items from source to target
            OrderItem::where('order_id', $sourceOrder->id)->update(['order_id' => $targetOrder->id]);

            // Recalculate target order totals
            $newSubtotal = $targetOrder->items()->sum('subtotal');
            $targetOrder->subtotal = $newSubtotal;
            $targetOrder->total_amount = max(0, $newSubtotal + $targetOrder->tax_amount - $targetOrder->discount_amount + $targetOrder->tip_amount);
            $targetOrder->guest_count = ($targetOrder->guest_count ?? 1) + ($sourceOrder->guest_count ?? 1);
            $targetOrder->lock_version++;
            $targetOrder->save();

            // Cancel and link source order
            $sourceOrder->status = 'cancelled';
            $sourceOrder->parent_order_id = $targetOrder->id;
            $sourceOrder->notes = trim(($sourceOrder->notes ? $sourceOrder->notes.' | ' : '').'Merged into Order #'.$targetOrder->order_number.($reason ? ' - '.$reason : ''));
            $sourceOrder->save();

            // Release source table if any
            if ($sourceOrder->restaurant_table_id) {
                $sourceTable = RestaurantTable::find($sourceOrder->restaurant_table_id);
                $sourceTable?->markAvailable();
            }

            PosOperationLog::log(
                vendor: $targetOrder->vendor,
                action: 'pos_order_merge',
                user: $user,
                entity: $targetOrder,
                details: [
                    'target_order_id' => $targetOrder->id,
                    'target_order_number' => $targetOrder->order_number,
                    'source_order_id' => $sourceOrder->id,
                    'source_order_number' => $sourceOrder->order_number,
                    'reason' => $reason,
                ]
            );

            return $targetOrder->load(['items', 'restaurantTable', 'waiter', 'customer']);
        });
    }

    /**
     * Split items from a source order into a newly created child order.
     */
    public function splitOrder(
        Order $sourceOrder,
        array $itemsToSplit,
        ?RestaurantTable $targetTable,
        User $user,
        ?string $reason = null,
        ?int $expectedLockVersion = null
    ): array {
        if ((int) $sourceOrder->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order split.');
        }

        if (in_array($sourceOrder->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'split' => 'Cannot split a closed or cancelled order.',
            ]);
        }

        if (empty($itemsToSplit)) {
            throw ValidationException::withMessages([
                'items' => 'No items specified to split.',
            ]);
        }

        if ($expectedLockVersion !== null && (int) $sourceOrder->lock_version !== (int) $expectedLockVersion) {
            throw ValidationException::withMessages([
                'concurrency' => 'Source order was modified by another terminal. Please refresh.',
            ])->status(409);
        }

        return DB::transaction(function () use ($sourceOrder, $itemsToSplit, $targetTable, $user, $reason) {
            $splitOrderNumber = 'POS-SPLIT-'.strtoupper(Str::random(6));

            $splitOrder = Order::create([
                'vendor_id' => $sourceOrder->vendor_id,
                'location_id' => $sourceOrder->location_id,
                'order_number' => $splitOrderNumber,
                'source' => 'pos',
                'type' => $sourceOrder->type,
                'status' => $sourceOrder->status,
                'parent_order_id' => $sourceOrder->id,
                'split_type' => 'by_item',
                'restaurant_table_id' => $targetTable?->id ?? $sourceOrder->restaurant_table_id,
                'table_number' => $targetTable?->table_number ?? ($sourceOrder->table_number.' (Split)'),
                'waiter_id' => $sourceOrder->waiter_id,
                'cashier_id' => $sourceOrder->cashier_id,
                'currency' => $sourceOrder->currency,
                'subtotal' => 0,
                'total_amount' => 0,
                'opened_at' => now(),
            ]);

            foreach ($itemsToSplit as $spec) {
                $itemId = $spec['order_item_id'] ?? null;
                $splitQty = max(1, (int) ($spec['quantity'] ?? 1));

                $origItem = OrderItem::where('order_id', $sourceOrder->id)->findOrFail($itemId);

                if ($splitQty >= $origItem->quantity) {
                    // Transfer the whole line item to the split order
                    $origItem->update(['order_id' => $splitOrder->id]);
                } else {
                    // Split partial quantity
                    $origItem->quantity -= $splitQty;
                    $origItem->subtotal = $origItem->unit_price * $origItem->quantity;
                    $origItem->save();

                    OrderItem::create([
                        'order_id' => $splitOrder->id,
                        'product_id' => $origItem->product_id,
                        'product_name' => $origItem->product_name,
                        'variation_name' => $origItem->variation_name,
                        'unit_price' => $origItem->unit_price,
                        'quantity' => $splitQty,
                        'subtotal' => $origItem->unit_price * $splitQty,
                        'notes' => $origItem->notes,
                        'variants' => $origItem->variants,
                        'modifiers' => $origItem->modifiers,
                        'status' => $origItem->status,
                        'kitchen_station' => $origItem->kitchen_station,
                    ]);
                }
            }

            // Recalculate source order
            $sourceSubtotal = $sourceOrder->items()->sum('subtotal');
            $sourceOrder->subtotal = $sourceSubtotal;
            $sourceOrder->total_amount = max(0, $sourceSubtotal + $sourceOrder->tax_amount - $sourceOrder->discount_amount);
            $sourceOrder->lock_version++;
            $sourceOrder->save();

            // Recalculate split order
            $splitSubtotal = $splitOrder->items()->sum('subtotal');
            $splitOrder->subtotal = $splitSubtotal;
            $splitOrder->total_amount = $splitSubtotal;
            $splitOrder->save();

            if ($targetTable) {
                $targetTable->markOccupied($splitOrder, $user);
            }

            PosOperationLog::log(
                vendor: $sourceOrder->vendor,
                action: 'pos_order_split',
                user: $user,
                entity: $sourceOrder,
                details: [
                    'source_order_id' => $sourceOrder->id,
                    'split_order_id' => $splitOrder->id,
                    'split_order_number' => $splitOrderNumber,
                    'reason' => $reason,
                ]
            );

            return [
                'source_order' => $sourceOrder->fresh(['items', 'restaurantTable']),
                'split_order' => $splitOrder->fresh(['items', 'restaurantTable']),
            ];
        });
    }

    /**
     * Reassign the designated waiter for an order.
     */
    public function assignWaiter(
        Order $order,
        User $newWaiter,
        User $actor,
        ?string $reason = null,
        ?int $expectedLockVersion = null
    ): Order {
        if ((int) $order->vendor_id !== (int) $actor->vendor_id || (int) $newWaiter->vendor_id !== (int) $actor->vendor_id) {
            abort(403, 'Unauthorized waiter reassignment.');
        }

        if ($expectedLockVersion !== null && (int) $order->lock_version !== (int) $expectedLockVersion) {
            throw ValidationException::withMessages([
                'concurrency' => 'This order was modified by another terminal. Please refresh.',
            ])->status(409);
        }

        $oldWaiterId = $order->waiter_id;
        $order->waiter_id = $newWaiter->id;
        $order->lock_version++;
        $order->save();

        if ($order->restaurant_table_id) {
            $table = RestaurantTable::find($order->restaurant_table_id);
            if ($table) {
                $table->current_waiter_id = $newWaiter->id;
                $table->save();
            }
        }

        PosOperationLog::log(
            vendor: $order->vendor,
            action: 'pos_waiter_reassign',
            user: $actor,
            entity: $order,
            details: [
                'order_id' => $order->id,
                'old_waiter_id' => $oldWaiterId,
                'new_waiter_id' => $newWaiter->id,
                'new_waiter_name' => $newWaiter->name,
                'reason' => $reason,
            ]
        );

        return $order->load(['waiter', 'restaurantTable']);
    }
}
