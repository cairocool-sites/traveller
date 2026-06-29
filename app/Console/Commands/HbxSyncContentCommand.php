<?php

namespace App\Console\Commands;

use App\Enums\SupplierStatus;
use App\Models\HbxDestination;
use App\Models\Supplier;
use App\Services\Supplier\Hbx\HbxContentApiClient;
use App\Services\Supplier\Hbx\HbxContentSyncService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class HbxSyncContentCommand extends Command
{
    protected $signature = 'hbx:sync-content
        {--countries : Validate the official countries endpoint without storing country records}
        {--destinations : Sync HBX destinations}
        {--hotels : Sync HBX hotels for one destination}
        {--destination= : HBX destination code, for example CAI}
        {--country=EG : Country code filter for destination sync}
        {--page-limit=1 : Maximum content pages to request}
        {--dry-run : Fetch and validate without writing records}
        {--suggest-mappings : Suggest exact local city to HBX destination mappings}';

    protected $description = 'Safely sync bounded HBX Content API reference data without booking or cancellation calls.';

    public function handle(HbxContentSyncService $sync): int
    {
        try {
            $supplier = $this->supplier();
            $options = [
                'dry_run' => (bool) $this->option('dry-run'),
                'page_limit' => (int) $this->option('page-limit'),
                'country_code' => strtoupper((string) $this->option('country')),
            ];

            if ($this->option('countries')) {
                app(HbxContentApiClient::class)->countries($supplier, ['fields' => 'all', 'language' => 'ENG', 'from' => 1, 'to' => 100]);
                $this->info('Countries endpoint validated. No local country records were changed.');
            }

            if ($this->option('destinations')) {
                $result = $sync->syncDestinations($supplier, $options);
                $this->info("Destinations processed: {$result['processed']}; stored: {$result['stored']}.");
            }

            if ($this->option('hotels')) {
                $destinationCode = strtoupper((string) $this->option('destination'));

                if ($destinationCode === '') {
                    throw new RuntimeException('Use --destination={HBX_CODE} when syncing hotels.');
                }

                if (! HbxDestination::query()->where('supplier_code', $supplier->code)->where('destination_code', $destinationCode)->where('is_active', true)->exists() && ! $this->option('dry-run')) {
                    throw new RuntimeException('The HBX destination must be synced before storing hotels.');
                }

                $result = $sync->syncHotels($supplier, $destinationCode, $options);
                $this->info("Hotels processed for {$destinationCode}: {$result['processed']}; stored: {$result['stored']}.");
            }

            if ($this->option('suggest-mappings')) {
                $count = $sync->suggestDestinationMappings($supplier->code);
                $this->info("Suggested destination mappings: {$count}.");
            }

            if (! $this->option('countries') && ! $this->option('destinations') && ! $this->option('hotels') && ! $this->option('suggest-mappings')) {
                throw new RuntimeException('Choose at least one sync option. No full-world sync is allowed.');
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('HBX content sync failed safely. Check sanitized supplier logs for details.');

            return self::FAILURE;
        }
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
}
