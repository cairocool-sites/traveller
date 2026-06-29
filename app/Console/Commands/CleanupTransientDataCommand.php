<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\SupplierOperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTransientDataCommand extends Command
{
    protected $signature = 'ops:cleanup {--dry-run : Report eligible records without deleting}';

    protected $description = 'Safely clean expired transient operational data without deleting confirmed financial history.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = [];

        DB::transaction(function () use ($dryRun, &$summary): void {
            $summary['expired_booking_drafts'] = $this->deleteOrCount(
                Booking::query()
                    ->whereIn('status', [
                        BookingStatus::Draft->value,
                        BookingStatus::PendingRateCheck->value,
                        BookingStatus::RateConfirmed->value,
                        BookingStatus::GuestDetailsCompleted->value,
                    ])
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now()),
                $dryRun,
            );
            $summary['expired_rate_checks'] = $this->deleteOrCount(
                RateCheck::query()
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->whereDoesntHave('bookings'),
                $dryRun,
            );
            $summary['expired_search_sessions'] = $this->deleteOrCount(
                SearchSession::query()
                    ->where('expires_at', '<', now())
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('rate_checks')->whereColumn('rate_checks.search_session_id', 'search_sessions.id'))
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('bookings')->whereColumn('bookings.search_session_id', 'search_sessions.id')),
                $dryRun,
            );
            $summary['old_supplier_operation_logs'] = $this->deleteOrCount(
                SupplierOperationLog::query()->where('created_at', '<', now()->subDays((int) config('travel.operations.retention.supplier_logs_days', 90))),
                $dryRun,
            );
        });

        foreach ($summary as $label => $count) {
            $this->line("{$label}: {$count}");
        }

        $this->info($dryRun ? 'Dry run complete; no records deleted.' : 'Cleanup complete.');

        return self::SUCCESS;
    }

    private function deleteOrCount($query, bool $dryRun): int
    {
        if ($dryRun) {
            return (int) $query->count();
        }

        return (int) $query->delete();
    }
}
