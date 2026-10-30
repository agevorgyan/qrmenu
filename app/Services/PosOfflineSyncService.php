<?php

namespace App\Services;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\DTOs\OrderItemDTO;
use App\Models\Category;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosKitchenStation;
use App\Models\PosOperationLog;
use App\Models\PosPayment;
use App\Models\PosSetting;
use App\Models\PosShift;
use App\Models\PosSyncBatch;
use App\Models\PosSyncConflict;
use App\Models\PosSyncOperation;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosOfflineSyncService
{
    public function __construct(
        protected PosService $posService,
        protected PosPaymentShiftService $paymentShiftService,
        protected CreateOrderAction $createOrderAction
    ) {}

    /**
     * Build comprehensive offline manifest for local IndexedDB caching.
     */
    public function getOfflineBootstrapManifest(Vendor $vendor, PosDevice $device, User $user, ?int $locationId = null): array
    {
        $locationId = $locationId ?: $device->location_id ?: $vendor->locations()->first()?->id;

        $settings = PosSetting::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)->orWhereNull('location_id');
            })
            ->first() ?? new PosSetting(['vendor_id' => $vendor->id]);

        // 1. Staff Members with offline PIN verification hashes
        $staff = User::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->get()
            ->map(function (User $u) use ($device) {
                // Generate a deterministic device-scoped PIN HMAC for offline verification
                $pinHmac = null;
                if (! empty($u->pin_code)) {
                    $pinHmac = hash_hmac('sha256', (string) $u->pin_code, (string) ($device->pairing_code ?: 'elam_offline_key'));
                }

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'pos_mode' => $u->pos_mode ?: 'waiter',
                    'has_pin' => ! empty($u->pin_code),
                    'offline_pin_hmac' => $pinHmac,
                ];
            })->all();

        // 2. Menu Catalog with stations and variations
        $categories = Category::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order'])
            ->all();

        $products = Product::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_available', true)
            ->with('variations')
            ->get()
            ->map(function (Product $p) {
                return [
                    'id' => $p->id,
                    'category_id' => $p->category_id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'is_available' => (bool) $p->is_available,
                    'is_featured' => (bool) $p->is_featured,
                    'variations' => $p->variations->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'price' => (float) $v->price,
                    ])->all(),
                    'modifiers' => [
                        ['id' => 'no_onion', 'name' => 'No Onion', 'price' => 0.00],
                        ['id' => 'extra_sauce', 'name' => 'Extra Sauce', 'price' => 200.00],
                        ['id' => 'extra_cheese', 'name' => 'Extra Cheese', 'price' => 500.00],
                    ],
                ];
            })->all();

        $stations = PosKitchenStation::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->forLocation($locationId)
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'color', 'paper_width'])
            ->all();

        $catalogPayload = [
            'categories' => $categories,
            'products' => $products,
            'stations' => $stations,
        ];
        $catalogHash = hash('sha256', json_encode($catalogPayload));

        // 3. Tables & Dining Areas
        $tables = RestaurantTable::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('location_id', $locationId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (RestaurantTable $t) {
                return [
                    'id' => $t->id,
                    'table_number' => $t->table_number,
                    'name' => $t->name,
                    'dining_area' => $t->dining_area ?: 'Main Hall',
                    'capacity' => (int) $t->capacity,
                    'status' => $t->status,
                    'current_order_id' => $t->current_order_id,
                    'lock_version' => (int) $t->lock_version,
                ];
            })->all();

        // 4. Current Open Shift for this Device
        $currentShift = PosShift::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('pos_device_id', $device->id)
            ->where('status', 'open')
            ->first();

        return [
            'manifest_version' => '2.0',
            'generated_at' => Carbon::now()->toISOString(),
            'device' => [
                'id' => $device->id,
                'uuid' => $device->uuid,
                'name' => $device->name,
                'location_id' => $locationId,
                'pairing_code' => $device->pairing_code,
            ],
            'policy' => [
                'allow_offline_orders' => (bool) ($settings->allow_offline_orders ?? true),
                'max_offline_hours' => (int) ($settings->max_offline_hours ?? 48),
                'max_offline_orders' => (int) ($settings->max_offline_orders ?? 100),
                'max_offline_amount' => (float) ($settings->max_offline_amount ?? 500000.00),
                'allow_offline_cash_payments' => (bool) ($settings->allow_offline_cash_payments ?? true),
                'allow_offline_card_payments' => (bool) ($settings->allow_offline_card_payments ?? false),
                'tax_rate' => (float) ($settings->tax_rate ?? 0.00),
                'tax_inclusive' => (bool) ($settings->tax_inclusive ?? true),
                'service_charge_percent' => (float) ($settings->service_charge_percent ?? 0.00),
            ],
            'staff' => $staff,
            'catalog' => $catalogPayload,
            'catalog_hash' => $catalogHash,
            'tables' => $tables,
            'active_shift' => $currentShift ? [
                'id' => $currentShift->id,
                'uuid' => $currentShift->uuid,
                'opening_float' => (float) $currentShift->opening_float,
                'opened_at' => $currentShift->opened_at->toISOString(),
            ] : null,
        ];
    }

    /**
     * Ingest and synchronize an offline operations batch safely.
     */
    public function processSyncBatch(Vendor $vendor, PosDevice $device, User $user, array $batchData): array
    {
        $batchId = $batchData['batch_id'] ?? (string) Str::uuid();
        $operations = $batchData['operations'] ?? [];
        $clientStartedAt = ! empty($batchData['client_started_at']) ? Carbon::parse($batchData['client_started_at']) : Carbon::now();
        $clientEndedAt = ! empty($batchData['client_ended_at']) ? Carbon::parse($batchData['client_ended_at']) : Carbon::now();

        // 1. Device Security & Offline Window Validation
        if ((int) $device->vendor_id !== (int) $vendor->id || ! $device->is_active) {
            throw ValidationException::withMessages([
                'device' => 'POS device is unauthorized or deactivated.',
            ]);
        }

        $settings = PosSetting::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->first() ?? new PosSetting(['vendor_id' => $vendor->id]);

        $maxOfflineHours = (int) ($settings->max_offline_hours ?? 48);
        $maxOfflineOrders = (int) ($settings->max_offline_orders ?? 100);
        $maxOfflineAmount = (float) ($settings->max_offline_amount ?? 500000.00);

        // Check offline duration
        $hoursOffline = (int) $clientStartedAt->diffInHours(Carbon::now());
        if ($hoursOffline > $maxOfflineHours) {
            Log::warning("POS Sync batch {$batchId} offline window {$hoursOffline}h exceeds max {$maxOfflineHours}h.");
        }

        // Sort operations chronologically
        usort($operations, function ($a, $b) {
            $tA = strtotime($a['client_timestamp'] ?? 'now');
            $tB = strtotime($b['client_timestamp'] ?? 'now');

            return $tA <=> $tB;
        });

        // 2. Create Sync Batch record
        $batchRecord = PosSyncBatch::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'pos_device_id' => $device->id,
            'user_id' => $user->id,
            'batch_id' => $batchId,
            'status' => 'processing',
            'total_operations' => count($operations),
            'synced_operations' => 0,
            'conflict_operations' => 0,
            'client_started_at' => $clientStartedAt,
            'client_ended_at' => $clientEndedAt,
            'server_processed_at' => Carbon::now(),
        ]);

        $syncedCount = 0;
        $conflictCount = 0;
        $results = [];

        // Check total orders and amount in batch for safety limits
        $totalOrdersInBatch = count(array_filter($operations, fn ($o) => ($o['entity_type'] ?? '') === 'order' && ($o['action'] ?? '') === 'create'));
        $totalAmountInBatch = array_reduce($operations, function ($acc, $o) {
            if (($o['entity_type'] ?? '') === 'payment') {
                return $acc + (float) ($o['payload']['amount'] ?? 0);
            }

            return $acc;
        }, 0.0);

        $limitExceeded = false;
        if ($totalOrdersInBatch > $maxOfflineOrders || $totalAmountInBatch > $maxOfflineAmount) {
            $limitExceeded = true;
            PosSyncConflict::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'pos_sync_batch_id' => $batchRecord->id,
                'pos_device_id' => $device->id,
                'client_sync_id' => $batchId,
                'entity_type' => 'batch',
                'conflict_type' => 'limit_exceeded',
                'client_data' => [
                    'orders_count' => $totalOrdersInBatch,
                    'total_amount' => $totalAmountInBatch,
                    'max_orders' => $maxOfflineOrders,
                    'max_amount' => $maxOfflineAmount,
                ],
                'resolution_strategy' => 'manual_review',
                'resolution_notes' => 'Batch exceeded permitted offline threshold limits. Logged for manager inspection.',
            ]);
        }

        // 3. Process Individual Operations with Idempotency
        foreach ($operations as $op) {
            $clientSyncId = $op['client_sync_id'] ?? (string) Str::uuid();
            $entityType = $op['entity_type'] ?? 'order';
            $action = $op['action'] ?? 'create';
            $timestamp = ! empty($op['client_timestamp']) ? Carbon::parse($op['client_timestamp']) : Carbon::now();
            $payload = $op['payload'] ?? [];

            // Check Idempotency
            $existingOp = PosSyncOperation::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('client_sync_id', $clientSyncId)
                ->first();

            if ($existingOp) {
                $syncedCount++;
                $results[] = [
                    'client_sync_id' => $clientSyncId,
                    'status' => 'duplicate',
                    'entity_type' => $entityType,
                    'server_id' => $existingOp->server_entity_id,
                    'message' => 'Operation was already synced previously.',
                ];

                continue;
            }

            // Ingest based on Entity Type
            try {
                DB::beginTransaction();

                $serverEntityId = null;
                $opStatus = 'synced';
                $errorMessage = null;

                if ($entityType === 'order') {
                    $orderResult = $this->ingestOfflineOrder($vendor, $device, $user, $clientSyncId, $action, $timestamp, $payload, $batchRecord);
                    $serverEntityId = $orderResult['order_id'];
                    if (! empty($orderResult['conflict'])) {
                        $opStatus = 'conflict';
                        $conflictCount++;
                    } else {
                        $syncedCount++;
                    }
                } elseif ($entityType === 'payment') {
                    $paymentResult = $this->ingestOfflinePayment($vendor, $device, $user, $clientSyncId, $timestamp, $payload, $batchRecord);
                    $serverEntityId = $paymentResult['payment_id'];
                    if (! empty($paymentResult['conflict'])) {
                        $opStatus = 'conflict';
                        $conflictCount++;
                    } else {
                        $syncedCount++;
                    }
                } elseif ($entityType === 'table_status') {
                    $tableResult = $this->ingestOfflineTableStatus($vendor, $device, $user, $clientSyncId, $timestamp, $payload, $batchRecord);
                    $serverEntityId = $tableResult['table_id'];
                    if (! empty($tableResult['conflict'])) {
                        $opStatus = 'conflict';
                        $conflictCount++;
                    } else {
                        $syncedCount++;
                    }
                }

                // Record sync operation
                PosSyncOperation::create([
                    'uuid' => (string) Str::uuid(),
                    'pos_sync_batch_id' => $batchRecord->id,
                    'vendor_id' => $vendor->id,
                    'pos_device_id' => $device->id,
                    'client_sync_id' => $clientSyncId,
                    'entity_type' => $entityType,
                    'action' => $action,
                    'client_timestamp' => $timestamp,
                    'payload' => $payload,
                    'status' => $opStatus,
                    'server_entity_id' => $serverEntityId,
                    'error_message' => $errorMessage,
                ]);

                DB::commit();

                $results[] = [
                    'client_sync_id' => $clientSyncId,
                    'status' => $opStatus,
                    'entity_type' => $entityType,
                    'server_id' => $serverEntityId,
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("POS Sync operation {$clientSyncId} failed: ".$e->getMessage());

                $conflictCount++;
                PosSyncOperation::create([
                    'uuid' => (string) Str::uuid(),
                    'pos_sync_batch_id' => $batchRecord->id,
                    'vendor_id' => $vendor->id,
                    'pos_device_id' => $device->id,
                    'client_sync_id' => $clientSyncId,
                    'entity_type' => $entityType,
                    'action' => $action,
                    'client_timestamp' => $timestamp,
                    'payload' => $payload,
                    'status' => 'rejected',
                    'error_message' => $e->getMessage(),
                ]);

                $results[] = [
                    'client_sync_id' => $clientSyncId,
                    'status' => 'rejected',
                    'entity_type' => $entityType,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Update batch status
        $finalStatus = ($conflictCount > 0) ? 'partially_failed' : 'completed';
        $batchRecord->update([
            'status' => $finalStatus,
            'synced_operations' => $syncedCount,
            'conflict_operations' => $conflictCount,
            'summary' => [
                'synced' => $syncedCount,
                'conflicts' => $conflictCount,
                'limit_exceeded' => $limitExceeded,
            ],
        ]);

        $device->update(['last_sync_at' => Carbon::now()]);

        // Audit Log
        PosOperationLog::log(
            vendor: $vendor,
            action: 'offline_sync_batch',
            user: $user,
            device: $device,
            details: [
                'batch_id' => $batchId,
                'synced_count' => $syncedCount,
                'conflict_count' => $conflictCount,
                'status' => $finalStatus,
            ]
        );

        return [
            'success' => true,
            'batch_id' => $batchId,
            'batch_status' => $finalStatus,
            'synced_count' => $syncedCount,
            'conflict_count' => $conflictCount,
            'operations' => $results,
            'server_timestamp' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Ingest offline order creation or update.
     */
    protected function ingestOfflineOrder(
        Vendor $vendor,
        PosDevice $device,
        User $user,
        string $clientSyncId,
        string $action,
        Carbon $timestamp,
        array $payload,
        PosSyncBatch $batch
    ): array {
        $tableId = ! empty($payload['restaurant_table_id']) ? (int) $payload['restaurant_table_id'] : null;
        $hasTableConflict = false;

        // Check Table Collision
        if ($tableId) {
            $table = RestaurantTable::withoutGlobalScopes()->find($tableId);
            if ($table && in_array($table->status, ['occupied', 'ordering', 'preparing'], true) && $table->current_order_id) {
                // Table was occupied online while offline device used it -> log collision
                $hasTableConflict = true;
                PosSyncConflict::create([
                    'uuid' => (string) Str::uuid(),
                    'vendor_id' => $vendor->id,
                    'pos_sync_batch_id' => $batch->id,
                    'pos_device_id' => $device->id,
                    'client_sync_id' => $clientSyncId,
                    'entity_type' => 'order',
                    'conflict_type' => 'table_collision',
                    'client_data' => $payload,
                    'server_data' => [
                        'table_id' => $table->id,
                        'table_status' => $table->status,
                        'online_order_id' => $table->current_order_id,
                    ],
                    'resolution_strategy' => 'client_wins',
                    'resolution_notes' => 'Table collision detected during offline sync. Offline order preserved.',
                ]);
            }
        }

        // Build Order
        $itemsData = $payload['items'] ?? [];
        $itemsDTO = [];
        $calculatedSubtotal = 0.0;

        foreach ($itemsData as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $subtotal = $qty * $unitPrice;
            $calculatedSubtotal += $subtotal;

            $itemsDTO[] = new OrderItemDTO(
                productId: (int) ($item['product_id'] ?? 1),
                quantity: $qty,
                variationId: ! empty($item['variation_id']) ? (int) $item['variation_id'] : null,
                variationName: $item['variation_name'] ?? null,
                notes: $item['notes'] ?? null,
                modifiers: is_array($item['modifiers'] ?? null) ? $item['modifiers'] : [],
                variants: is_array($item['variants'] ?? null) ? $item['variants'] : [],
                status: $item['status'] ?? 'pending',
                kitchenStation: $item['kitchen_station'] ?? 'kitchen'
            );
        }

        $orderType = $payload['order_type'] ?? 'dine_in';
        $locationId = ! empty($payload['location_id']) ? (int) $payload['location_id'] : ($device->location_id ?: $vendor->locations()->first()?->id);

        $dto = new CreateOrderDTO(
            locationId: $locationId,
            type: $orderType,
            items: $itemsDTO,
            tableNumber: $payload['table_number'] ?? null,
            notes: $payload['notes'] ?? null,
            waiterId: ! empty($payload['waiter_id']) ? (int) $payload['waiter_id'] : $user->id,
            source: 'pos',
            restaurantTableId: $tableId,
            guestCount: ! empty($payload['guest_count']) ? (int) $payload['guest_count'] : 1,
            discountAmount: (float) ($payload['discount_amount'] ?? 0),
            discountType: $payload['discount_type'] ?? null,
            discountReason: $payload['discount_reason'] ?? null,
            taxAmount: (float) ($payload['tax_amount'] ?? 0),
            posDeviceId: $device->id,
            status: $payload['status'] ?? 'confirmed',
        );

        $result = $this->createOrderAction->execute($vendor, $dto);
        $order = $result['order'];

        // Update offline metadata on order
        $order->update([
            'is_offline_created' => true,
            'client_sync_id' => $clientSyncId,
            'offline_synced_at' => Carbon::now(),
            'created_at' => $timestamp,
        ]);

        // Update table link if specified
        if ($tableId) {
            $table = RestaurantTable::withoutGlobalScopes()->find($tableId);
            if ($table) {
                $table->update([
                    'status' => 'occupied',
                    'current_order_id' => $order->id,
                    'current_waiter_id' => $order->waiter_id,
                    'occupied_at' => $timestamp,
                ]);
            }
        }

        return [
            'order_id' => $order->id,
            'conflict' => $hasTableConflict,
        ];
    }

    /**
     * Ingest offline payment.
     */
    protected function ingestOfflinePayment(
        Vendor $vendor,
        PosDevice $device,
        User $user,
        string $clientSyncId,
        Carbon $timestamp,
        array $payload,
        PosSyncBatch $batch
    ): array {
        $orderId = ! empty($payload['order_id']) ? (int) $payload['order_id'] : null;
        $orderClientSyncId = $payload['order_client_sync_id'] ?? null;

        $order = null;
        if ($orderId) {
            $order = Order::withoutGlobalScopes()->where('vendor_id', $vendor->id)->find($orderId);
        }
        if (! $order && $orderClientSyncId) {
            $order = Order::withoutGlobalScopes()->where('vendor_id', $vendor->id)->where('client_sync_id', $orderClientSyncId)->first();
        }

        if (! $order) {
            PosSyncConflict::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'pos_sync_batch_id' => $batch->id,
                'pos_device_id' => $device->id,
                'client_sync_id' => $clientSyncId,
                'entity_type' => 'payment',
                'conflict_type' => 'order_not_found',
                'client_data' => $payload,
                'resolution_strategy' => 'manual_review',
                'resolution_notes' => 'Offline payment received for unknown or unresolvable order.',
            ]);

            return ['payment_id' => null, 'conflict' => true];
        }

        $amount = (float) ($payload['amount'] ?? $order->total_amount);
        $method = $payload['payment_method'] ?? 'cash';
        $tipAmount = (float) ($payload['tip_amount'] ?? 0);
        $tendered = (float) ($payload['cash_tendered'] ?? $amount);
        $change = max(0.0, $tendered - $amount);

        // Find or associate active shift
        $shift = PosShift::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('pos_device_id', $device->id)
            ->where('status', 'open')
            ->first();

        $payment = PosPayment::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'pos_shift_id' => $shift?->id,
            'pos_device_id' => $device->id,
            'user_id' => $user->id,
            'payment_method' => $method,
            'amount' => $amount,
            'cash_tendered' => $tendered,
            'change_due' => $change,
            'tip_amount' => $tipAmount,
            'currency' => $order->currency ?: 'AMD',
            'status' => 'completed',
            'transaction_reference' => $payload['transaction_reference'] ?? ('OFFLINE-PAY-'.strtoupper(Str::random(8))),
            'notes' => $payload['notes'] ?? 'Offline synchronized payment',
            'is_offline' => true,
            'client_sync_id' => $clientSyncId,
            'offline_synced_at' => Carbon::now(),
            'created_at' => $timestamp,
        ]);

        // Associate with shift and trigger recalculation
        if ($shift) {
            $shift->recalculateExpectedCash();
        }

        // Update Order paid amount and status
        $order->paid_amount = (float) ($order->paid_amount ?? 0) + $amount;
        $order->tip_amount = (float) ($order->tip_amount ?? 0) + $tipAmount;
        $order->change_amount = $change;
        if ($shift) {
            $order->pos_shift_id = $shift->id;
        }

        if ($order->paid_amount >= (float) $order->total_amount) {
            $order->status = 'completed';
            $order->closed_at = $timestamp;
            if ($order->restaurant_table_id) {
                $table = RestaurantTable::withoutGlobalScopes()->find($order->restaurant_table_id);
                $table?->markCleaning();
            }
        }
        $order->save();

        return ['payment_id' => $payment->id, 'conflict' => false];
    }

    /**
     * Ingest offline table status change.
     */
    protected function ingestOfflineTableStatus(
        Vendor $vendor,
        PosDevice $device,
        User $user,
        string $clientSyncId,
        Carbon $timestamp,
        array $payload,
        PosSyncBatch $batch
    ): array {
        $tableId = (int) ($payload['table_id'] ?? 0);
        $targetStatus = $payload['status'] ?? 'available';
        $clientLockVersion = isset($payload['lock_version']) ? (int) $payload['lock_version'] : null;

        $table = RestaurantTable::withoutGlobalScopes()->where('vendor_id', $vendor->id)->find($tableId);
        if (! $table) {
            return ['table_id' => null, 'conflict' => true];
        }

        $hasConflict = false;
        if ($clientLockVersion !== null && (int) $table->lock_version > $clientLockVersion) {
            $hasConflict = true;
            PosSyncConflict::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'pos_sync_batch_id' => $batch->id,
                'pos_device_id' => $device->id,
                'client_sync_id' => $clientSyncId,
                'entity_type' => 'table',
                'conflict_type' => 'version_mismatch',
                'client_data' => $payload,
                'server_data' => [
                    'server_status' => $table->status,
                    'server_lock_version' => $table->lock_version,
                ],
                'resolution_strategy' => 'server_wins',
                'resolution_notes' => 'Table version conflict during offline sync. Server state preserved.',
            ]);
        } else {
            $table->status = $targetStatus;
            $table->lock_version = (int) $table->lock_version + 1;
            if ($targetStatus === 'available') {
                $table->current_order_id = null;
                $table->current_waiter_id = null;
                $table->occupied_at = null;
            }
            $table->save();
        }

        return ['table_id' => $table->id, 'conflict' => $hasConflict];
    }
}
