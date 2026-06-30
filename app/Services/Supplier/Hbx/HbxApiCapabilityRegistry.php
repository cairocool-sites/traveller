<?php

namespace App\Services\Supplier\Hbx;

use App\Models\HbxApiCapability;
use App\Models\SupplierOperationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HbxApiCapabilityRegistry
{
    public const SUPPLIER_CODE = 'hbx_hotels';

    public function sync(): Collection
    {
        $configured = (bool) config('services.hbx.enabled') && filled(config('services.hbx.api_key')) && filled(config('services.hbx.api_secret'));
        $productionEnabled = rtrim((string) config('services.hbx.base_url'), '/') === 'https://api.hotelbeds.com';

        return collect($this->definitions())->map(function (array $definition) use ($configured, $productionEnabled): HbxApiCapability {
            $lastLog = $this->lastLog($definition['endpoint_path'] ?? null, $definition['http_method'] ?? null);

            return HbxApiCapability::query()->updateOrCreate(
                ['supplier_code' => self::SUPPLIER_CODE, 'capability_code' => $definition['capability_code']],
                array_merge($definition, [
                    'supplier_code' => self::SUPPLIER_CODE,
                    'configured' => $configured,
                    'credential_access_confirmed' => $lastLog?->successful === true || false,
                    'sandbox_tested' => $lastLog?->successful === true || false,
                    'production_enabled' => $productionEnabled && (bool) ($definition['production_capable'] ?? false),
                    'last_successful_call_at' => $lastLog?->successful ? $lastLog->created_at : null,
                    'last_sanitized_failure' => $lastLog && ! $lastLog->successful ? Str::limit((string) $lastLog->error_message, 500, '') : null,
                ]),
            );
        });
    }

    public function definitions(): array
    {
        return [
            $this->capability('booking_availability', 'Hotel Booking API', 'Availability', 'POST', '/hotel-api/1.0/hotels', true, true, true, 'Supports destination, hotel-list, occupancy, nationality, currency and language payloads. Additional official filters are schema-preserved through metadata work planned in this phase.'),
            $this->capability('booking_check_rate', 'Hotel Booking API', 'CheckRate', 'POST', '/hotel-api/1.0/checkrates', true, true, true, 'Supports one or more rate keys and replacement rate-key normalization.'),
            $this->capability('booking_confirmation', 'Hotel Booking API', 'Booking confirmation', 'POST', '/hotel-api/1.0/bookings', true, true, false, 'Implemented behind the sandbox booking guard; disabled publicly.'),
            $this->capability('booking_list', 'Hotel Booking API', 'Booking list', 'GET', '/hotel-api/1.0/bookings', false, false, false, 'Registry entry created; operation implementation is scheduled in the post-booking slice.'),
            $this->capability('booking_detail', 'Hotel Booking API', 'Booking detail', 'GET', '/hotel-api/1.0/bookings/{bookingId}', true, true, false, 'Used for reconciliation and manual review lookup.'),
            $this->capability('booking_modification', 'Hotel Booking API', 'Booking modification', 'PUT', '/hotel-api/1.0/bookings/{bookingId}', false, false, false, 'Not enabled until explicit admin workflow, simulation, and authorization checks are implemented.'),
            $this->capability('booking_cancel_simulation', 'Hotel Booking API', 'Cancellation simulation', 'DELETE', '/hotel-api/1.0/bookings/{bookingId}?cancellationFlag=SIMULATION', true, false, false, 'Adapter supports explicit internal metadata but no public or automatic flow uses it.'),
            $this->capability('booking_cancellation', 'Hotel Booking API', 'Booking cancellation', 'DELETE', '/hotel-api/1.0/bookings/{bookingId}?cancellationFlag=CANCELLATION', true, false, false, 'Implemented through the protected cancellation service; never auto-retried.'),
            $this->capability('booking_reconfirmation', 'Hotel Booking API', 'Reconfirmation retrieval', 'GET', '/hotel-api/1.0/bookings/reconfirmations', false, false, false, 'Pull-based implementation is scheduled; push/email are placeholders only.'),
            $this->capability('voucher_support', 'Hotel Booking API', 'Voucher support', null, null, true, true, false, 'Internal voucher HTML/PDF fallback exists for confirmed sandbox bookings.'),
            $this->capability('payment_data_support', 'Hotel Booking API', 'Payment-data and 3DS support', null, null, false, false, false, 'Architecture must remain disabled until PCI/security gates and HBX account model are confirmed.'),
            $this->capability('content_countries', 'Hotel Content API', 'Countries', 'GET', '/hotel-content-api/1.0/locations/countries', true, true, false),
            $this->capability('content_destinations', 'Hotel Content API', 'Destinations', 'GET', '/hotel-content-api/1.0/locations/destinations', true, true, false),
            $this->capability('content_hotels', 'Hotel Content API', 'Hotels', 'GET', '/hotel-content-api/1.0/hotels', true, true, false),
            $this->capability('content_hotel_details', 'Hotel Content API', 'Hotel details', 'GET', '/hotel-content-api/1.0/hotels/{hotelCodes}/details', true, true, false),
            $this->capability('content_master_data', 'Hotel Content API', 'Master/descriptive resources', 'GET', '/hotel-content-api/1.0/*', false, false, false, 'Rooms, boards, accommodations, categories, chains, facilities, issues, languages, promotions, terminals, currencies, images, and rate comments are tracked for the full content slice.'),
            $this->capability('cache_full', 'Hotel Cache API', 'FULL file import', null, null, false, false, false, 'Capability-gated; authorization must be detected without faking success.'),
            $this->capability('cache_incremental', 'Hotel Cache API', 'Incremental/update import', null, null, false, false, false, 'Capability-gated; cache data cannot replace live Booking API validation.'),
            $this->capability('cds_change_discovery', 'Change Discovery Service', 'Change Discovery Service', null, null, false, false, false, 'Capability-gated; Content API differential sync remains fallback.'),
            $this->capability('production_access', 'Production access', 'Production endpoint access', null, null, false, false, false, 'Production endpoint and booking remain blocked until explicit activation.'),
            $this->capability('certification_readiness', 'Certification', 'Certification readiness', null, null, true, true, false, 'Readiness command and documentation exist; certification is not claimed complete.'),
        ];
    }

    private function capability(string $code, string $family, string $name, ?string $method, ?string $path, bool $implemented, bool $adminEnabled, bool $publicEnabled, ?string $notes = null): array
    {
        return [
            'capability_code' => $code,
            'api_family' => $family,
            'display_name' => $name,
            'api_version' => '1.0',
            'http_method' => $method,
            'endpoint_path' => $path,
            'implemented' => $implemented,
            'admin_enabled' => $adminEnabled,
            'public_enabled' => $publicEnabled,
            'notes' => $notes,
        ];
    }

    private function lastLog(?string $path, ?string $method): ?SupplierOperationLog
    {
        if (! $path || ! $method || str_contains($path, '{')) {
            return null;
        }

        return SupplierOperationLog::query()
            ->whereHas('supplier', fn ($query) => $query->where('code', self::SUPPLIER_CODE))
            ->where('request_method', $method)
            ->where('request_url', $path)
            ->latest('id')
            ->first();
    }
}
