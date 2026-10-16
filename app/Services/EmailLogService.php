<?php

namespace App\Services;

use App\Models\EmailLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLogService
{
    /**
     * Build base query with standard filters applied.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildQuery(array $filters = []): Builder
    {
        $query = EmailLog::with(['senderIdentity', 'connection', 'vendor'])
            ->latest('created_at');

        // Search term
        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('recipient_email', 'ilike', "%{$term}%")
                    ->orWhere('recipient_name', 'ilike', "%{$term}%")
                    ->orWhere('subject', 'ilike', "%{$term}%")
                    ->orWhere('uuid', 'ilike', "%{$term}%")
                    ->orWhere('provider_message_id', 'ilike', "%{$term}%");
            });
        }

        // Category filter
        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        // Status filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Sender filter
        if (! empty($filters['sender_id']) && $filters['sender_id'] !== 'all') {
            $query->where('sender_identity_id', (int) $filters['sender_id']);
        }

        // Connection filter
        if (! empty($filters['connection_id']) && $filters['connection_id'] !== 'all') {
            $query->where('email_connection_id', (int) $filters['connection_id']);
        }

        // Vendor filter
        if (! empty($filters['vendor_id']) && $filters['vendor_id'] !== 'all') {
            $query->where('vendor_id', (int) $filters['vendor_id']);
        }

        // Date range filter
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

        return $query;
    }

    /**
     * Get paginated logs.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getFilteredLogs(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->buildQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Stream CSV export of email logs without loading entire table into memory.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportLogsCsv(array $filters = []): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="email_logs_export_'.date('Y-m-d_His').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($handle, [
                'Log ID',
                'UUID',
                'Timestamp',
                'Recipient Email',
                'Recipient Name',
                'Subject',
                'Category',
                'From Email',
                'From Name',
                'Connection',
                'Transport',
                'Status',
                'Attempts',
                'Duration (ms)',
                'Provider Message ID',
                'Vendor ID',
                'Delivered At',
                'Error Message',
            ]);

            $this->buildQuery($filters)->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->uuid,
                        $log->created_at?->toIso8601String(),
                        $log->recipient_email,
                        $log->recipient_name,
                        $log->subject,
                        $log->category,
                        $log->from_email,
                        $log->from_name,
                        $log->connection_name,
                        $log->transport,
                        $log->status,
                        $log->attempts,
                        $log->duration_ms,
                        $log->provider_message_id,
                        $log->vendor_id,
                        $log->delivered_at?->toIso8601String(),
                        $log->error_message ? str_replace(["\r", "\n"], ' ', $log->error_message) : '',
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
