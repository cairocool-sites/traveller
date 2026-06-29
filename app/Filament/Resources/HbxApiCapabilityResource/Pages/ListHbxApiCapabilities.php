<?php

namespace App\Filament\Resources\HbxApiCapabilityResource\Pages;

use App\Filament\Resources\HbxApiCapabilityResource;
use App\Services\Supplier\Hbx\HbxApiCapabilityRegistry;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListHbxApiCapabilities extends ListRecords
{
    protected static string $resource = HbxApiCapabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('admin.hbx_capabilities.actions.refresh'))
                ->action(fn () => app(HbxApiCapabilityRegistry::class)->sync()),
        ];
    }
}
