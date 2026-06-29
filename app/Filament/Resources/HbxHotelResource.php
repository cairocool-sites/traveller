<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HbxHotelResource\Pages\ListHbxHotels;
use App\Models\HbxHotel;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class HbxHotelResource extends Resource
{
    protected static ?string $model = HbxHotel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 25;

    public static function getModelLabel(): string
    {
        return __('admin.hbx_hotels.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.hbx_hotels.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hotel_code')->label(__('admin.hbx_hotels.fields.hotel_code'))->searchable()->sortable()->copyable(),
                TextColumn::make('hotel_name')->label(__('admin.hbx_hotels.fields.hotel_name'))->searchable()->sortable(),
                TextColumn::make('destination_code')->label(__('admin.hbx_hotels.fields.destination_code'))->searchable()->sortable(),
                TextColumn::make('category_code')->label(__('admin.hbx_hotels.fields.category_code'))->toggleable(),
                TextColumn::make('star_rating')->label(__('admin.hbx_hotels.fields.star_rating'))->sortable(),
                IconColumn::make('supplier_active')->label(__('admin.hbx_hotels.fields.supplier_active'))->boolean()->sortable(),
                IconColumn::make('public_enabled')->label(__('admin.hbx_hotels.fields.public_enabled'))->boolean()->sortable(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean()->sortable(),
                TextColumn::make('synced_at')->label(__('admin.hbx_hotels.fields.synced_at'))->dateTime()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
                TernaryFilter::make('public_enabled')->label(__('admin.hbx_hotels.fields.public_enabled')),
            ])
            ->recordActions([
                Action::make('enable_public')
                    ->label(__('admin.hbx_hotels.actions.enable_public'))
                    ->visible(fn (HbxHotel $record): bool => ! $record->public_enabled && (bool) auth()->user()?->can('manage_suppliers'))
                    ->requiresConfirmation()
                    ->action(fn (HbxHotel $record): bool => tap($record)->forceFill(['public_enabled' => true])->save()),
                Action::make('disable_public')
                    ->label(__('admin.hbx_hotels.actions.disable_public'))
                    ->visible(fn (HbxHotel $record): bool => $record->public_enabled && (bool) auth()->user()?->can('manage_suppliers'))
                    ->requiresConfirmation()
                    ->action(fn (HbxHotel $record): bool => tap($record)->forceFill(['public_enabled' => false])->save()),
            ])
            ->defaultSort('hotel_name');
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
        return ['index' => ListHbxHotels::route('/')];
    }
}
