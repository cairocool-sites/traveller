<?php

namespace App\Services\Supplier\Hbx;

use App\Models\City;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
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
        $pageLimit = max(1, min((int) ($options['page_limit'] ?? 1), 25));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $countryCode = $options['country_code'] ?? null;
        $seen = [];
        $total = 0;

        for ($page = 1; $page <= $pageLimit; $page++) {
            $response = $this->client->destinations($supplier, [
                'fields' => 'all',
                'language' => 'ENG',
                'countryCodes' => $countryCode,
                'from' => (($page - 1) * self::PAGE_SIZE) + 1,
                'to' => $page * self::PAGE_SIZE,
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
                    HbxDestination::query()->updateOrCreate(
                        ['supplier_code' => $supplier->code, 'destination_code' => $code],
                        [
                            'destination_name' => $this->localizedName($item, 'name') ?: $code,
                            'country_code' => $item['countryCode'] ?? $item['country'] ?? null,
                            'parent_destination_code' => $item['parentDestinationCode'] ?? null,
                            'is_active' => true,
                            'synced_at' => now(),
                        ],
                    );
                }
            }
        }

        if (! $dryRun && $seen !== []) {
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
        $pageLimit = max(1, min((int) ($options['page_limit'] ?? 1), 25));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $countryCode = HbxDestination::query()
            ->where('supplier_code', $supplier->code)
            ->where('destination_code', $destinationCode)
            ->value('country_code');
        $seen = [];
        $total = 0;

        for ($page = 1; $page <= $pageLimit; $page++) {
            $response = $this->client->hotels($supplier, [
                'fields' => 'all',
                'language' => 'ENG',
                'useSecondaryLanguage' => 'false',
                'countryCode' => $countryCode,
                'destinationCode' => $destinationCode,
                'from' => (($page - 1) * self::PAGE_SIZE) + 1,
                'to' => $page * self::PAGE_SIZE,
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
                    HbxHotel::query()->updateOrCreate(
                        ['supplier_code' => $supplier->code, 'hotel_code' => $code],
                        [
                            'destination_code' => (string) ($item['destinationCode'] ?? $destinationCode),
                            'hotel_name' => $this->localizedName($item, 'name') ?: $code,
                            'category_code' => $item['categoryCode'] ?? null,
                            'star_rating' => $this->stars($item['categoryCode'] ?? null),
                            'latitude' => $item['coordinates']['latitude'] ?? $item['latitude'] ?? null,
                            'longitude' => $item['coordinates']['longitude'] ?? $item['longitude'] ?? null,
                            'address' => $this->localizedName($item, 'address') ?: ($item['address']['content'] ?? null),
                            'is_active' => true,
                            'synced_at' => now(),
                        ],
                    );
                }
            }
        }

        if (! $dryRun && $seen !== []) {
            HbxHotel::query()
                ->where('supplier_code', $supplier->code)
                ->where('destination_code', $destinationCode)
                ->whereNotIn('hotel_code', array_unique($seen))
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
        $items = Arr::get($body, $key.'.'.$key, Arr::get($body, $key, []));

        return is_array($items) ? array_values($items) : [];
    }

    private function localizedName(array $item, string $key): ?string
    {
        $value = $item[$key] ?? null;

        if (is_array($value)) {
            return $value['content'] ?? $value['name'] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    private function stars(?string $categoryCode): ?int
    {
        if (! $categoryCode || ! preg_match('/([1-5])/', $categoryCode, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
