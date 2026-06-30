<?php

namespace App\Console\Commands;

use App\Models\HbxDestination;
use App\Models\HbxHotel;
use Illuminate\Console\Command;

class HbxEnablePublicCatalogueCommand extends Command
{
    protected $signature = 'hbx:content:enable-public {--country=EG : ISO2 country to enable} {--dry-run : Preview counts without changing records}';

    protected $description = 'Enable a bounded country subset of synchronized HBX catalogue records for public local pages and autocomplete.';

    public function handle(): int
    {
        $country = strtoupper((string) $this->option('country'));
        $destinations = HbxDestination::query()
            ->where('country_code', $country)
            ->where('supplier_active', true);
        $hotels = HbxHotel::query()
            ->where('country_code', $country)
            ->where('supplier_active', true);

        $this->line("Country: {$country}");
        $this->line('Destinations eligible: '.$destinations->count());
        $this->line('Hotels eligible: '.$hotels->count());

        if ($this->option('dry-run')) {
            $this->line('Dry run complete. No public visibility was changed.');

            return self::SUCCESS;
        }

        $destinations->update(['public_enabled' => true]);
        $hotels->update(['public_enabled' => true]);

        $this->line('Public visibility enabled for eligible local HBX records only.');
        $this->line('No supplier request was sent by this command.');

        return self::SUCCESS;
    }
}
