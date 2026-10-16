<?php

namespace App\Services;

use App\Models\EmailConnection;
use App\Models\EmailLog;
use App\Models\EmailProviderEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailAnalyticsService
{
    /**
     * Compute comprehensive database-backed metrics for the overview dashboard and reports.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getOverviewMetrics(array $filters = []): array
    {
        $baseQuery = EmailLog::query();
        $this->applyFilters($baseQuery, $filters);

        // Run aggregated query for speed and efficiency (single DB trip)
        $stats = (clone $baseQuery)->selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'queued' THEN 1 END) as count_queued,
            COUNT(CASE WHEN status = 'processing' THEN 1 END) as count_processing,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as count_sent,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as count_delivered,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as count_failed,
            COUNT(CASE WHEN status = 'bounced' THEN 1 END) as count_bounced,
            COUNT(CASE WHEN status = 'complained' THEN 1 END) as count_complained,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as count_cancelled,
            AVG(duration_ms) as avg_duration,
            MAX(created_at) as last_dispatch,
            MAX(CASE WHEN status IN ('sent', 'delivered') THEN created_at END) as last_successful_dispatch
        ")->first();

        $total = (int) ($stats->total ?? 0);
        $queued = (int) ($stats->count_queued ?? 0);
        $processing = (int) ($stats->count_processing ?? 0);
        $sent = (int) ($stats->count_sent ?? 0);
        $delivered = (int) ($stats->count_delivered ?? 0);
        $failed = (int) ($stats->count_failed ?? 0);
        $bounced = (int) ($stats->count_bounced ?? 0);
        $complained = (int) ($stats->count_complained ?? 0);
        $cancelled = (int) ($stats->count_cancelled ?? 0);

        $acceptedByTransport = $sent + $delivered;
        $totalProcessed = $acceptedByTransport + $failed + $bounced;

        $sentRate = $totalProcessed > 0 ? round(($acceptedByTransport / $totalProcessed) * 100, 1) : 0;
        $failureRate = $totalProcessed > 0 ? round((($failed + $bounced) / $totalProcessed) * 100, 1) : 0;
        $bounceRate = $totalProcessed > 0 ? round(($bounced / $totalProcessed) * 100, 1) : 0;

        $hasProviderEvents = EmailProviderEvent::exists();
        $verifiedDeliveryRate = ($hasProviderEvents && $acceptedByTransport > 0)
            ? round(($delivered / $acceptedByTransport) * 100, 1)
            : null;

        $lastProviderEvent = EmailProviderEvent::latest('created_at')->value('created_at');
        $defaultConnection = EmailConnection::where('is_default', true)->first();

        return [
            'total_emails' => $total,
            'queued' => $queued,
            'processing' => $processing,
            'accepted_by_transport' => $acceptedByTransport,
            'delivered_verified' => $delivered,
            'failed' => $failed,
            'bounced' => $bounced,
            'complained' => $complained,
            'cancelled' => $cancelled,
            'sent_rate' => $sentRate,
            'failure_rate' => $failureRate,
            'bounce_rate' => $bounceRate,
            'verified_delivery_rate' => $verifiedDeliveryRate,
            'has_provider_tracking' => $hasProviderEvents,
            'avg_duration_ms' => (int) round($stats->avg_duration ?? 0),
            'last_dispatch_at' => $stats->last_dispatch ? Carbon::parse($stats->last_dispatch) : null,
            'last_successful_dispatch_at' => $stats->last_successful_dispatch ? Carbon::parse($stats->last_successful_dispatch) : null,
            'last_provider_event_at' => $lastProviderEvent ? Carbon::parse($lastProviderEvent) : null,
            'default_connection_status' => $defaultConnection?->last_test_status ?? ($defaultConnection?->is_active ? 'active' : 'inactive'),
            'default_connection_name' => $defaultConnection?->name ?? 'None',
            'last_tested_at' => $defaultConnection?->last_tested_at,
        ];
    }

    /**
     * Volume aggregation by date for trendline chart.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getVolumeByDay(array $filters = [], int $days = 14): Collection
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $rows = DB::table('email_logs')
            ->selectRaw("DATE(created_at) as date, COUNT(*) as total, COUNT(CASE WHEN status IN ('sent', 'delivered') THEN 1 END) as success, COUNT(CASE WHEN status IN ('failed', 'bounced') THEN 1 END) as failed")
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fill complete continuous date range
        $chartData = collect();
        for ($i = $days; $i >= 0; $i--) {
            $d = Carbon::now()->subDays($i)->format('Y-m-d');
            $chartData->push((object) [
                'date' => $d,
                'label' => Carbon::parse($d)->format('M d'),
                'total' => (int) ($rows[$d]->total ?? 0),
                'success' => (int) ($rows[$d]->success ?? 0),
                'failed' => (int) ($rows[$d]->failed ?? 0),
            ]);
        }

        return $chartData;
    }

    /**
     * Volume breakdown grouped by category.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getVolumeByCategory(array $filters = []): Collection
    {
        $query = DB::table('email_logs')
            ->selectRaw('category, COUNT(*) as total, COUNT(CASE WHEN status IN (\'sent\', \'delivered\') THEN 1 END) as success, COUNT(CASE WHEN status IN (\'failed\', \'bounced\') THEN 1 END) as failed')
            ->groupBy('category')
            ->orderByDesc('total');

        return $query->get();
    }

    /**
     * Volume breakdown grouped by sender identity.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getVolumeBySender(array $filters = []): Collection
    {
        return DB::table('email_logs')
            ->selectRaw('from_email, from_name, COUNT(*) as total, COUNT(CASE WHEN status IN (\'sent\', \'delivered\') THEN 1 END) as success')
            ->groupBy('from_email', 'from_name')
            ->orderByDesc('total')
            ->take(10)
            ->get();
    }

    /**
     * Volume breakdown grouped by SMTP connection.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getVolumeByConnection(array $filters = []): Collection
    {
        return DB::table('email_logs')
            ->selectRaw('connection_name, transport, COUNT(*) as total, COUNT(CASE WHEN status IN (\'sent\', \'delivered\') THEN 1 END) as success, COUNT(CASE WHEN status IN (\'failed\', \'bounced\') THEN 1 END) as failed')
            ->groupBy('connection_name', 'transport')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Distribution of most common failure reasons.
     *
     * @return Collection<int, object>
     */
    public function getCommonFailureReasons(int $limit = 10): Collection
    {
        return DB::table('email_logs')
            ->selectRaw('COALESCE(error_code, \'ERROR\') as error_code, SUBSTRING(error_message FROM 1 FOR 120) as summary, COUNT(*) as occurrences, MAX(last_attempt_at) as last_occurred_at')
            ->whereIn('status', [EmailLog::STATUS_FAILED, EmailLog::STATUS_BOUNCED])
            ->whereNotNull('error_message')
            ->groupBy('error_code', DB::raw('SUBSTRING(error_message FROM 1 FOR 120)'))
            ->orderByDesc('occurrences')
            ->take($limit)
            ->get();
    }

    /**
     * Export aggregated analytics report to CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportReportCsv(array $filters = []): StreamedResponse
    {
        $metrics = $this->getOverviewMetrics($filters);
        $categories = $this->getVolumeByCategory($filters);
        $senders = $this->getVolumeBySender($filters);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="email_reports_summary_'.date('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($metrics, $categories, $senders) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['--- EMAIL MANAGEMENT EXECUTIVE SUMMARY ---']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Emails Processed', $metrics['total_emails']]);
            fputcsv($handle, ['Accepted by Transport (Sent)', $metrics['accepted_by_transport']]);
            fputcsv($handle, ['Verified Delivered', $metrics['delivered_verified']]);
            fputcsv($handle, ['Failed', $metrics['failed']]);
            fputcsv($handle, ['Bounced', $metrics['bounced']]);
            fputcsv($handle, ['Sent Rate (%)', $metrics['sent_rate'].'%']);
            fputcsv($handle, ['Failure Rate (%)', $metrics['failure_rate'].'%']);
            fputcsv($handle, ['Verified Delivery Rate (%)', $metrics['verified_delivery_rate'] !== null ? $metrics['verified_delivery_rate'].'%' : 'Not tracked (SMTP only)']);
            fputcsv($handle, ['Average Dispatch Duration (ms)', $metrics['avg_duration_ms']]);
            fputcsv($handle, []);

            fputcsv($handle, ['--- BREAKDOWN BY CATEGORY ---']);
            fputcsv($handle, ['Category', 'Total', 'Success', 'Failed']);
            foreach ($categories as $cat) {
                fputcsv($handle, [$cat->category, $cat->total, $cat->success, $cat->failed]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['--- BREAKDOWN BY SENDER IDENTITY ---']);
            fputcsv($handle, ['Sender Email', 'Sender Name', 'Total', 'Success']);
            foreach ($senders as $s) {
                fputcsv($handle, [$s->from_email, $s->from_name, $s->total, $s->success]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Apply date range and filter criteria to query.
     */
    protected function applyFilters(object $query, array $filters): void
    {
        $range = $filters['range'] ?? '30d';
        $now = Carbon::now();

        if ($range === 'today') {
            $query->where('created_at', '>=', $now->copy()->startOfDay());
        } elseif ($range === '7d') {
            $query->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay());
        } elseif ($range === '30d') {
            $query->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay());
        } elseif ($range === 'custom') {
            if (! empty($filters['from_date'])) {
                $query->where('created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
            }
            if (! empty($filters['to_date'])) {
                $query->where('created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
            }
        }

        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['connection_id']) && $filters['connection_id'] !== 'all') {
            $query->where('email_connection_id', (int) $filters['connection_id']);
        }

        if (! empty($filters['sender_id']) && $filters['sender_id'] !== 'all') {
            $query->where('sender_identity_id', (int) $filters['sender_id']);
        }
    }
}
