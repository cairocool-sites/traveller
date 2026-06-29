<?php

namespace App\Console\Commands;

use App\Models\HbxContentResource;
use App\Models\HbxContentSyncBatch;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use Illuminate\Console\Command;

class HbxContentStatusCommand extends Command
{
    protected $signature = 'hbx:content:status';

    protected $description = 'Display sanitized local HBX Content API catalogue status.';

    public function handle(): int
    {
        $this->line('HBX Content API local catalogue status');
        $this->line('Destinations: '.HbxDestination::query()->count());
        $this->line('Public destinations: '.HbxDestination::query()->where('supplier_active', true)->where('public_enabled', true)->count());
        $this->line('Hotels: '.HbxHotel::query()->count());
        $this->line('Public hotels: '.HbxHotel::query()->where('supplier_active', true)->where('public_enabled', true)->count());
        $this->line('Generic resources: '.HbxContentResource::query()->count());

        $latest = HbxContentSyncBatch::query()->latest()->first();

        if ($latest) {
            $this->line('Latest batch: #'.$latest->id.' '.$latest->resource.' '.$latest->status.' processed='.$latest->processed_count.' stored='.$latest->stored_count);
        } else {
            $this->line('Latest batch: none');
        }

        $this->line('No supplier request was sent by this command.');

        return self::SUCCESS;
    }
}
