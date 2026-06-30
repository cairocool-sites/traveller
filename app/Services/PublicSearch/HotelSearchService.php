<?php

namespace App\Services\PublicSearch;

use App\Enums\SupplierErrorType;
use App\Enums\SupplierOperation;
use App\Models\Currency;
use App\Models\HbxHotel;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Services\Supplier\CorrelationIdFactory;
use App\Services\Supplier\Data\HotelSearchRequestData;
use App\Services\Supplier\Data\RoomOccupancyData;
use App\Services\Supplier\Data\SupplierHotelData;
use App\Services\Supplier\Exceptions\SupplierAuthenticationException;
use App\Services\Supplier\Exceptions\SupplierException;
use App\Services\Supplier\Exceptions\SupplierRateLimitException;
use App\Services\Supplier\Exceptions\SupplierTimeoutException;
use App\Services\Supplier\Exceptions\UnavailableSupplierException;
use App\Services\Supplier\SupplierManager;
use App\Services\Supplier\SupplierOperationLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class HotelSearchService
{
    public function __construct(
        private readonly DestinationLookupService $destinations,
        private readonly SupplierManager $suppliers,
        private readonly CorrelationIdFactory $correlationIds,
        private readonly SupplierOperationLogger $logger,
        private readonly CancellationSummaryService $cancellations,
        private readonly OfferPricingService $pricing,
        private readonly SupplierDestinationResolver $supplierDestinations,
    ) {}

    public function search(array $criteria, ?string $anonymousSessionId = null): SearchSession
    {
        $validated = $this->validate($criteria);
        $destination = $this->destinations->resolve($validated['destination'], $validated['locale']);
        $correlationId = $this->correlationIds->make($criteria['correlation_id'] ?? null);
        $rooms = $this->rooms($validated);
        $warnings = [];
        $results = [];

        foreach ($this->eligibleSuppliers() as $supplier) {
            try {
                $supplierDestination = $supplier->code === 'hbx_hotels'
                    ? $this->supplierDestinations->forHbx($destination)
                    : ['destination_code' => $destination->supplierIdentifier, 'hotel_codes' => []];
                $adapter = $this->suppliers->resolve($supplier->code, SupplierOperation::Search);
                $response = $adapter->search(new HotelSearchRequestData(
                    destinationIdentifier: $supplierDestination['destination_code'],
                    checkIn: CarbonImmutable::parse($validated['check_in']),
                    checkOut: CarbonImmutable::parse($validated['check_out']),
                    rooms: $rooms,
                    currency: $validated['currency'],
                    locale: $validated['locale'],
                    nationality: $validated['nationality'] ?? null,
                    residencyCountry: $validated['residency_country'] ?? null,
                    correlationId: $correlationId,
                    metadata: array_filter([
                        'scenario' => $criteria['scenario'] ?? null,
                        'hotel_codes' => $supplierDestination['hotel_codes'],
                    ]),
                ));

                $warnings = array_merge($warnings, $response->warnings);
                $results = array_merge($results, $this->normalizeHotels($response->hotels, $validated['locale'], $response->supplierCode));

                if ($supplier->code === 'hbx_hotels') {
                    break;
                }
            } catch (Throwable $exception) {
                $warnings[] = $this->customerWarning($exception);
                $this->logFailure($supplier, $correlationId, $criteria, $exception);

                if ($supplier->code === 'hbx_hotels') {
                    break;
                }
            }
        }

        return SearchSession::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'destination_type' => $destination->type,
            'destination_id' => $destination->id,
            'destination_label' => $destination->label,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'occupancy' => array_map(fn (RoomOccupancyData $room): array => $room->jsonSerialize(), $rooms),
            'nationality' => $validated['nationality'] ?? null,
            'residency_country' => $validated['residency_country'] ?? null,
            'currency' => $validated['currency'],
            'locale' => $validated['locale'],
            'anonymous_session_id' => $anonymousSessionId ? hash('sha256', $anonymousSessionId) : null,
            'user_id' => Auth::id(),
            'correlation_id' => $correlationId,
            'criteria_snapshot' => $validated,
            'results_snapshot' => collect($results)->take(config('travel.public_search.results_limit'))->values()->all(),
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'expires_at' => now()->addMinutes(config('travel.public_search.session_lifetime_minutes')),
        ]);
    }

    public function filteredResults(SearchSession $session, array $filters = []): Collection
    {
        $results = collect($session->results_snapshot ?? []);

        if (filled($filters['name'] ?? null)) {
            $name = mb_strtolower($filters['name']);
            $results = $results->filter(fn (array $hotel): bool => str_contains(mb_strtolower($hotel['name']), $name));
        }

        if (filled($filters['star_rating'] ?? null)) {
            $results = $results->where('star_rating', (int) $filters['star_rating']);
        }

        if (filled($filters['refundability'] ?? null)) {
            $results = $results->filter(fn (array $hotel): bool => collect($hotel['rates'])->contains('refundability', $filters['refundability']));
        }

        if (filled($filters['board_basis'] ?? null)) {
            $results = $results->filter(fn (array $hotel): bool => collect($hotel['rates'])->contains('board_basis', $filters['board_basis']));
        }

        if (filled($filters['area'] ?? null)) {
            $area = mb_strtolower($filters['area']);
            $results = $results->filter(fn (array $hotel): bool => str_contains(mb_strtolower($hotel['location']), $area));
        }

        $sort = $filters['sort'] ?? 'recommended';

        return match ($sort) {
            'price_asc' => $results->sortBy('minimum_price_minor')->values(),
            'price_desc' => $results->sortByDesc('minimum_price_minor')->values(),
            'star_rating' => $results->sortByDesc('star_rating')->values(),
            default => $results->sortByDesc('canonical_hotel_id')->values(),
        };
    }

    public function resultFor(SearchSession $session, string $token): ?array
    {
        if ($session->isExpired()) {
            return null;
        }

        return collect($session->results_snapshot ?? [])->firstWhere('public_token', $token);
    }

    private function validate(array $criteria): array
    {
        $locale = $criteria['locale'] ?? app()->getLocale();
        $currency = strtoupper(config('travel.currency.default'));
        $rooms = (int) ($criteria['rooms'] ?? 1);
        $adults = (int) ($criteria['adults'] ?? 2);
        $children = (int) ($criteria['children'] ?? 0);
        $childAges = array_values(array_filter((array) ($criteria['child_ages'] ?? []), fn ($age): bool => $age !== ''));
        $checkIn = CarbonImmutable::parse($criteria['check_in'] ?? now()->addDay()->toDateString());
        $checkOut = CarbonImmutable::parse($criteria['check_out'] ?? now()->addDays(2)->toDateString());

        if (! in_array($locale, config('travel.locales.supported'), true)) {
            throw ValidationException::withMessages(['locale' => __('public.search.validation.locale')]);
        }

        if (! Currency::query()->where('code', $currency)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['currency' => __('public.search.validation.currency')]);
        }

        if ($checkIn->isPast() && ! $checkIn->isToday()) {
            throw ValidationException::withMessages(['check_in' => __('public.search.validation.check_in')]);
        }

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw ValidationException::withMessages(['check_out' => __('public.search.validation.check_out')]);
        }

        if ($checkIn->diffInDays($checkOut) > config('travel.public_search.max_stay_nights')) {
            throw ValidationException::withMessages(['check_out' => __('public.search.validation.max_stay')]);
        }

        if ($rooms < 1 || $rooms > config('travel.public_search.max_rooms') || $adults < 1 || $adults > config('travel.public_search.max_adults_per_room') || $children < 0 || $children > config('travel.public_search.max_children_per_room')) {
            throw ValidationException::withMessages(['rooms' => __('public.search.validation.occupancy')]);
        }

        if (count($childAges) !== $children) {
            throw ValidationException::withMessages(['child_ages' => __('public.search.validation.child_ages')]);
        }

        foreach ($childAges as $age) {
            if (! is_numeric($age) || (int) $age < 0 || (int) $age > config('travel.public_search.max_child_age')) {
                throw ValidationException::withMessages(['child_ages' => __('public.search.validation.child_age_range')]);
            }
        }

        return [
            'destination' => (string) ($criteria['destination'] ?? ''),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'rooms' => $rooms,
            'adults' => $adults,
            'children' => $children,
            'child_ages' => array_map('intval', $childAges),
            'nationality' => $criteria['nationality'] ?? null,
            'residency_country' => $criteria['residency_country'] ?? null,
            'currency' => $currency,
            'locale' => $locale,
        ];
    }

    private function rooms(array $criteria): array
    {
        $rooms = [];

        for ($i = 0; $i < $criteria['rooms']; $i++) {
            $rooms[] = new RoomOccupancyData($criteria['adults'], $criteria['children'], $criteria['child_ages']);
        }

        return $rooms;
    }

    private function eligibleSuppliers(): Collection
    {
        $allowed = config('travel.public_search.suppliers', []);

        return $this->suppliers->enabledFor(SupplierOperation::Search)
            ->whereIn('code', $allowed)
            ->sortBy(fn (Supplier $supplier): int => array_search($supplier->code, $allowed, true))
            ->values();
    }

    private function normalizeHotels(array $hotels, string $locale, string $supplierCode): array
    {
        return collect($hotels)->map(function (SupplierHotelData $hotel) use ($locale, $supplierCode): array {
            $localHbx = $supplierCode === 'hbx_hotels'
                ? HbxHotel::query()->with(['images', 'facilities'])->where('supplier_code', 'hbx_hotels')->where('hotel_code', $hotel->supplierHotelId)->first()
                : null;
            $rates = collect($hotel->rooms)->map(function ($rate) use ($locale): array {
                $sellingTotal = $this->pricing->sellingPrice($rate->totalAmount);

                return [
                    'public_rate_token' => Str::lower(Str::random(18)),
                    'supplier_room_id' => $rate->supplierRoomId,
                    'supplier_rate_key' => $rate->rateKey,
                    'room_name' => $rate->roomName,
                    'board_basis' => $rate->boardBasis->value,
                    'supplier_total' => $rate->totalAmount->jsonSerialize(),
                    'net' => $rate->netAmount?->jsonSerialize(),
                    'total' => $sellingTotal->jsonSerialize(),
                    'tax' => $rate->taxAmount?->jsonSerialize(),
                    'fee' => $rate->feeAmount?->jsonSerialize(),
                    'refundability' => $rate->refundability->value,
                    'cancellation_summary' => $this->cancellations->summarize($rate->cancellationPolicies, $locale),
                    'occupancy' => $rate->occupancy->jsonSerialize(),
                    'requires_check_rate' => (bool) ($rate->metadata['requires_check_rate'] ?? false),
                    'rate_type' => $rate->metadata['rate_type'] ?? ((bool) ($rate->metadata['requires_check_rate'] ?? false) ? 'RECHECK' : 'BOOKABLE'),
                    'payment_type' => $rate->paymentType,
                    'rate_expires_at' => $rate->rateExpiry?->toIso8601String(),
                    'availability_timestamp' => now()->toIso8601String(),
                ];
            })->all();

            $minimumRate = collect($rates)->sortBy('total.minor_amount')->first();

            return [
                'public_token' => Str::lower(Str::random(16)),
                'supplier_hotel_id' => $hotel->supplierHotelId,
                'supplier_code' => $supplierCode,
                'canonical_hotel_id' => $hotel->canonicalHotelId,
                'name' => $localHbx ? $this->localizedHbxHotelName($localHbx, $locale) : $hotel->name,
                'star_rating' => $hotel->starRating,
                'location' => $localHbx?->address ?: $hotel->location,
                'coordinates' => $hotel->coordinates,
                'facilities' => $localHbx ? $localHbx->facilities->take(6)->pluck('description')->filter()->values()->all() : $hotel->facilities,
                'primary_image' => $localHbx?->images->firstWhere('is_primary', true)?->path ?? $localHbx?->images->first()?->path,
                'rates' => $rates,
                'minimum_price' => $minimumRate['total'] ?? $hotel->minimumTotalPrice?->jsonSerialize(),
                'minimum_price_minor' => $minimumRate['total']['minor_amount'] ?? $hotel->minimumTotalPrice?->minorAmount ?? 0,
                'currency' => $hotel->currency,
                'taxes_known' => $hotel->taxesAndFees !== [],
            ];
        })->all();
    }

    private function localizedHbxHotelName(HbxHotel $hotel, string $locale): string
    {
        return $locale === 'ar'
            ? ($hotel->name_ar ?: $hotel->hotel_name)
            : ($hotel->name_en ?: $hotel->hotel_name);
    }

    private function customerWarning(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof SupplierTimeoutException => __('public.search.errors.timeout'),
            $exception instanceof SupplierAuthenticationException => __('public.search.errors.unavailable'),
            $exception instanceof SupplierRateLimitException => __('public.search.errors.rate_limited'),
            $exception instanceof UnavailableSupplierException => __('public.search.errors.unavailable'),
            default => __('public.search.errors.generic'),
        };
    }

    private function logFailure(Supplier $supplier, string $correlationId, array $criteria, Throwable $exception): void
    {
        $this->logger->log($supplier, SupplierOperation::Search, [
            'correlation_id' => $correlationId,
            'request_payload' => $criteria,
            'response_payload' => ['message' => 'Search failed before normalized response.'],
            'successful' => false,
            'error_type' => $exception instanceof SupplierException ? SupplierErrorType::InvalidResponse : SupplierErrorType::Unavailable,
            'error_message' => class_basename($exception).': '.$exception->getMessage(),
        ]);
    }
}
