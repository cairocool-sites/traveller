<?php

namespace App\Console\Commands;

use App\Enums\SupplierStatus;
use App\Jobs\HbxContentSyncJob;
use App\Models\HbxContentSyncBatch;
use App\Models\Supplier;
use App\Services\Supplier\Hbx\HbxContentApiClient;
use App\Services\Supplier\Hbx\HbxContentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HbxContentSyncCommand extends Command
{
    protected $signature = 'hbx:content:sync
        {--resource= : Content resource: countries, destinations, hotels, all, or a supported master resource}
        {--country= : Country code filter such as EG}
        {--destination= : HBX destination code}
        {--hotel-codes= : Comma-separated official HBX hotel codes from Availability}
        {--details : Use the official /hotels/{hotelCodes}/details fallback for --hotel-codes}
        {--language=ENG : HBX language code}
        {--limit= : Maximum records to request using official from/to range}
        {--from= : Official HBX Content API from offset}
        {--to= : Official HBX Content API to offset}
        {--page-limit=1 : Maximum pages to request}
        {--last-update-time= : Differential sync timestamp/date where supported}
        {--deactivate-missing : Deactivate records missing from a full explicit sync window}
        {--full-authorized-portfolio : Allow a portfolio-wide sync mode}
        {--confirm : Required with --full-authorized-portfolio}
        {--queue : Dispatch the sync to the configured queue}
        {--batch-id= : Internal batch id used by queued sync jobs}
        {--dry-run : Fetch and validate without writing records}';

    protected $description = 'Safely sync HBX Content API resources with bounded pagination and no booking calls.';

    public function handle(HbxContentSyncService $sync): int
    {
        $batch = null;

        try {
            $supplier = $this->supplier();
            $resource = strtolower((string) $this->option('resource'));

            if ($resource === '') {
                throw new RuntimeException('Use --resource={countries|destinations|hotels|all|master-resource}.');
            }

            if ($this->option('full-authorized-portfolio') && ! $this->option('confirm')) {
                throw new RuntimeException('Full authorized portfolio sync requires --confirm.');
            }

            if (! $this->option('full-authorized-portfolio') && ! $this->option('country') && ! $this->option('destination') && ! $this->option('hotel-codes') && in_array($resource, ['all', 'destinations', 'hotels'], true)) {
                throw new RuntimeException('Use --country={ISO2}, --destination={HBX_CODE}, or --hotel-codes={codes} for bounded sync, or explicitly use --full-authorized-portfolio --confirm.');
            }

            if ($this->option('details') && ! $this->option('hotel-codes')) {
                throw new RuntimeException('Use --hotel-codes with --details.');
            }

            $options = [
                'dry_run' => (bool) $this->option('dry-run'),
                'page_limit' => (int) $this->option('page-limit'),
                'country_code' => $this->option('country') ? strtoupper((string) $this->option('country')) : null,
                'destination_code' => $this->option('destination') ? strtoupper((string) $this->option('destination')) : null,
                'hotel_codes' => $this->option('hotel-codes'),
                'details' => (bool) $this->option('details'),
                'language' => strtoupper((string) $this->option('language')),
                'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
                'from' => $this->option('from') ? (int) $this->option('from') : null,
                'to' => $this->option('to') ? (int) $this->option('to') : null,
                'last_update_time' => $this->option('last-update-time'),
                'deactivate_missing' => (bool) $this->option('deactivate-missing'),
            ];

            if ($this->option('queue')) {
                $batch = $this->createBatch($supplier, $resource, $options, 'pending', true);

                HbxContentSyncJob::dispatch($batch->id, [
                    'resource' => $resource,
                    'country' => $this->option('country'),
                    'destination' => $this->option('destination'),
                    'hotel_codes' => $this->option('hotel-codes'),
                    'details' => (bool) $this->option('details'),
                    'language' => $options['language'],
                    'limit' => $options['limit'],
                    'from' => $options['from'],
                    'to' => $options['to'],
                    'page_limit' => $options['page_limit'],
                    'last_update_time' => $options['last_update_time'],
                    'full_authorized_portfolio' => (bool) $this->option('full-authorized-portfolio'),
                    'confirm' => (bool) $this->option('confirm'),
                    'dry_run' => (bool) $this->option('dry-run'),
                ]);

                $this->info("Queued HBX content sync batch #{$batch->id}.");

                return self::SUCCESS;
            }

            $batch = $this->resolveBatch($supplier, $resource, $options);
            $batch->forceFill([
                'status' => 'running',
                'started_at' => $batch->started_at ?: now(),
                'error_message' => null,
            ])->save();

            foreach ($this->resources($resource) as $currentResource) {
                $result = $this->syncResource($sync, $supplier, $currentResource, $options);
                $this->recordResourceProgress($batch, $currentResource, $result);
                $this->info("{$currentResource}: processed {$result['processed']}; stored {$result['stored']}.");
            }

            $batch->forceFill([
                'status' => 'completed',
                'finished_at' => now(),
            ])->save();

            $this->line('No booking, modification, cancellation, or production request was sent by this command.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->failBatch($batch, $exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->failBatch($batch, 'HBX content sync failed safely. Check sanitized supplier logs for details.');
            $this->error('HBX content sync failed safely. Check sanitized supplier logs for details.');

            return self::FAILURE;
        }
    }

    private function syncResource(HbxContentSyncService $sync, Supplier $supplier, string $resource, array $options): array
    {
        return match ($resource) {
            'countries' => $this->syncCountries($supplier, $options),
            'destinations' => $sync->syncDestinations($supplier, $options),
            'hotels' => $options['details']
                ? $sync->syncHotelDetailsByCodes($supplier, $options['hotel_codes'], $options)
                : $sync->syncHotels($supplier, (string) ($options['destination_code'] ?: ''), $options),
            default => $sync->syncGenericResource($supplier, $resource, $options),
        };
    }

    private function syncCountries(Supplier $supplier, array $options): array
    {
        app(HbxContentApiClient::class)->countries($supplier, [
            'fields' => 'all',
            'language' => $options['language'],
            'from' => 1,
            'to' => max(1, min((int) $options['page_limit'], 25)) * 100,
        ]);

        return ['processed' => 0, 'stored' => 0];
    }

    private function resources(string $resource): array
    {
        if ($resource === 'all') {
            return array_merge(['countries', 'destinations', 'hotels'], array_keys(HbxContentApiClient::RESOURCE_PATHS));
        }

        if (in_array($resource, ['countries', 'destinations', 'hotels'], true) || isset(HbxContentApiClient::RESOURCE_PATHS[$resource])) {
            return [$resource];
        }

        throw new RuntimeException("Unsupported HBX Content API resource [{$resource}].");
    }

    private function supplier(): Supplier
    {
        $supplier = Supplier::query()->where('code', HbxContentSyncService::SUPPLIER_CODE)->first();

        if (! $supplier) {
            throw new RuntimeException('The hbx_hotels supplier is not configured.');
        }

        if ($supplier->status !== SupplierStatus::Active) {
            throw new RuntimeException('The hbx_hotels supplier must be active.');
        }

        $baseUrl = rtrim((string) ($supplier->base_url ?: config('services.hbx.base_url')), '/');

        if ($baseUrl !== 'https://api.test.hotelbeds.com') {
            throw new RuntimeException('HBX content sync is blocked because the configured endpoint is not https://api.test.hotelbeds.com.');
        }

        return $supplier;
    }

    private function resolveBatch(Supplier $supplier, string $resource, array $options): HbxContentSyncBatch
    {
        if ($this->option('batch-id')) {
            return HbxContentSyncBatch::query()->whereKey((int) $this->option('batch-id'))->firstOrFail();
        }

        return $this->createBatch($supplier, $resource, $options, 'running', false);
    }

    private function createBatch(Supplier $supplier, string $resource, array $options, string $status, bool $queued): HbxContentSyncBatch
    {
        return HbxContentSyncBatch::query()->create([
            'supplier_id' => $supplier->id,
            'resource' => $resource,
            'mode' => $this->option('full-authorized-portfolio') ? 'full_authorized_portfolio' : ($options['last_update_time'] ? 'differential' : 'bounded'),
            'status' => $status,
            'country_code' => $options['country_code'],
            'destination_code' => $options['destination_code'],
            'language' => $options['language'],
            'page_limit' => $options['page_limit'],
            'checkpoint' => array_filter([
                'requested_from' => $options['from'],
                'requested_to' => $options['to'],
                'requested_limit' => $options['limit'],
                'hotel_codes' => $options['hotel_codes'],
                'details' => $options['details'] ? true : null,
            ], fn ($value): bool => $value !== null),
            'last_update_time' => $options['last_update_time'],
            'dry_run' => $options['dry_run'],
            'full_authorized_portfolio' => (bool) $this->option('full-authorized-portfolio'),
            'queued' => $queued,
            'started_at' => $status === 'running' ? now() : null,
        ]);
    }

    private function recordResourceProgress(HbxContentSyncBatch $batch, string $resource, array $result): void
    {
        $checkpoint = $batch->checkpoint ?: [];
        $checkpoint[$resource] = [
            'processed' => (int) $result['processed'],
            'stored' => (int) $result['stored'],
            'completed_at' => now()->toIso8601String(),
        ];

        $batch->forceFill([
            'checkpoint' => $checkpoint,
            'processed_count' => $batch->processed_count + (int) $result['processed'],
            'stored_count' => $batch->stored_count + (int) $result['stored'],
        ])->save();
    }

    private function failBatch(?HbxContentSyncBatch $batch, string $message): void
    {
        if (! $batch) {
            return;
        }

        $batch->forceFill([
            'status' => 'failed',
            'error_message' => Str::limit($message, 1000),
            'finished_at' => now(),
        ])->save();
    }
}
