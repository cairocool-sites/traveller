<?php

namespace App\Services\Supplier\Hbx;

use App\Models\City;
use App\Models\HbxContentResource;
use App\Models\HbxDestination;
use App\Models\HbxDestinationZone;
use App\Models\HbxHotel;
use App\Models\HbxHotelFacility;
use App\Models\HbxHotelImage;
use App\Models\HbxHotelRoom;
use App\Models\HbxHotelTranslation;
use App\Models\Supplier;
use App\Models\SupplierDestinationMapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HbxContentSyncService
{
    public const SUPPLIER_CODE = 'hbx_hotels';

    private const PAGE_SIZE = 100;

    public function __construct(private readonly HbxContentApiClient $client) {}

    public function syncDestinations(Supplier $supplier, array $options = []): array
    {
        $ranges = $this->ranges($options);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $countryCode = $options['country_code'] ?? null;
        $language = (string) ($options['language'] ?? 'ENG');
        $seen = [];
        $total = 0;

        foreach ($ranges as $range) {
            $response = $this->client->destinations($supplier, [
                'fields' => 'all',
                'language' => $language,
                'countryCodes' => $countryCode,
                'lastUpdateTime' => $options['last_update_time'] ?? null,
                'from' => $range['from'],
                'to' => $range['to'],
            ]);

            $items = $this->items($response['body'], 'destinations');
            $total += count($items);

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $code = (string) ($item['code'] ?? '');

                if ($code === '') {
                    continue;
                }

                $seen[] = $code;

                if (! $dryRun) {
                    $name = $this->localizedName($item, 'name') ?: $code;
                    HbxDestination::query()->updateOrCreate(
                        ['supplier_code' => $supplier->code, 'destination_code' => $code, 'content_language' => $language],
                        [
                            'destination_name' => $name,
                            'country_code' => $item['countryCode'] ?? $item['country'] ?? null,
                            'parent_destination_code' => $item['parentDestinationCode'] ?? null,
                            'destination_type' => $item['type'] ?? $item['destinationType'] ?? null,
                            'latitude' => $item['coordinates']['latitude'] ?? $item['latitude'] ?? null,
                            'longitude' => $item['coordinates']['longitude'] ?? $item['longitude'] ?? null,
                            'supplier_active' => true,
                            'public_enabled' => ($item['countryCode'] ?? $item['country'] ?? null) === config('travel.hbx.public_country', 'EG'),
                            'name_en' => $language === 'ENG' ? $name : null,
                            'name_ar' => $language === 'ARA' ? $name : null,
                            'slug' => $this->uniqueSlug(HbxDestination::class, $supplier->code, $name, $code),
                            'seo_title' => $name,
                            'display_order' => 100,
                            'last_supplier_update_at' => $item['lastUpdateTime'] ?? null,
                            'last_synced_at' => now(),
                            'payload_checksum' => hash('sha256', json_encode($item, JSON_THROW_ON_ERROR)),
                            'is_active' => true,
                            'synced_at' => now(),
                        ],
                    );

                    $this->syncZones($supplier->code, $code, $item, $language);
                }
            }
        }

        if (! $dryRun && $seen !== [] && (bool) ($options['deactivate_missing'] ?? false)) {
            HbxDestination::query()
                ->where('supplier_code', $supplier->code)
                ->when($countryCode, fn ($query) => $query->where('country_code', $countryCode))
                ->whereNotIn('destination_code', array_unique($seen))
                ->update(['is_active' => false]);
        }

        return ['processed' => $total, 'stored' => $dryRun ? 0 : count(array_unique($seen))];
    }

    public function syncHotels(Supplier $supplier, string $destinationCode, array $options = []): array
    {
        $ranges = $this->ranges($options);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $countryCode = $options['country_code'] ?? null;
        $hotelCodes = $this->hotelCodes($options['hotel_codes'] ?? []);
        $language = (string) ($options['language'] ?? 'ENG');
        if (! $countryCode && $destinationCode !== '') {
            $countryCode = HbxDestination::query()
                ->where('supplier_code', $supplier->code)
                ->where('destination_code', $destinationCode)
                ->value('country_code');
        }
        $seen = [];
        $total = 0;

        foreach ($ranges as $range) {
            $response = $this->client->hotels($supplier, [
                'fields' => 'all',
                'language' => $language,
                'useSecondaryLanguage' => 'false',
                'countryCode' => $countryCode,
                'destinationCode' => $destinationCode !== '' ? $destinationCode : null,
                'codes' => $hotelCodes !== [] ? implode(',', $hotelCodes) : null,
                'lastUpdateTime' => $options['last_update_time'] ?? null,
                'from' => $range['from'],
                'to' => $range['to'],
            ]);

            $items = $this->items($response['body'], 'hotels');
            $total += count($items);

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $code = (string) ($item['code'] ?? '');

                if ($code === '') {
                    continue;
                }

                $seen[] = $code;

                if (! $dryRun) {
                    $this->storeHotel($supplier, $item, $language, $destinationCode, $countryCode);
                }
            }
        }

        if (! $dryRun && $seen !== [] && (bool) ($options['deactivate_missing'] ?? false)) {
            HbxHotel::query()
                ->where('supplier_code', $supplier->code)
                ->when($hotelCodes !== [], fn ($query) => $query->whereIn('hotel_code', $hotelCodes))
                ->when($destinationCode !== '', fn ($query) => $query->where('destination_code', $destinationCode))
                ->when($countryCode && $destinationCode === '', fn ($query) => $query->whereIn('destination_code', HbxDestination::query()
                    ->select('destination_code')
                    ->where('supplier_code', $supplier->code)
                    ->where('country_code', $countryCode)))
                ->whereNotIn('hotel_code', array_unique($seen))
                ->update(['is_active' => false]);
        }

        return ['processed' => $total, 'stored' => $dryRun ? 0 : count(array_unique($seen))];
    }

    public function syncHotelDetailsByCodes(Supplier $supplier, mixed $hotelCodes, array $options = []): array
    {
        $codes = $this->hotelCodes($hotelCodes);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $language = (string) ($options['language'] ?? 'ENG');

        if ($codes === []) {
            return ['processed' => 0, 'stored' => 0];
        }

        $response = $this->client->hotelDetails($supplier, implode(',', $codes), [
            'language' => $language,
            'useSecondaryLanguage' => 'false',
        ]);

        $items = $this->detailItems($response['body']);
        $seen = [];

        foreach ($items as $item) {
            $code = (string) ($item['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $seen[] = $code;

            if (! $dryRun) {
                $this->storeHotel($supplier, $item, $language);
            }
        }

        return ['processed' => count($items), 'stored' => $dryRun ? 0 : count(array_unique($seen))];
    }

    public function syncGenericResource(Supplier $supplier, string $resource, array $options = []): array
    {
        $resource = HbxContentApiClient::RESOURCE_ALIASES[$resource] ?? $resource;
        $ranges = $this->ranges($options);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $language = (string) ($options['language'] ?? 'ENG');
        $lastUpdateTime = $options['last_update_time'] ?? null;
        $seen = [];
        $total = 0;

        foreach ($ranges as $range) {
            $response = $this->client->resource($supplier, $resource, [
                'fields' => 'all',
                'language' => $language,
                'lastUpdateTime' => $lastUpdateTime,
                'from' => $range['from'],
                'to' => $range['to'],
            ]);

            $items = $this->items($response['body'], $resource);
            $total += count($items);

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $code = $this->resourceCode($item);

                if ($code === '') {
                    continue;
                }

                $seen[] = $code;

                if (! $dryRun) {
                    HbxContentResource::query()->updateOrCreate(
                        [
                            'supplier_code' => $supplier->code,
                            'resource_type' => $resource,
                            'resource_code' => $code,
                            'language' => $language,
                        ],
                        [
                            'name' => $this->localizedName($item, 'description') ?: $this->localizedName($item, 'name') ?: $code,
                            'country_code' => $item['countryCode'] ?? $item['country'] ?? null,
                            'destination_code' => $item['destinationCode'] ?? null,
                            'parent_code' => $item['parentCode'] ?? $item['groupCode'] ?? $item['categoryGroupCode'] ?? null,
                            'payload' => $item,
                            'payload_hash' => hash('sha256', json_encode($item, JSON_THROW_ON_ERROR)),
                            'last_update_time' => $item['lastUpdateTime'] ?? null,
                            'is_active' => true,
                            'synced_at' => now(),
                        ],
                    );
                }
            }
        }

        if (! $dryRun && $seen !== [] && (bool) ($options['deactivate_missing'] ?? false)) {
            HbxContentResource::query()
                ->where('supplier_code', $supplier->code)
                ->where('resource_type', $resource)
                ->where('language', $language)
                ->whereNotIn('resource_code', array_unique($seen))
                ->update(['is_active' => false]);
        }

        return ['processed' => $total, 'stored' => $dryRun ? 0 : count(array_unique($seen))];
    }

    public function suggestDestinationMappings(string $supplierCode = self::SUPPLIER_CODE): int
    {
        $count = 0;

        City::query()->where('is_active', true)->chunkById(100, function (Collection $cities) use (&$count, $supplierCode): void {
            foreach ($cities as $city) {
                $destination = HbxDestination::query()
                    ->where('supplier_code', $supplierCode)
                    ->where('is_active', true)
                    ->whereRaw('lower(destination_name) = ?', [Str::lower($city->name_en)])
                    ->first();

                if (! $destination) {
                    continue;
                }

                SupplierDestinationMapping::query()->updateOrCreate(
                    [
                        'local_entity_type' => 'city',
                        'local_entity_id' => $city->id,
                        'supplier_code' => $supplierCode,
                        'supplier_destination_code' => $destination->destination_code,
                    ],
                    [
                        'status' => 'suggested',
                        'confidence' => 90,
                        'manually_confirmed' => false,
                        'is_active' => true,
                    ],
                );

                $count++;
            }
        });

        return $count;
    }

    private function items(array $body, string $key): array
    {
        $keys = array_unique([
            $key,
            ...$this->resourceEnvelopeKeys($key),
            Str::camel($key),
            str_replace('_', '', $key),
        ]);
        $items = [];

        foreach ($keys as $candidate) {
            $items = Arr::get($body, $candidate.'.'.$candidate, Arr::get($body, $candidate, []));

            if (is_array($items) && $items !== []) {
                break;
            }
        }

        return is_array($items) ? array_values($items) : [];
    }

    private function resourceEnvelopeKeys(string $key): array
    {
        return [
            'boardgroups' => ['boardGroups'],
            'facilitygroups' => ['facilityGroups'],
            'facilitytypologies' => ['facilityTypologies'],
            'groupcategories' => ['groupCategories'],
            'imagetypes' => ['imageTypes'],
            'rate_comments' => ['rateComments'],
            'ratecomments' => ['rateComments'],
        ][$key] ?? [];
    }

    private function detailItems(array $body): array
    {
        if (isset($body['hotels']) && is_array($body['hotels'])) {
            return array_values($body['hotels']);
        }

        if (isset($body['hotel']) && is_array($body['hotel'])) {
            return [$body['hotel']];
        }

        return $this->items($body, 'hotels');
    }

    private function ranges(array $options): array
    {
        $from = isset($options['from']) ? max(1, (int) $options['from']) : 1;
        $limit = isset($options['limit']) ? max(1, min((int) $options['limit'], self::PAGE_SIZE * 25)) : null;
        $to = isset($options['to']) ? max($from, (int) $options['to']) : null;

        if ($limit !== null) {
            $to = $from + $limit - 1;
        }

        if ($to !== null) {
            return [['from' => $from, 'to' => $to]];
        }

        $pageLimit = max(1, min((int) ($options['page_limit'] ?? 1), 25));
        $ranges = [];

        for ($page = 1; $page <= $pageLimit; $page++) {
            $ranges[] = [
                'from' => (($page - 1) * self::PAGE_SIZE) + 1,
                'to' => $page * self::PAGE_SIZE,
            ];
        }

        return $ranges;
    }

    private function hotelCodes(mixed $codes): array
    {
        if (is_string($codes)) {
            $codes = explode(',', $codes);
        }

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $code): string => (string) preg_replace('/\D+/', '', (string) $code),
            $codes,
        ))));
    }

    private function localizedName(array $item, string $key): ?string
    {
        $value = $item[$key] ?? null;

        if (is_array($value)) {
            return $value['content'] ?? $value['name'] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    private function resourceCode(array $item): string
    {
        foreach (['code', 'id', 'isoCode', 'languageCode', 'currencyCode'] as $key) {
            if (filled($item[$key] ?? null)) {
                return (string) $item[$key];
            }
        }

        return '';
    }

    private function stars(?string $categoryCode): ?int
    {
        if (! $categoryCode || ! preg_match('/([1-5])/', $categoryCode, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function syncZones(string $supplierCode, string $destinationCode, array $item, string $language): void
    {
        foreach (array_values($item['zones'] ?? []) as $zone) {
            $zoneCode = (string) ($zone['zoneCode'] ?? $zone['code'] ?? '');

            if ($zoneCode === '') {
                continue;
            }

            HbxDestinationZone::query()->updateOrCreate(
                ['supplier_code' => $supplierCode, 'destination_code' => $destinationCode, 'zone_code' => $zoneCode, 'content_language' => $language],
                [
                    'zone_name' => $this->localizedName($zone, 'name') ?: ($zone['description']['content'] ?? $zoneCode),
                    'payload' => $zone,
                    'is_active' => true,
                    'synced_at' => now(),
                ],
            );
        }
    }

    private function syncHotelDetails(HbxHotel $hotel, array $item, string $language): void
    {
        HbxHotelTranslation::query()->updateOrCreate(
            ['hbx_hotel_id' => $hotel->id, 'language' => $language],
            [
                'name' => $this->localizedName($item, 'name') ?: $hotel->hotel_name,
                'description' => $this->localizedName($item, 'description'),
                'address' => $this->localizedName($item, 'address') ?: ($item['address']['content'] ?? null),
                'seo_title' => $hotel->seo_title,
                'seo_description' => $hotel->seo_description,
            ],
        );

        foreach (array_values($item['images'] ?? []) as $index => $image) {
            $path = HbxHotelImage::normalizePath((string) ($image['path'] ?? ''));

            if ($path === '') {
                continue;
            }

            HbxHotelImage::query()->updateOrCreate(
                ['hbx_hotel_id' => $hotel->id, 'path' => $path],
                [
                    'image_type_code' => $image['imageTypeCode'] ?? $image['type'] ?? null,
                    'room_code' => $image['roomCode'] ?? null,
                    'sort_order' => $this->imageSortOrder($image, $index),
                    'width' => $image['width'] ?? null,
                    'height' => $image['height'] ?? null,
                    'alt_text' => $hotel->hotel_name,
                    'is_primary' => (int) ($image['visualOrder'] ?? $index) === 0,
                    'is_active' => true,
                    'payload' => $image,
                ],
            );
        }

        foreach (array_values($item['facilities'] ?? []) as $facility) {
            $code = (string) ($facility['facilityCode'] ?? $facility['code'] ?? '');

            if ($code === '') {
                continue;
            }

            HbxHotelFacility::query()->updateOrCreate(
                ['hbx_hotel_id' => $hotel->id, 'facility_code' => $code],
                [
                    'facility_group_code' => isset($facility['facilityGroupCode']) ? (string) $facility['facilityGroupCode'] : null,
                    'description' => $this->localizedName($facility, 'description'),
                    'is_active' => true,
                    'payload' => $facility,
                ],
            );
        }

        foreach (array_values($item['rooms'] ?? []) as $room) {
            $code = (string) ($room['roomCode'] ?? $room['code'] ?? '');

            if ($code === '') {
                continue;
            }

            HbxHotelRoom::query()->updateOrCreate(
                ['hbx_hotel_id' => $hotel->id, 'room_code' => $code, 'characteristic_code' => $room['characteristicCode'] ?? null],
                [
                    'room_name' => $this->localizedName($room, 'description') ?: $this->localizedName($room, 'name'),
                    'min_adults' => $room['minAdults'] ?? null,
                    'max_adults' => $room['maxAdults'] ?? null,
                    'max_children' => $room['maxChildren'] ?? null,
                    'max_pax' => $room['maxPax'] ?? null,
                    'is_active' => true,
                    'payload' => $room,
                ],
            );
        }
    }

    private function imageSortOrder(array $image, int $index): int
    {
        $visualOrder = max(0, (int) ($image['visualOrder'] ?? $index));
        $order = max(0, (int) ($image['order'] ?? 0));

        return ($visualOrder * 1000) + $order + 1;
    }

    private function storeHotel(Supplier $supplier, array $item, string $language, string $destinationCode = '', ?string $countryCode = null): HbxHotel
    {
        $code = (string) ($item['code'] ?? '');
        $name = $this->localizedName($item, 'name') ?: $code;
        $resolvedDestinationCode = (string) ($item['destinationCode'] ?? $item['destination']['code'] ?? $destinationCode);
        $resolvedCountryCode = $item['countryCode'] ?? $item['country']['code'] ?? $item['country']['isoCode'] ?? $countryCode;
        $categoryCode = $item['categoryCode'] ?? $item['category']['code'] ?? null;

        $hotel = HbxHotel::query()->updateOrCreate(
            ['supplier_code' => $supplier->code, 'hotel_code' => $code],
            [
                'destination_code' => $resolvedDestinationCode,
                'country_code' => $resolvedCountryCode,
                'zone_code' => isset($item['zoneCode']) ? (string) $item['zoneCode'] : (isset($item['zone']['code']) ? (string) $item['zone']['code'] : null),
                'hotel_name' => $name,
                'category_code' => $categoryCode,
                'star_rating' => $this->stars($categoryCode),
                'latitude' => $item['coordinates']['latitude'] ?? $item['latitude'] ?? null,
                'longitude' => $item['coordinates']['longitude'] ?? $item['longitude'] ?? null,
                'address' => $this->localizedName($item, 'address') ?: ($item['address']['content'] ?? null),
                'postal_code' => $item['postalCode'] ?? null,
                'accommodation_type_code' => $item['accommodationTypeCode'] ?? $item['accommodationType']['code'] ?? null,
                'chain_code' => $item['chainCode'] ?? $item['chain']['code'] ?? null,
                'primary_phone' => $item['phones'][0]['phoneNumber'] ?? null,
                'primary_email' => $item['email'] ?? null,
                'supplier_active' => true,
                'public_enabled' => $resolvedCountryCode === config('travel.hbx.public_country', 'EG'),
                'name_en' => $language === 'ENG' ? $name : null,
                'name_ar' => $language === 'ARA' ? $name : null,
                'slug' => $this->uniqueSlug(HbxHotel::class, $supplier->code, $name, $code),
                'seo_title' => $name,
                'seo_description' => $this->localizedName($item, 'description'),
                'display_order' => 100,
                'last_supplier_update_at' => $item['lastUpdateTime'] ?? $item['lastUpdate'] ?? null,
                'last_synced_at' => now(),
                'payload_checksum' => hash('sha256', json_encode($item, JSON_THROW_ON_ERROR)),
                'is_active' => true,
                'synced_at' => now(),
            ],
        );

        $this->syncHotelDetails($hotel, $item, $language);

        return $hotel;
    }

    private function uniqueSlug(string $model, string $supplierCode, string $name, string $fallback): string
    {
        unset($model, $supplierCode);

        $base = Str::slug($name) ?: Str::slug($fallback);
        $code = Str::slug($fallback);

        return $code && ! str_ends_with($base, $code) ? "{$base}-{$code}" : $base;
    }
}
