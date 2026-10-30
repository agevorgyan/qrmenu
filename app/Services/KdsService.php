<?php

namespace App\Services;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\PosKitchenStation;
use App\Models\PosOperationLog;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KdsService
{
    public function __construct(
        protected ReceiptPrinterService $printerService
    ) {}

    /**
     * Get or seed kitchen stations for vendor.
     *
     * @return Collection<int, PosKitchenStation>
     */
    public function getStations(Vendor $vendor, ?int $locationId = null): Collection
    {
        $stations = PosKitchenStation::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->forLocation($locationId)
            ->active()
            ->orderBy('sort_order')
            ->get();

        if ($stations->isEmpty()) {
            // Seed default stations for this vendor
            $defaults = PosKitchenStation::getDefaultStations();
            foreach ($defaults as $def) {
                PosKitchenStation::create(array_merge($def, [
                    'vendor_id' => $vendor->id,
                    'location_id' => $locationId,
                ]));
            }

            $stations = PosKitchenStation::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->forLocation($locationId)
                ->active()
                ->orderBy('sort_order')
                ->get();
        }

        return $stations;
    }

    /**
     * Retrieve active KDS tickets filtered by station and location.
     */
    public function getActiveTickets(Vendor $vendor, ?int $locationId = null, ?string $station = null): array
    {
        $query = Order::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereIn('status', [
                'pending',
                'accepted',
                'confirmed',
                'preparing',
                'partially_ready',
                'ready',
            ])
            ->with([
                'items' => fn ($q) => $q->where('status', '!=', 'cancelled'),
                'restaurantTable',
                'waiter',
            ])
            ->orderBy('created_at', 'asc');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $orders = $query->get();
        $targetStation = ($station && strtolower($station) !== 'all') ? strtolower($station) : null;
        $now = Carbon::now();

        $tickets = [];

        foreach ($orders as $order) {
            // Filter items for station
            $filteredItems = $order->items->filter(function (OrderItem $item) use ($targetStation) {
                if (! $targetStation) {
                    return true;
                }
                $itemStation = strtolower($item->kitchen_station ?: 'kitchen');

                return $itemStation === $targetStation;
            })->values();

            // Skip ticket if no items match the selected station
            if ($filteredItems->isEmpty()) {
                continue;
            }

            $elapsedMinutes = (int) $order->created_at->diffInMinutes($now);

            // Priority determination: normal (<10m), warning (10-20m), delayed (>20m)
            $priority = 'normal';
            if ($elapsedMinutes >= 20) {
                $priority = 'delayed';
            } elseif ($elapsedMinutes >= 10) {
                $priority = 'warning';
            }

            $totalItemsCount = $filteredItems->count();
            $readyItemsCount = $filteredItems->filter(fn ($i) => in_array($i->status, ['ready', 'served'], true))->count();
            $allReady = ($totalItemsCount > 0 && $readyItemsCount === $totalItemsCount);

            $tickets[] = [
                'order_id' => $order->id,
                'order_uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'order_type' => $order->order_type,
                'order_status' => $order->status,
                'table_name' => $order->restaurantTable?->name,
                'table_number' => $order->restaurantTable?->table_number,
                'guest_count' => $order->guest_count,
                'waiter_name' => $order->waiter?->name ?? 'POS Terminal',
                'notes' => $order->notes,
                'created_at' => $order->created_at->toISOString(),
                'elapsed_minutes' => $elapsedMinutes,
                'priority' => $priority,
                'all_ready' => $allReady,
                'total_items_count' => $totalItemsCount,
                'ready_items_count' => $readyItemsCount,
                'items' => $filteredItems->map(function (OrderItem $item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'variation_name' => $item->variation_name,
                        'quantity' => (int) $item->quantity,
                        'status' => $item->status,
                        'kitchen_station' => $item->kitchen_station ?: 'kitchen',
                        'notes' => $item->notes,
                        'modifiers' => $item->modifiers ?: [],
                        'prepared_at' => $item->prepared_at?->toISOString(),
                        'ready_at' => $item->ready_at?->toISOString(),
                    ];
                })->all(),
            ];
        }

        return $tickets;
    }

    /**
     * Update individual item preparation status and synchronize order-level status.
     */
    public function updateItemStatus(OrderItem $item, string $newStatus, User $user, ?PosDevice $device = null): array
    {
        $allowed = ['pending', 'preparing', 'ready', 'served', 'cancelled'];
        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid item preparation status '{$newStatus}'.",
            ]);
        }

        $order = $item->order ?: Order::withoutGlobalScopes()->findOrFail($item->order_id);

        // Security check
        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            throw ValidationException::withMessages([
                'authorization' => 'Unauthorized item modification.',
            ]);
        }

        DB::transaction(function () use ($item, $newStatus, $order, $user, $device) {
            $item->status = $newStatus;
            $now = Carbon::now();

            if ($newStatus === 'preparing' && empty($item->prepared_at)) {
                $item->prepared_at = $now;
            } elseif ($newStatus === 'ready') {
                $item->ready_at = $now;
            } elseif ($newStatus === 'served') {
                $item->served_at = $now;
            }

            $item->save();

            // Recalculate parent Order status based on all non-cancelled items
            $this->syncOrderStatusFromItems($order, $user, $device);
        });

        return [
            'item' => $item->fresh(),
            'order' => $order->fresh(),
            'order_status' => $order->fresh()->status,
        ];
    }

    /**
     * Bulk bump ticket: marks all items (optionally restricted to a station) to ready or served.
     */
    public function bumpOrder(Order $order, string $targetStatus, User $user, ?PosDevice $device = null, ?string $station = null): Order
    {
        $allowed = ['ready', 'served', 'preparing'];
        if (! in_array($targetStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid bump target status '{$targetStatus}'.",
            ]);
        }

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            throw ValidationException::withMessages([
                'authorization' => 'Unauthorized order modification.',
            ]);
        }

        DB::transaction(function () use ($order, $targetStatus, $user, $device, $station) {
            $now = Carbon::now();
            $targetStation = ($station && strtolower($station) !== 'all') ? strtolower($station) : null;

            $items = $order->items()->where('status', '!=', 'cancelled')->get();

            foreach ($items as $item) {
                if ($targetStation) {
                    $itemStation = strtolower($item->kitchen_station ?: 'kitchen');
                    if ($itemStation !== $targetStation) {
                        continue;
                    }
                }

                $item->status = $targetStatus;
                if ($targetStatus === 'ready') {
                    $item->ready_at = $now;
                } elseif ($targetStatus === 'served') {
                    $item->served_at = $now;
                } elseif ($targetStatus === 'preparing' && empty($item->prepared_at)) {
                    $item->prepared_at = $now;
                }
                $item->save();
            }

            // Sync parent order status
            $this->syncOrderStatusFromItems($order, $user, $device);

            $vendor = $order->vendor ?: Vendor::find($order->vendor_id);
            PosOperationLog::log(
                vendor: $vendor,
                action: 'kds_bump_order',
                user: $user,
                device: $device,
                entity: $order,
                details: [
                    'target_status' => $targetStatus,
                    'station' => $station ?? 'all',
                    'order_number' => $order->order_number,
                ]
            );
        });

        return $order->fresh();
    }

    /**
     * Generate and queue a kitchen ticket for physical or browser printing.
     */
    public function printKitchenTicket(Order $order, string $station = 'kitchen', ?PosDevice $device = null): array
    {
        $stationModel = PosKitchenStation::withoutGlobalScopes()
            ->where('vendor_id', $order->vendor_id)
            ->where('slug', $station)
            ->first();

        $paperWidth = $stationModel?->paper_width ?: '80mm';

        $escpos = $this->printerService->buildKitchenTicketEscpos($order, $station, [], $paperWidth);
        $text = $this->printerService->buildKitchenTicketText($order, $station, [], $paperWidth);

        $vendor = $order->vendor ?: Vendor::find($order->vendor_id);

        $printJob = $this->printerService->queuePrintJob(
            vendor: $vendor,
            ticketType: 'kitchen_ticket',
            escpos: $escpos,
            text: $text,
            order: $order,
            device: $device,
            stationSlug: $station,
            title: strtoupper($station).' Ticket #'.$order->order_number,
        );

        // Attempt direct network print if IP configured
        if ($stationModel?->printer_ip) {
            $success = $this->printerService->sendToNetworkPrinter(
                $stationModel->printer_ip,
                $stationModel->printer_port ?: 9100,
                $escpos
            );

            if ($success) {
                $printJob->markPrinted();
            } else {
                $printJob->markFailed('Network printer socket connection failed');
            }
        }

        return [
            'print_job' => $printJob->fresh(),
            'ticket_text' => $text,
        ];
    }

    /**
     * Synchronize parent Order status from its individual items.
     */
    protected function syncOrderStatusFromItems(Order $order, User $user, ?PosDevice $device = null): void
    {
        $items = OrderItem::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $allServed = $items->every(fn ($i) => $i->status === 'served');
        $allReady = $items->every(fn ($i) => in_array($i->status, ['ready', 'served'], true));
        $anyReady = $items->contains(fn ($i) => in_array($i->status, ['ready', 'served'], true));
        $anyPreparing = $items->contains(fn ($i) => $i->status === 'preparing');

        $newOrderStatus = $order->status;

        if ($allServed) {
            $newOrderStatus = 'served';
        } elseif ($allReady) {
            $newOrderStatus = 'ready';
        } elseif ($anyReady) {
            $newOrderStatus = 'partially_ready';
        } elseif ($anyPreparing) {
            $newOrderStatus = 'preparing';
        }

        if ($newOrderStatus !== $order->status) {
            $oldStatus = $order->status;
            $order->status = $newOrderStatus;
            $order->save();

            // Update associated restaurant table status if applicable
            if ($order->restaurant_table_id) {
                $table = RestaurantTable::withoutGlobalScopes()->find($order->restaurant_table_id);
                if ($table && in_array($newOrderStatus, ['preparing', 'ready', 'awaiting_payment'], true)) {
                    $table->update(['status' => $newOrderStatus]);
                }
            }

            $vendor = $order->vendor ?: Vendor::find($order->vendor_id);
            PosOperationLog::log(
                vendor: $vendor,
                action: 'kds_status_sync',
                user: $user,
                device: $device,
                entity: $order,
                details: [
                    'from' => $oldStatus,
                    'to' => $newOrderStatus,
                    'order_number' => $order->order_number,
                ]
            );

            // Broadcast real-time event to waiters, cashiers, and QR screens
            try {
                event(new OrderStatusUpdated($order));
            } catch (\Throwable $e) {
                Log::warning('KDS OrderStatusUpdated broadcast failed: '.$e->getMessage());
            }
        }
    }
}
