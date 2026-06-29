<?php

namespace App\Support\Operations;

use App\Enums\BookingStatus;
use App\Enums\CancellationStatus;
use App\Enums\ManualPaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\OperationalHeartbeat;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SupplierOperationLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemHealthService
{
    /**
     * @return array{ok: bool, checks: array<string, array{ok: bool, message: string}>}
     */
    public function readiness(): array
    {
        $checks = [
            'database' => $this->check(fn () => DB::connection()->getPdo() !== null),
            'cache' => $this->check(fn () => Cache::put('ops:readiness', 'ok', 10)),
            'storage' => $this->check(fn () => Storage::disk('local')->put('ops/.readiness', 'ok') && Storage::disk('local')->delete('ops/.readiness') !== false),
            'queue' => [
                'ok' => filled(config('queue.default')),
                'message' => filled(config('queue.default')) ? 'configured' : 'missing',
            ],
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check): bool => $check['ok']),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSummary(): array
    {
        $scheduler = OperationalHeartbeat::query()->where('key', 'scheduler')->first();
        $staleAfterMinutes = (int) config('travel.operations.scheduler_stale_after_minutes', 5);

        return [
            'application' => ['ok' => true, 'message' => app()->environment()],
            'readiness' => $this->readiness()['checks'],
            'scheduler' => [
                'ok' => $scheduler?->last_seen_at !== null && $scheduler->last_seen_at->greaterThan(now()->subMinutes($staleAfterMinutes)),
                'message' => $scheduler?->last_seen_at?->diffForHumans() ?? 'no heartbeat recorded',
            ],
            'failed_jobs' => $this->safeCount('failed_jobs'),
            'pending_manual_review_bookings' => Booking::query()->where('status', BookingStatus::ManualReview->value)->count(),
            'pending_payment_reviews' => Payment::query()->whereIn('status', [ManualPaymentStatus::Submitted->value, ManualPaymentStatus::UnderReview->value])->count(),
            'pending_cancellation_reviews' => BookingCancellation::query()->whereIn('status', [CancellationStatus::Requested->value, CancellationStatus::UnderReview->value])->count(),
            'pending_refund_reviews' => Refund::query()->whereIn('status', [RefundStatus::Pending->value, RefundStatus::UnderReview->value, RefundStatus::Approved->value, RefundStatus::Processing->value])->count(),
            'recent_supplier_failures' => SupplierOperationLog::query()->where('successful', false)->where('created_at', '>=', now()->subDay())->count(),
            'mail' => ['ok' => filled(config('mail.default')), 'message' => filled(config('mail.default')) ? 'configured' : 'missing'],
            'backup' => ['ok' => false, 'message' => 'manual metadata not configured yet'],
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function check(callable $callback): array
    {
        try {
            return ['ok' => (bool) $callback(), 'message' => 'ok'];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'unavailable'];
        }
    }

    private function safeCount(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
