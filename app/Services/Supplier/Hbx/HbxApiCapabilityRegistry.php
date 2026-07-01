<?php

namespace App\Services\Supplier\Hbx;

use App\Enums\SupplierOperation;
use App\Models\HbxApiCapability;
use App\Models\Supplier;
use App\Models\SupplierOperationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HbxApiCapabilityRegistry
{
    public const SUPPLIER_CODE = 'hbx_hotels';

    public function __construct(private readonly HbxConfiguration $config) {}

    public function sync(): Collection
    {
        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        $configured = (bool) config('services.hbx.enabled') && $this->config->hasCredentials($supplier);
        $productionEnabled = rtrim((string) config('services.hbx.base_url'), '/') === 'https://api.hotelbeds.com';

        return collect($this->definitions())->map(function (array $definition) use ($configured, $productionEnabled): HbxApiCapability {
            $evidence = $this->evidence($definition);
            $lastLog = $evidence['latest'];

            return HbxApiCapability::query()->updateOrCreate(
                ['supplier_code' => self::SUPPLIER_CODE, 'capability_code' => $definition['capability_code']],
                array_merge($definition, [
                    'supplier_code' => self::SUPPLIER_CODE,
                    'configured' => $configured,
                    'credential_access_confirmed' => $evidence['successful'],
                    'sandbox_tested' => $evidence['successful'],
                    'production_enabled' => $productionEnabled && (bool) ($definition['production_capable'] ?? false),
                    'last_successful_call_at' => $evidence['successful_at'],
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
            $this->capability('booking_list', 'Hotel Booking API', 'Booking list', 'GET', '/hotel-api/1.0/bookings', true, true, false, 'Implemented for protected reconciliation/admin diagnostics with official pagination and filters.'),
            $this->capability('booking_detail', 'Hotel Booking API', 'Booking detail', 'GET', '/hotel-api/1.0/bookings/{bookingId}', true, true, false, 'Used for reconciliation and manual review lookup.'),
            $this->capability('booking_modification', 'Hotel Booking API', 'Booking modification', 'PUT', '/hotel-api/1.0/bookings/{bookingId}', true, false, false, 'Low-level adapter support exists for simulation/update payloads; no public customer workflow is enabled.'),
            $this->capability('booking_cancel_simulation', 'Hotel Booking API', 'Cancellation simulation', 'DELETE', '/hotel-api/1.0/bookings/{bookingId}?cancellationFlag=SIMULATION', true, true, false, 'Adapter supports explicit cancellation simulation using separate idempotency from actual cancellation.'),
            $this->capability('booking_cancellation', 'Hotel Booking API', 'Booking cancellation', 'DELETE', '/hotel-api/1.0/bookings/{bookingId}?cancellationFlag=CANCELLATION', true, false, false, 'Implemented through the protected cancellation service; never auto-retried.'),
            $this->capability('booking_reconfirmation', 'Hotel Booking API', 'Reconfirmation retrieval', 'GET', '/hotel-api/1.0/bookings/reconfirmations', true, true, false, 'Pull-based reconfirmation retrieval is implemented; push/email services remain external HBX setup items.'),
            $this->capability('voucher_support', 'Hotel Booking API', 'Voucher support', null, null, true, true, false, 'Internal voucher HTML/PDF fallback exists for confirmed sandbox bookings.'),
            $this->capability('payment_data_support', 'Hotel Booking API', 'Payment-data and 3DS support', null, null, false, false, false, 'Architecture must remain disabled until PCI/security gates and HBX account model are confirmed.'),
            $this->capability('content_countries', 'Hotel Content API', 'Countries', 'GET', '/hotel-content-api/1.0/locations/countries', true, true, false),
            $this->capability('content_destinations', 'Hotel Content API', 'Destinations', 'GET', '/hotel-content-api/1.0/locations/destinations', true, true, false),
            $this->capability('content_hotels', 'Hotel Content API', 'Hotels', 'GET', '/hotel-content-api/1.0/hotels', true, true, false),
            $this->capability('content_hotel_details', 'Hotel Content API', 'Hotel details', 'GET', '/hotel-content-api/1.0/hotels/{hotelCodes}/details', true, true, false),
            $this->capability('content_hotel_images', 'Hotel Content API', 'Hotel images', 'GET', '/hotel-content-api/1.0/hotels/{hotelCodes}/details', true, true, true, 'Hotel images are normalized from Content API hotel details and stored locally; Booking API images must not be treated as canonical content.'),
            $this->capability('content_rooms', 'Hotel Content API', 'Room types', 'GET', '/hotel-content-api/1.0/types/rooms', true, true, false),
            $this->capability('content_boards', 'Hotel Content API', 'Board bases', 'GET', '/hotel-content-api/1.0/types/boards', true, true, false),
            $this->capability('content_board_groups', 'Hotel Content API', 'Board groups', 'GET', '/hotel-content-api/1.0/types/boardgroups', true, true, false),
            $this->capability('content_accommodations', 'Hotel Content API', 'Accommodation types', 'GET', '/hotel-content-api/1.0/types/accommodations', true, true, false),
            $this->capability('content_categories', 'Hotel Content API', 'Hotel categories', 'GET', '/hotel-content-api/1.0/types/categories', true, true, false),
            $this->capability('content_classifications', 'Hotel Content API', 'Hotel classifications', 'GET', '/hotel-content-api/1.0/types/classifications', true, true, false),
            $this->capability('content_group_categories', 'Hotel Content API', 'Category groups', 'GET', '/hotel-content-api/1.0/types/groupcategories', true, true, false),
            $this->capability('content_chains', 'Hotel Content API', 'Hotel chains', 'GET', '/hotel-content-api/1.0/types/chains', true, true, false),
            $this->capability('content_facilities', 'Hotel Content API', 'Facilities', 'GET', '/hotel-content-api/1.0/types/facilities', true, true, false),
            $this->capability('content_facility_groups', 'Hotel Content API', 'Facility groups', 'GET', '/hotel-content-api/1.0/types/facilitygroups', true, true, false),
            $this->capability('content_facility_typologies', 'Hotel Content API', 'Facility typologies', 'GET', '/hotel-content-api/1.0/types/facilitytypologies', true, true, false),
            $this->capability('content_issues', 'Hotel Content API', 'Issue codes', 'GET', '/hotel-content-api/1.0/types/issues', true, true, false),
            $this->capability('content_languages', 'Hotel Content API', 'Languages', 'GET', '/hotel-content-api/1.0/types/languages', true, true, false),
            $this->capability('content_promotions', 'Hotel Content API', 'Promotions', 'GET', '/hotel-content-api/1.0/types/promotions', true, true, false),
            $this->capability('content_segments', 'Hotel Content API', 'Segments', 'GET', '/hotel-content-api/1.0/types/segments', true, true, false),
            $this->capability('content_image_types', 'Hotel Content API', 'Image types', 'GET', '/hotel-content-api/1.0/types/imagetypes', true, true, false),
            $this->capability('content_currencies', 'Hotel Content API', 'Currencies', 'GET', '/hotel-content-api/1.0/types/currencies', true, true, false),
            $this->capability('content_terminals', 'Hotel Content API', 'Terminals', 'GET', '/hotel-content-api/1.0/types/terminals', true, true, false),
            $this->capability('content_rate_comments', 'Hotel Content API', 'Rate comments', 'GET', '/hotel-content-api/1.0/types/ratecomments', true, true, false),
            $this->capability('content_master_data', 'Hotel Content API', 'Master/descriptive resources', 'GET', '/hotel-content-api/1.0/types/*', true, true, false, 'Rooms, boards, accommodations, categories, chains, facilities, issues, languages, promotions, terminals, currencies, images, and rate comments are tracked for the full content slice. Some resources may still require HBX account authorization.'),
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

    private function evidence(array $definition): array
    {
        $operations = $this->operationsFor($definition['capability_code']);
        $path = $definition['endpoint_path'] ?? null;
        $method = $definition['http_method'] ?? null;

        if ($operations === [] && ! $path && ! $method) {
            return [
                'latest' => null,
                'successful' => false,
                'successful_at' => null,
            ];
        }

        $query = SupplierOperationLog::query()
            ->whereHas('supplier', fn ($query) => $query->where('code', self::SUPPLIER_CODE));

        if ($operations !== []) {
            $query->whereIn('operation', array_map(fn (SupplierOperation $operation): string => $operation->value, $operations));
        }

        if ($method) {
            $query->where('request_method', $method);
        }

        if ($path) {
            $query->where(function ($query) use ($path): void {
                foreach ($this->pathPatterns($path) as $pattern) {
                    $query->orWhere('request_url', 'like', $pattern);
                }
            });
        }

        $latest = (clone $query)->latest('id')->first();
        $successful = (clone $query)->where('successful', true)->latest('id')->first();

        return [
            'latest' => $latest,
            'successful' => $successful !== null,
            'successful_at' => $successful?->created_at,
        ];
    }

    /**
     * @return array<int, SupplierOperation>
     */
    private function operationsFor(string $capabilityCode): array
    {
        if (str_starts_with($capabilityCode, 'content_')) {
            return [SupplierOperation::HotelDetails];
        }

        return match ($capabilityCode) {
            'booking_availability' => [SupplierOperation::Search],
            'booking_check_rate' => [SupplierOperation::CheckRate],
            'booking_confirmation' => [SupplierOperation::Book],
            'booking_list' => [SupplierOperation::BookingList],
            'booking_detail' => [SupplierOperation::GetBooking],
            'booking_modification' => [SupplierOperation::BookingChange],
            'booking_cancel_simulation' => [SupplierOperation::CancellationSimulation],
            'booking_cancellation' => [SupplierOperation::Cancel],
            'booking_reconfirmation' => [SupplierOperation::BookingReconfirmation],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function pathPatterns(string $path): array
    {
        if ($path === '/hotel-content-api/1.0/types/*') {
            return ['/hotel-content-api/1.0/types/%'];
        }

        if (str_contains($path, '{bookingId}')) {
            return [str_replace('{bookingId}', '%', $path).'%'];
        }

        if (str_contains($path, '{hotelCodes}')) {
            return [str_replace('{hotelCodes}', '%', $path).'%'];
        }

        return [$path, $path.'?%'];
    }
}
