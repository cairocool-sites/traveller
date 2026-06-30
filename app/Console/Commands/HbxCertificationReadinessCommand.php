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
        $this->table(['Certification package item', 'Current owner action'], $this->packageItems());
        $this->table(['Open blocker or review item', 'Resolution needed'], $this->openItems());

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

    private function packageItems(): array
    {
        return [
            ['Workflow explanation', 'Prepare a short tester guide covering search, hotel details, CheckRate, booking, voucher, and support/review states.'],
            ['Commercial decisions', 'Confirm whether commissions, markups, taxes, fees, and customer selling prices are final for staging review.'],
            ['Certification URL', 'Use the staging subdomain only after deployment: https://travel.cairocool.com.'],
            ['Access details', 'Provide a test admin/customer path only if the deployed staging flow requires authentication.'],
            ['Payment notes', 'State that online payment is out of scope; manual payment review is the current supported flow.'],
            ['HBX-only guide', 'Explain how reviewers can test HBX results without Mock fallback or other suppliers.'],
            ['Known deviations', 'Disclose Content API HTTP 500/SYSTEM_ERROR and the disputed Sandbox booking identity as open support items.'],
        ];
    }

    private function openItems(): array
    {
        return [
            ['HBX Content API', 'Wait for HBX support response before using bulk hotel/detail content failures as certification evidence.'],
            ['Disputed Sandbox booking', 'Keep under manual review and exclude from certification evidence.'],
            ['Clean Sandbox booking evidence', 'Create exactly one new controlled Sandbox booking only after HBX blockers are resolved and owner approves.'],
            ['Voucher evidence', 'Generate from a clean confirmed booking and manually verify fields against HBX certification expectations.'],
            ['Legal and support copy', 'Review placeholder terms, privacy, payment, cancellation, and support pages before public launch.'],
            ['Staging deployment', 'Deploy to the subdomain with APP_DEBUG=false and HBX_SANDBOX_BOOKING_ENABLED=false until explicit booking verification.'],
            ['Live environment', 'Do not request live go-live or perform live tests until HBX grants live credentials and owner approves a non-NRF test.'],
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
