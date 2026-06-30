<?php

namespace App\Console\Commands;

use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\SupplierOperationLog;
use App\Services\Supplier\Hbx\HbxApiCapabilityRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class HbxCertificationReadinessCommand extends Command
{
    protected $signature = 'hbx:certification:readiness';

    protected $description = 'Display a safe HBX certification-readiness checklist without making supplier calls.';

    public function handle(HbxApiCapabilityRegistry $capabilities): int
    {
        $matrix = $capabilities->sync()->keyBy('capability_code');
        $items = $this->items($matrix);

        $this->info('HBX certification readiness checklist');
        $this->line('Source: HBX Hotels Knowledge Base certification process.');
        $this->line('No supplier request was sent by this command.');
        $this->line('No booking, modification, cancellation, or production request was sent.');

        $this->table(['Area', 'Requirement', 'Status', 'Evidence'], $items);

        $blocked = collect($items)->contains(fn (array $item): bool => $item[2] === 'blocked');
        $this->line('Overall: '.($blocked ? 'not ready for certification request' : 'ready for manual evidence review'));

        return self::SUCCESS;
    }

    private function items($matrix): array
    {
        $availability = $matrix->get('booking_availability');
        $checkRate = $matrix->get('booking_check_rate');
        $booking = $matrix->get('booking_confirmation');
        $contentHotels = $matrix->get('content_hotels');
        $contentDetails = $matrix->get('content_hotel_details');
        $voucher = $matrix->get('voucher_support');

        return [
            ['Technical', 'Signed JSON requests and gzip support', $this->ready($availability?->implemented), 'HBX clients send Api-key, X-Signature, Accept JSON; Content API also sends Accept-Encoding gzip.'],
            ['Technical', 'Sandbox endpoint only during development', $this->sandboxStatus(), 'Production remains blocked unless services.hbx.base_url is explicitly changed in a later approved step.'],
            ['Workflow', 'Availability before CheckRate/Booking', $this->ready($availability?->implemented && $checkRate?->implemented && $booking?->implemented), 'Public and manual flows resolve opaque local IDs, then call Availability, CheckRate when required, then guarded Booking.'],
            ['Workflow', 'No repeated Availability during booking step', $this->ready(true), 'BookingService submits from stored RateCheck snapshots and does not re-run Availability.'],
            ['Availability/CheckRate/Confirmation', 'CheckRate only when applicable and booking guarded', $this->ready($checkRate?->implemented), 'RECHECK requires CheckRate; BOOKABLE may proceed from freshness rules; sandbox booking guard defaults disabled.'],
            ['Availability/CheckRate/Confirmation', 'Sandbox confirmation evidence', $this->manual((bool) $booking?->sandbox_tested), $booking?->sandbox_tested ? 'Sanitized successful sandbox booking log exists.' : 'Needs one controlled HBX sandbox booking after approval.'],
            ['Voucher', 'Voucher for confirmed bookings', $this->ready($voucher?->implemented && Route::has('admin.bookings.voucher')), 'Protected internal voucher route exists for confirmed or manual-review bookings.'],
            ['Voucher', 'Voucher customer/supplier data review', $this->manual(false), 'Manual evidence must confirm hotel, passenger, booking, room, board, rate comments if applicable, and sandbox/test notice.'],
            ['Content', 'Content API implementation documented', $this->partial($contentHotels?->implemented && $contentDetails?->implemented), 'Countries, destinations, hotels, details, diagnostics, and code-based fallback are implemented.'],
            ['Content', 'Real hotel content stored locally', HbxHotel::query()->where('supplier_code', 'hbx_hotels')->exists() ? 'partial' : 'blocked', 'Stored HBX hotels: '.HbxHotel::query()->where('supplier_code', 'hbx_hotels')->count().'; destinations: '.HbxDestination::query()->where('supplier_code', 'hbx_hotels')->count().'.'],
            ['Content', 'Content API supplier errors tracked', $this->partial($this->latestHotelsLog() !== null), $this->latestHotelsLogEvidence($contentHotels?->last_sanitized_failure)],
            ['Live environment', 'Live booking and cancellation', 'blocked', 'Out of scope until HBX grants live keys and owner approves a real non-NRF live test.'],
            ['Certification request', 'Information package ready', 'manual', 'Prepare workflow, commercial decisions, certification URL/access, payment notes, HBX-only testing guide, and known deviations.'],
        ];
    }

    private function ready(mixed $value): string
    {
        return $value ? 'ready' : 'blocked';
    }

    private function partial(mixed $value): string
    {
        return $value ? 'partial' : 'blocked';
    }

    private function manual(bool $done): string
    {
        return $done ? 'ready' : 'manual';
    }

    private function sandboxStatus(): string
    {
        return rtrim((string) config('services.hbx.base_url'), '/') === 'https://api.test.hotelbeds.com'
            ? 'ready'
            : 'blocked';
    }

    private function latestHotelsLog(): ?SupplierOperationLog
    {
        return SupplierOperationLog::query()
            ->where('request_method', 'GET')
            ->where('request_url', '/hotel-content-api/1.0/hotels')
            ->latest('id')
            ->first();
    }

    private function latestHotelsLogEvidence(?string $fallback): string
    {
        $log = $this->latestHotelsLog();

        if (! $log) {
            return $fallback ?: 'No sanitized hotels request log recorded.';
        }

        return 'Latest hotels request status: '.($log->response_status ?: 'no response')
            .'; successful: '.($log->successful ? 'yes' : 'no')
            .'; error type: '.($log->error_type?->value ?: 'none');
    }
}
