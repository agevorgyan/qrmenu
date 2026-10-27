<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Order;
use App\Models\ServiceRating;
use App\Models\Tip;
use App\Models\TipRatingSetting;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TipRatingService
{
    /**
     * Calculate authoritative tip amount based on vendor settings.
     *
     * @return array{amount: float, type: string, percentage: ?float, currency: string}
     *
     * @throws ValidationException
     */
    public function calculateTip(
        Vendor $vendor,
        float $baseAmount,
        string $tipType = 'none',
        float|int|null $tipValue = 0,
        ?string $orderType = null
    ): array {
        $settings = $vendor->getTipRatingSetting();

        if (! $vendor->isTipsEnabled($orderType)) {
            return [
                'amount' => 0.00,
                'type' => 'none',
                'percentage' => null,
                'currency' => $vendor->currency ?? 'AMD',
            ];
        }

        $type = strtolower(trim($tipType));

        if ($type === 'none' || empty($tipValue) || (float) $tipValue <= 0) {
            return [
                'amount' => 0.00,
                'type' => 'none',
                'percentage' => null,
                'currency' => $vendor->currency ?? 'AMD',
            ];
        }

        $amount = 0.00;
        $percentageApplied = null;

        if ($type === 'percentage') {
            $percent = (float) $tipValue;
            if ($percent <= 0 || $percent > 100) {
                throw ValidationException::withMessages([
                    'tip_value' => 'Invalid tip percentage.',
                ]);
            }
            $amount = round(($baseAmount * $percent) / 100, 2);
            $percentageApplied = $percent;
        } elseif ($type === 'fixed') {
            $amount = round((float) $tipValue, 2);
        } elseif ($type === 'custom') {
            if (! $settings->allow_custom_tip) {
                throw ValidationException::withMessages([
                    'tip_type' => 'Custom tips are disabled for this restaurant.',
                ]);
            }
            $amount = round((float) $tipValue, 2);
        } else {
            throw ValidationException::withMessages([
                'tip_type' => 'Invalid tip type.',
            ]);
        }

        // Validate min/max boundaries
        if ($amount > 0) {
            $min = (float) ($settings->min_tip_amount ?? 0);
            $max = (float) ($settings->max_tip_amount ?? 50000);

            if ($min > 0 && $amount < $min) {
                throw ValidationException::withMessages([
                    'tip_amount' => 'Tip amount is below the minimum allowed limit ('.number_format($min, 2).' '.$vendor->currency.').',
                ]);
            }

            if ($max > 0 && $amount > $max) {
                throw ValidationException::withMessages([
                    'tip_amount' => 'Tip amount exceeds the maximum allowed limit ('.number_format($max, 2).' '.$vendor->currency.').',
                ]);
            }
        }

        return [
            'amount' => max(0, $amount),
            'type' => $type,
            'percentage' => $percentageApplied,
            'currency' => $vendor->currency ?? 'AMD',
        ];
    }

    /**
     * Record a tip associated with an order.
     */
    public function recordOrderTip(
        Order $order,
        float $tipAmount,
        string $paymentMethod,
        string $tipType = 'custom',
        ?float $percentage = null,
        ?int $waiterId = null
    ): ?Tip {
        if ($tipAmount <= 0) {
            return null;
        }

        $vendor = $order->vendor;
        $settings = $vendor->getTipRatingSetting();

        $isCashOrPos = in_array($paymentMethod, ['cash', 'pos_terminal'], true);
        $paymentStatus = $isCashOrPos ? 'paid' : 'pending';

        $resolvedWaiterId = $waiterId ?? $order->waiter_id;
        if ($resolvedWaiterId && in_array($settings->waiter_attribution_scope ?? 'both', ['none', 'ratings'], true)) {
            $resolvedWaiterId = null;
        }

        return DB::transaction(function () use ($order, $vendor, $settings, $tipAmount, $paymentMethod, $tipType, $percentage, $resolvedWaiterId, $isCashOrPos, $paymentStatus) {
            // Find existing tip for this order if appending/updating
            $tip = Tip::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('order_id', $order->id)
                ->first();

            $data = [
                'vendor_id' => $vendor->id,
                'location_id' => $order->location_id,
                'order_id' => $order->id,
                'table_number' => $order->table_number,
                'waiter_id' => $resolvedWaiterId,
                'amount' => $tipAmount,
                'currency' => $vendor->currency ?? 'AMD',
                'tip_type' => $tipType,
                'percentage_applied' => $percentage,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'allocation_policy' => $settings->allocation_policy ?? 'pool',
                'is_cash' => $isCashOrPos,
                'idempotency_key' => 'tip_ord_'.$order->order_number,
                'settled_at' => $isCashOrPos ? now() : null,
            ];

            if ($tip) {
                $tip->update($data);

                return $tip;
            }

            return Tip::create($data);
        });
    }

    /**
     * Record a direct tip (e.g. table session without ordering, or separate tip payment).
     */
    public function recordDirectTip(Vendor $vendor, array $validated): Tip
    {
        $settings = $vendor->getTipRatingSetting();

        if (! $vendor->isTipsEnabled()) {
            throw ValidationException::withMessages([
                'module' => 'Tips are currently disabled for this restaurant.',
            ]);
        }

        $locationId = ! empty($validated['location_id']) ? (int) $validated['location_id'] : null;
        if ($locationId) {
            $loc = Location::withoutGlobalScopes()->where('vendor_id', $vendor->id)->find($locationId);
            if (! $loc) {
                throw ValidationException::withMessages(['location_id' => 'Invalid location.']);
            }
        }

        $waiterId = ! empty($validated['waiter_id']) ? (int) $validated['waiter_id'] : null;
        if ($waiterId) {
            $waiter = User::where('vendor_id', $vendor->id)->find($waiterId);
            if (! $waiter) {
                throw ValidationException::withMessages(['waiter_id' => 'Selected waiter does not belong to this restaurant.']);
            }
            if (! $waiter->is_active) {
                throw ValidationException::withMessages(['waiter_id' => 'Selected waiter is currently inactive.']);
            }
            if (in_array($settings->waiter_attribution_scope ?? 'both', ['none', 'ratings'], true)) {
                $waiterId = null;
            }
        }

        $amount = round((float) $validated['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Tip amount must be greater than zero.']);
        }

        $min = (float) ($settings->min_tip_amount ?? 0);
        $max = (float) ($settings->max_tip_amount ?? 50000);
        if ($min > 0 && $amount < $min) {
            throw ValidationException::withMessages(['amount' => 'Tip amount is below minimum limit.']);
        }
        if ($max > 0 && $amount > $max) {
            throw ValidationException::withMessages(['amount' => 'Tip amount exceeds maximum limit.']);
        }

        $paymentMethod = $validated['payment_method'] ?? 'cash';
        $isCashOrPos = in_array($paymentMethod, ['cash', 'pos_terminal'], true);
        $idempotencyKey = $validated['idempotency_key'] ?? ('direct_tip_'.Str::uuid());

        return DB::transaction(function () use ($vendor, $settings, $locationId, $waiterId, $amount, $paymentMethod, $isCashOrPos, $idempotencyKey, $validated) {
            return Tip::create([
                'vendor_id' => $vendor->id,
                'location_id' => $locationId,
                'order_id' => ! empty($validated['order_id']) ? (int) $validated['order_id'] : null,
                'table_number' => $validated['table_number'] ?? null,
                'waiter_id' => $waiterId,
                'amount' => $amount,
                'currency' => $vendor->currency ?? 'AMD',
                'tip_type' => $validated['tip_type'] ?? 'custom',
                'payment_method' => $paymentMethod,
                'payment_status' => $isCashOrPos ? 'paid' : 'pending',
                'allocation_policy' => $settings->allocation_policy ?? 'pool',
                'is_cash' => $isCashOrPos,
                'idempotency_key' => $idempotencyKey,
                'notes' => $validated['notes'] ?? null,
                'settled_at' => $isCashOrPos ? now() : null,
            ]);
        });
    }

    /**
     * Submit customer service or waiter rating with duplicate prevention and XSS sanitization.
     */
    public function submitRating(Vendor $vendor, array $validated, ?string $ip = null, ?string $userAgent = null): ServiceRating
    {
        $settings = $vendor->getTipRatingSetting();

        if (! $vendor->isRatingsEnabled()) {
            throw ValidationException::withMessages([
                'module' => 'Service ratings are currently disabled for this restaurant.',
            ]);
        }

        $order = null;
        if (! empty($validated['order_number'])) {
            $order = Order::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where(function ($q) use ($validated) {
                    $q->where('order_number', $validated['order_number'])
                        ->orWhere('tracking_token', $validated['order_number']);
                })
                ->first();

            if (! $order) {
                throw ValidationException::withMessages([
                    'order_number' => 'The referenced order was not found for this restaurant.',
                ]);
            }
        } elseif (! empty($validated['order_id'])) {
            $order = Order::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->find($validated['order_id']);

            if (! $order) {
                throw ValidationException::withMessages([
                    'order_id' => 'The referenced order was not found.',
                ]);
            }
        }

        // Validate table context if provided
        $tableNumber = ! empty($validated['table_number']) ? trim((string) $validated['table_number']) : ($order?->table_number ?? null);

        // Enforce duplicate prevention policy
        $this->assertNoDuplicateRating($vendor, $settings, $order, $tableNumber, $ip);

        // Validate waiter attribution
        $waiterId = ! empty($validated['waiter_id']) ? (int) $validated['waiter_id'] : ($order?->waiter_id ?? null);
        if ($waiterId) {
            $waiter = User::where('vendor_id', $vendor->id)->find($waiterId);
            if (! $waiter) {
                throw ValidationException::withMessages([
                    'waiter_id' => 'Selected waiter does not belong to this restaurant.',
                ]);
            }
            if (! $waiter->is_active) {
                throw ValidationException::withMessages([
                    'waiter_id' => 'Selected waiter is currently inactive.',
                ]);
            }
            if (in_array($settings->waiter_attribution_scope ?? 'both', ['none', 'tips'], true)) {
                $waiterId = null;
            }
        }

        // Rating scale check
        $ratingScore = (int) $validated['rating'];
        $maxScale = max(1, min(10, $settings->rating_scale ?? 5));
        if ($ratingScore < 1 || $ratingScore > $maxScale) {
            throw ValidationException::withMessages([
                'rating' => "Rating score must be between 1 and {$maxScale}.",
            ]);
        }

        // Written feedback validation
        $comment = ! empty($validated['comment']) ? trim((string) $validated['comment']) : null;
        if ($settings->require_written_feedback && empty($comment)) {
            throw ValidationException::withMessages([
                'comment' => 'Written feedback is required by this restaurant.',
            ]);
        }
        if (! $settings->allow_written_feedback && ! empty($comment)) {
            $comment = null; // Silently ignore or drop if not permitted
        }

        // Feedback categories validation
        $selectedCategories = ! empty($validated['categories']) ? (array) $validated['categories'] : [];
        if (! empty($selectedCategories) && is_array($settings->feedback_categories)) {
            $allowed = array_map('mb_strtolower', $settings->feedback_categories);
            $selectedCategories = array_values(array_filter($selectedCategories, function ($cat) use ($allowed) {
                return in_array(mb_strtolower(trim((string) $cat)), $allowed, true);
            }));
        }

        $isAnonymous = isset($validated['is_anonymous'])
            ? (bool) $validated['is_anonymous']
            : (bool) $settings->is_anonymous_by_default;

        $customerName = (! $isAnonymous && ! empty($validated['customer_name'])) ? trim((string) $validated['customer_name']) : null;
        $customerPhone = (! $isAnonymous && ! empty($validated['customer_phone'])) ? trim((string) $validated['customer_phone']) : null;

        $idempotencyKey = $validated['idempotency_key'] ?? ('rating_'.($order ? $order->order_number : ($tableNumber.'_'.now()->format('YmdHi'))).'_'.Str::random(8));

        return DB::transaction(function () use ($vendor, $settings, $order, $tableNumber, $waiterId, $ratingScore, $selectedCategories, $comment, $isAnonymous, $customerName, $customerPhone, $ip, $userAgent, $idempotencyKey) {
            return ServiceRating::create([
                'vendor_id' => $vendor->id,
                'location_id' => $order?->location_id,
                'order_id' => $order?->id,
                'table_number' => $tableNumber,
                'waiter_id' => $waiterId,
                'rating' => $ratingScore,
                'categories' => $selectedCategories,
                'comment' => $comment,
                'is_anonymous' => $isAnonymous,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? Str::limit($userAgent, 500) : null,
                'status' => $settings->auto_publish_ratings ? 'published' : 'hidden',
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * Enforce duplicate rating policy.
     *
     * @throws ValidationException
     */
    protected function assertNoDuplicateRating(
        Vendor $vendor,
        TipRatingSetting $settings,
        ?Order $order = null,
        ?string $tableNumber = null,
        ?string $ip = null
    ): void {
        // Policy 1: If linked to an order, only 1 rating per order
        if ($order) {
            $existing = ServiceRating::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('order_id', $order->id)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'order' => 'Feedback for this order has already been submitted. Thank you!',
                ]);
            }
        }

        // Policy 2: Cooldown per IP and table (prevent automated feedback spamming)
        $windowHours = max(1, (int) ($settings->submission_window_hours ?? 24));
        $cooldownTime = now()->subMinutes(10); // 10-minute anti-flood protection

        if ($ip) {
            $recentFromIp = ServiceRating::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('ip_address', $ip)
                ->where('created_at', '>=', $cooldownTime)
                ->count();

            if ($recentFromIp >= 3) {
                throw ValidationException::withMessages([
                    'ip' => 'Too many rating submissions in a short period. Please try again later.',
                ]);
            }
        }

        if ($tableNumber && empty($order)) {
            $recentFromTable = ServiceRating::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('table_number', $tableNumber)
                ->where('created_at', '>=', now()->subHours(2))
                ->exists();

            if ($recentFromTable && $settings->duplicate_policy === 'once_per_session_day') {
                throw ValidationException::withMessages([
                    'table_number' => 'Feedback has already been recorded for this table session.',
                ]);
            }
        }
    }

    /**
     * Moderate a customer service rating (publish, hide, flag) with audit note.
     */
    public function moderateRating(ServiceRating $rating, string $status, ?string $note, User $moderator): void
    {
        $validStatuses = ['published', 'hidden', 'flagged'];
        if (! in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid moderation status '{$status}'.");
        }

        $rating->setModerationStatus($status, $note, $moderator);

        // Security / Administrative audit logging
        app(SecurityAuditService::class)->record(
            event: 'service_rating.moderated',
            action: "Service rating #{$rating->id} moderated to {$status}",
            vendor: $rating->vendor,
            actor: $moderator,
            targetType: 'service_rating',
            targetId: $rating->id,
            metadata: [
                'rating_id' => $rating->id,
                'new_status' => $status,
                'moderation_note' => $note,
            ]
        );
    }

    /**
     * Compute comprehensive tenant-scoped Tip reports.
     *
     * @return array<string, mixed>
     */
    public function getTipAnalytics(Vendor $vendor, array $filters = []): array
    {
        $days = (int) ($filters['range'] ?? 30);
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : now()->subDays($days - 1)->startOfDay();
        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();

        $locationId = ! empty($filters['location_id']) ? (int) $filters['location_id'] : null;

        $baseTipsQuery = Tip::withoutGlobalScopes()
            ->where('tips.vendor_id', $vendor->id)
            ->whereBetween('tips.created_at', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where('tips.location_id', $locationId));

        // Paid tips are successfully collected revenue
        $paidTipsQuery = (clone $baseTipsQuery)->where('tips.payment_status', 'paid');

        $totalCollected = (float) $paidTipsQuery->sum('tips.amount');
        $tipCount = $paidTipsQuery->count();
        $averageTip = $tipCount > 0 ? round($totalCollected / $tipCount, 2) : 0.00;

        // Eligible orders in period
        $ordersQuery = Order::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled'])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId));

        $eligibleOrdersCount = $ordersQuery->count();
        $tippedOrdersCount = (clone $ordersQuery)->where('tip_amount', '>', 0)->count();
        $tipRatePercentage = $eligibleOrdersCount > 0
            ? round(($tippedOrdersCount / $eligibleOrdersCount) * 100, 1)
            : 0.0;

        // Breakdown by payment method
        $byPaymentMethod = (clone $paidTipsQuery)
            ->select('tips.payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(tips.amount) as total'))
            ->groupBy('tips.payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->payment_method,
                'count' => (int) $r->count,
                'total' => (float) $r->total,
            ])
            ->values()
            ->toArray();

        // Breakdown by order type
        $byOrderType = (clone $paidTipsQuery)
            ->leftJoin('orders', 'tips.order_id', '=', 'orders.id')
            ->select(DB::raw("COALESCE(orders.type, 'direct_table') as order_type"), DB::raw('COUNT(tips.id) as count'), DB::raw('SUM(tips.amount) as total'))
            ->groupBy('order_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'type' => $r->order_type,
                'count' => (int) $r->count,
                'total' => (float) $r->total,
            ])
            ->values()
            ->toArray();

        // Daily trend
        $dailyTrend = (clone $paidTipsQuery)
            ->select(DB::raw('DATE(tips.created_at) as date'), DB::raw('COUNT(*) as count'), DB::raw('SUM(tips.amount) as total'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) $r->date,
                'count' => (int) $r->count,
                'total' => (float) $r->total,
            ])
            ->values()
            ->toArray();

        // Staff / Waiter allocation breakdown
        $waiterAllocation = (clone $paidTipsQuery)
            ->whereNotNull('tips.waiter_id')
            ->join('users', 'tips.waiter_id', '=', 'users.id')
            ->select('users.id as waiter_id', 'users.name as waiter_name', DB::raw('COUNT(tips.id) as count'), DB::raw('SUM(tips.amount) as total'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'waiter_id' => (int) $r->waiter_id,
                'waiter_name' => $r->waiter_name,
                'count' => (int) $r->count,
                'total' => (float) $r->total,
            ])
            ->values()
            ->toArray();

        // Separately reported non-collected financial states
        $refundedQuery = (clone $baseTipsQuery)->where('tips.payment_status', 'refunded');
        $refundedCount = $refundedQuery->count();
        $refundedTotal = (float) $refundedQuery->sum('tips.amount');

        $failedQuery = (clone $baseTipsQuery)->where('tips.payment_status', 'failed');
        $failedCount = $failedQuery->count();

        return [
            'vendor_id' => $vendor->id,
            'currency' => $vendor->currency ?? 'AMD',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_collected' => $totalCollected,
            'tip_count' => $tipCount,
            'average_tip' => $averageTip,
            'eligible_orders_count' => $eligibleOrdersCount,
            'tipped_orders_count' => $tippedOrdersCount,
            'tip_rate_percentage' => $tipRatePercentage,
            'by_payment_method' => $byPaymentMethod,
            'by_order_type' => $byOrderType,
            'daily_trend' => $dailyTrend,
            'waiter_allocation' => $waiterAllocation,
            'refunded_count' => $refundedCount,
            'refunded_total' => $refundedTotal,
            'failed_count' => $failedCount,
        ];
    }

    /**
     * Compute comprehensive tenant-scoped Waiter and Service Rating reports.
     *
     * @return array<string, mixed>
     */
    public function getRatingAnalytics(Vendor $vendor, array $filters = []): array
    {
        $days = (int) ($filters['range'] ?? 30);
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : now()->subDays($days - 1)->startOfDay();
        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();

        $locationId = ! empty($filters['location_id']) ? (int) $filters['location_id'] : null;

        $baseQuery = ServiceRating::withoutGlobalScopes()
            ->where('service_ratings.vendor_id', $vendor->id)
            ->whereBetween('service_ratings.created_at', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where('service_ratings.location_id', $locationId));

        $totalRatings = (clone $baseQuery)->count();
        $averageRating = $totalRatings > 0
            ? round((float) (clone $baseQuery)->avg('service_ratings.rating'), 2)
            : 0.00;

        // Rating distribution (5, 4, 3, 2, 1)
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $rawDist = (clone $baseQuery)
            ->select('service_ratings.rating', DB::raw('COUNT(*) as count'))
            ->groupBy('service_ratings.rating')
            ->get();
        foreach ($rawDist as $row) {
            $distribution[(int) $row->rating] = (int) $row->count;
        }

        // Ratings trend over time
        $trend = (clone $baseQuery)
            ->select(DB::raw('DATE(service_ratings.created_at) as date'), DB::raw('COUNT(*) as count'), DB::raw('ROUND(AVG(service_ratings.rating), 2) as avg_rating'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) $r->date,
                'count' => (int) $r->count,
                'avg_rating' => (float) $r->avg_rating,
            ])
            ->values()
            ->toArray();

        // Category frequency
        $categoriesStats = [];
        $ratingsWithCats = (clone $baseQuery)->whereNotNull('categories')->get(['categories', 'rating']);
        foreach ($ratingsWithCats as $item) {
            if (is_array($item->categories)) {
                foreach ($item->categories as $cat) {
                    $catName = (string) $cat;
                    if (! isset($categoriesStats[$catName])) {
                        $categoriesStats[$catName] = ['name' => $catName, 'count' => 0, 'positive_count' => 0];
                    }
                    $categoriesStats[$catName]['count']++;
                    if ($item->rating >= 4) {
                        $categoriesStats[$catName]['positive_count']++;
                    }
                }
            }
        }
        usort($categoriesStats, fn ($a, $b) => $b['count'] <=> $a['count']);

        // Staff / Waiter performance metrics
        $waiterPerformance = (clone $baseQuery)
            ->whereNotNull('service_ratings.waiter_id')
            ->join('users', 'service_ratings.waiter_id', '=', 'users.id')
            ->select('users.id as waiter_id', 'users.name as waiter_name', DB::raw('COUNT(service_ratings.id) as count'), DB::raw('ROUND(AVG(service_ratings.rating), 2) as avg_rating'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('avg_rating')
            ->get()
            ->map(fn ($r) => [
                'waiter_id' => (int) $r->waiter_id,
                'waiter_name' => $r->waiter_name,
                'count' => (int) $r->count,
                'avg_rating' => (float) $r->avg_rating,
            ])
            ->values()
            ->toArray();

        return [
            'vendor_id' => $vendor->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_ratings' => $totalRatings,
            'average_rating' => $averageRating,
            'distribution' => $distribution,
            'trend' => $trend,
            'categories' => array_values($categoriesStats),
            'waiter_performance' => $waiterPerformance,
        ];
    }

    /**
     * Export tips report data to CSV format.
     */
    public function exportTipsCsv(Vendor $vendor, array $filters = []): string
    {
        $days = (int) ($filters['range'] ?? 90);
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : now()->subDays($days - 1)->startOfDay();
        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();

        $tips = Tip::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['order', 'waiter', 'location'])
            ->orderByDesc('created_at')
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'ID',
            'UUID',
            'Date & Time',
            'Order Number',
            'Table',
            'Branch',
            'Waiter / Staff',
            'Amount',
            'Currency',
            'Payment Method',
            'Payment Status',
            'Allocation Policy',
            'Settled At',
        ]);

        foreach ($tips as $t) {
            fputcsv($output, [
                $t->id,
                $t->uuid,
                $t->created_at?->format('Y-m-d H:i:s'),
                $t->order?->order_number ?? 'N/A',
                $t->table_number ?? 'N/A',
                $t->location?->name ?? 'Main',
                $t->waiter?->name ?? 'Staff Pool',
                number_format((float) $t->amount, 2, '.', ''),
                $t->currency,
                $t->payment_method,
                $t->payment_status,
                $t->allocation_policy,
                $t->settled_at?->format('Y-m-d H:i:s') ?? 'Pending',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent ?: '';
    }

    /**
     * Export ratings and feedback report data to CSV format.
     */
    public function exportRatingsCsv(Vendor $vendor, array $filters = []): string
    {
        $days = (int) ($filters['range'] ?? 90);
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : now()->subDays($days - 1)->startOfDay();
        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();

        $ratings = ServiceRating::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['order', 'waiter', 'location'])
            ->orderByDesc('created_at')
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'ID',
            'UUID',
            'Date & Time',
            'Rating (1-5)',
            'Categories',
            'Customer Comment',
            'Customer Name',
            'Is Anonymous',
            'Table',
            'Order Number',
            'Waiter',
            'Status',
        ]);

        foreach ($ratings as $r) {
            $catString = is_array($r->categories) ? implode(', ', $r->categories) : '';
            fputcsv($output, [
                $r->id,
                $r->uuid,
                $r->created_at?->format('Y-m-d H:i:s'),
                $r->rating,
                $catString,
                $r->comment ?? '',
                $r->getCustomerDisplayName(),
                $r->is_anonymous ? 'Yes' : 'No',
                $r->table_number ?? 'N/A',
                $r->order?->order_number ?? 'N/A',
                $r->waiter?->name ?? 'N/A',
                $r->status,
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent ?: '';
    }
}
