<?php

namespace App\Services;

use App\Models\EmailConfigurationAudit;
use App\Models\EmailLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmailQueueMonitorService
{
    public function __construct(
        protected AuditSanitizer $sanitizer
    ) {}

    /**
     * Get aggregate statistics on the real database queue state.
     *
     * @return array{
     *     pending_jobs: int,
     *     processing_jobs: int,
     *     failed_jobs: int,
     *     email_failed_jobs: int,
     *     oldest_pending_age: ?string,
     *     queues: array<string, int>,
     *     driver: string,
     *     is_healthy: bool
     * }
     */
    public function getQueueStats(): array
    {
        $hasJobsTable = Schema::hasTable('jobs');
        $hasFailedJobsTable = Schema::hasTable('failed_jobs');

        if (! $hasJobsTable) {
            return [
                'pending_jobs' => 0,
                'processing_jobs' => 0,
                'failed_jobs' => 0,
                'email_failed_jobs' => 0,
                'oldest_pending_age' => null,
                'queues' => [],
                'driver' => config('queue.default', 'database'),
                'is_healthy' => true,
            ];
        }

        $now = Carbon::now()->timestamp;

        $totalJobs = DB::table('jobs')->count();
        $processingJobs = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $pendingJobs = $totalJobs - $processingJobs;

        $oldestJobTimestamp = DB::table('jobs')->min('created_at');
        $oldestPendingAge = $oldestJobTimestamp ? Carbon::createFromTimestamp($oldestJobTimestamp)->diffForHumans(null, true) : null;

        $queueBreakdown = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->toArray();

        $totalFailed = $hasFailedJobsTable ? DB::table('failed_jobs')->count() : 0;

        // Calculate how many of the failed jobs are email-specific jobs
        $emailFailedCount = 0;
        if ($hasFailedJobsTable && $totalFailed > 0) {
            $failedRows = DB::table('failed_jobs')->select('payload')->get();
            foreach ($failedRows as $row) {
                if ($this->isEmailJobPayload($row->payload)) {
                    $emailFailedCount++;
                }
            }
        }

        $isHealthy = $pendingJobs < 500 && $emailFailedCount === 0;

        return [
            'pending_jobs' => $pendingJobs,
            'processing_jobs' => $processingJobs,
            'failed_jobs' => $totalFailed,
            'email_failed_jobs' => $emailFailedCount,
            'oldest_pending_age' => $oldestPendingAge,
            'queues' => $queueBreakdown,
            'driver' => config('queue.default', 'database'),
            'is_healthy' => $isHealthy,
        ];
    }

    /**
     * Inspect pending jobs in the queue with sanitized metadata (no exposed secrets).
     *
     * @return Collection<int, object>
     */
    public function getPendingJobs(int $limit = 50): Collection
    {
        if (! Schema::hasTable('jobs')) {
            return collect();
        }

        return DB::table('jobs')
            ->orderBy('id', 'asc')
            ->take($limit)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true) ?: [];
                $displayName = $payload['displayName'] ?? 'Unknown Job';
                $isEmail = $this->isEmailJobPayload($job->payload);

                return (object) [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'attempts' => $job->attempts,
                    'reserved' => ! empty($job->reserved_at),
                    'created_at' => Carbon::createFromTimestamp($job->created_at),
                    'display_name' => $displayName,
                    'is_email' => $isEmail,
                ];
            });
    }

    /**
     * Inspect failed jobs with sanitized error messages.
     *
     * @return Collection<int, object>
     */
    public function getFailedJobs(int $limit = 50, bool $emailOnly = false): Collection
    {
        if (! Schema::hasTable('failed_jobs')) {
            return collect();
        }

        $query = DB::table('failed_jobs')->latest('failed_at');

        return $query->take($limit)->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true) ?: [];
                $displayName = $payload['displayName'] ?? 'Unknown Job';
                $isEmail = $this->isEmailJobPayload($job->payload);

                // Sanitize exception message
                $rawException = $job->exception;
                $firstLine = strtok($rawException, "\n");
                $sanitizedError = $this->sanitizer->sanitizeString($firstLine ?: 'Execution failed');

                return (object) [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'failed_at' => Carbon::parse($job->failed_at),
                    'display_name' => $displayName,
                    'error_summary' => $sanitizedError,
                    'is_email' => $isEmail,
                ];
            })
            ->filter(function ($item) use ($emailOnly) {
                return ! $emailOnly || $item->is_email;
            })
            ->values();
    }

    /**
     * Determine whether a job payload is an email job.
     */
    public function isEmailJobPayload(string $rawPayload): bool
    {
        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return false;
        }

        $displayName = $payload['displayName'] ?? '';
        $commandName = $payload['data']['commandName'] ?? '';

        if (str_starts_with($displayName, 'App\\Mail\\') || str_contains($displayName, 'SendQueuedMailable')) {
            return true;
        }

        if (str_contains($commandName, 'SendQueuedMailable') || str_contains($commandName, 'Mail')) {
            return true;
        }

        return false;
    }

    /**
     * Safely retry a failed email job.
     * Rejects non-email jobs to protect business integrity.
     *
     * @return array{success: bool, message: string}
     */
    public function retryFailedJob(string $uuid): array
    {
        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        if (! $job) {
            return [
                'success' => false,
                'message' => 'Failed job not found.',
            ];
        }

        if (! $this->isEmailJobPayload($job->payload)) {
            return [
                'success' => false,
                'message' => 'Security safeguard: Non-email jobs cannot be retried via Email Management.',
            ];
        }

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);

            EmailConfigurationAudit::log(
                'email_job_retried',
                'FailedJob',
                $job->id,
                $uuid,
                ['queue' => $job->queue]
            );

            return [
                'success' => true,
                'message' => "Email job {$uuid} successfully queued for retry.",
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "Failed to retry job: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Safely delete/cancel a pending email job.
     *
     * @return array{success: bool, message: string}
     */
    public function cancelPendingJob(int $id): array
    {
        $job = DB::table('jobs')->where('id', $id)->first();
        if (! $job) {
            return [
                'success' => false,
                'message' => 'Pending job not found.',
            ];
        }

        if (! $this->isEmailJobPayload($job->payload)) {
            return [
                'success' => false,
                'message' => 'Security safeguard: Only email jobs can be cancelled via Email Management.',
            ];
        }

        DB::table('jobs')->where('id', $id)->delete();

        // Update corresponding email log if matching
        EmailLog::where('queue_job_id', (string) $id)->update([
            'status' => EmailLog::STATUS_CANCELLED,
            'error_message' => 'Cancelled by SuperAdmin operator.',
        ]);

        EmailConfigurationAudit::log(
            'email_job_cancelled',
            'QueueJob',
            $id,
            "Job #{$id}",
            ['queue' => $job->queue]
        );

        return [
            'success' => true,
            'message' => "Email job #{$id} has been cancelled and removed from the queue.",
        ];
    }
}
