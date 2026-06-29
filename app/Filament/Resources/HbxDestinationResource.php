<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HbxDestinationResource\Pages\ListHbxDestinations;
use App\Models\HbxDestination;
use App\Models\Supplier;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class HbxDestinationResource extends Resource
{
    protected static ?string $model = HbxDestination::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 24;

    public static function getModelLabel(): string
    {
        return __('admin.hbx_destinations.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.hbx_destinations.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('destination_code')->label(__('admin.hbx_destinations.fields.destination_code'))->searchable()->sortable()->copyable(),
                TextColumn::make('destination_name')->label(__('admin.hbx_destinations.fields.destination_name'))->searchable()->sortable(),
                TextColumn::make('country_code')->label(__('admin.hbx_destinations.fields.country_code'))->searchable()->sortable(),
                TextColumn::make('parent_destination_code')->label(__('admin.hbx_destinations.fields.parent_destination_code'))->toggleable(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean()->sortable(),
                TextColumn::make('synced_at')->label(__('admin.hbx_destinations.fields.synced_at'))->dateTime()->sortable(),
            ])
            ->filters([TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active'))])
            ->defaultSort('destination_name');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Supplier::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListHbxDestinations::route('/')];
    }
}
