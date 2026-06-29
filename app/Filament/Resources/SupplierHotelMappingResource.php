<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierHotelMappingResource\Pages\EditSupplierHotelMapping;
use App\Filament\Resources\SupplierHotelMappingResource\Pages\ListSupplierHotelMappings;
use App\Models\Supplier;
use App\Models\SupplierHotelMapping;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class SupplierHotelMappingResource extends Resource
{
    protected static ?string $model = SupplierHotelMapping::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 27;

    public static function getModelLabel(): string
    {
        return __('admin.supplier_hotel_mappings.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.supplier_hotel_mappings.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->label(__('admin.supplier_mappings.fields.status'))->options(['suggested' => 'Suggested', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'])->required(),
            TextInput::make('confidence')->label(__('admin.supplier_mappings.fields.confidence'))->numeric()->minValue(0)->maxValue(100)->required(),
            Toggle::make('manually_confirmed')->label(__('admin.supplier_mappings.fields.manually_confirmed')),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hotel.name')->label(__('admin.supplier_hotel_mappings.fields.local_hotel'))->searchable(),
                TextColumn::make('supplier_hotel_code')->label(__('admin.supplier_hotel_mappings.fields.hotel_code'))->searchable()->copyable(),
                TextColumn::make('status')->label(__('admin.supplier_mappings.fields.status'))->badge()->sortable(),
                IconColumn::make('manually_confirmed')->label(__('admin.supplier_mappings.fields.manually_confirmed'))->boolean(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['suggested' => 'Suggested', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected']),
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('updated_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Supplier::class);
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('update', Supplier::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierHotelMappings::route('/'),
            'edit' => EditSupplierHotelMapping::route('/{record}/edit'),
        ];
    }
}
