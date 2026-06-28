<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AreaResource\Pages\CreateArea;
use App\Filament\Resources\AreaResource\Pages\EditArea;
use App\Filament\Resources\AreaResource\Pages\ListAreas;
use App\Models\Area;
use App\Models\City;
use Filament\Actions\CreateAction;
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

class AreaResource extends Resource
{
    protected static ?string $model = Area::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.reference_data.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.areas.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.areas.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('city_id')->label(__('admin.areas.fields.city'))->options(fn (): array => City::query()->with('country')->get()->mapWithKeys(fn (City $city): array => [$city->id => "{$city->name_en}, {$city->country?->name_en}"])->all())->searchable()->required(),
            TextInput::make('name_en')->label(__('admin.common_fields.name_en'))->required()->maxLength(255),
            TextInput::make('name_ar')->label(__('admin.common_fields.name_ar'))->required()->maxLength(255),
            TextInput::make('latitude')->label(__('admin.common_fields.latitude'))->numeric()->minValue(-90)->maxValue(90),
            TextInput::make('longitude')->label(__('admin.common_fields.longitude'))->numeric()->minValue(-180)->maxValue(180),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
            TextInput::make('sort_order')->label(__('admin.common_fields.sort_order'))->numeric()->default(0)->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('city.country.name_en')->label(__('admin.cities.fields.country'))->sortable(),
                TextColumn::make('city.name_en')->label(__('admin.areas.fields.city'))->searchable()->sortable(),
                TextColumn::make('name_en')->label(__('admin.common_fields.name_en'))->searchable()->sortable(),
                TextColumn::make('name_ar')->label(__('admin.common_fields.name_ar'))->searchable()->sortable(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('city_id')->label(__('admin.areas.fields.city'))->relationship('city', 'name_en'),
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAreas::route('/'),
            'create' => CreateArea::route('/create'),
            'edit' => EditArea::route('/{record}/edit'),
        ];
    }
}
