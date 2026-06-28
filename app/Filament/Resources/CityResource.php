<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages\CreateCity;
use App\Filament\Resources\CityResource\Pages\EditCity;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Models\City;
use App\Models\Country;
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

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.reference_data.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.cities.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.cities.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('country_id')->label(__('admin.cities.fields.country'))->options(fn (): array => Country::query()->orderBy('name_en')->pluck('name_en', 'id')->all())->searchable()->required(),
            TextInput::make('code')->label(__('admin.cities.fields.code'))->nullable()->maxLength(64),
            TextInput::make('name_en')->label(__('admin.common_fields.name_en'))->required()->maxLength(255),
            TextInput::make('name_ar')->label(__('admin.common_fields.name_ar'))->required()->maxLength(255),
            TextInput::make('latitude')->label(__('admin.common_fields.latitude'))->numeric()->minValue(-90)->maxValue(90),
            TextInput::make('longitude')->label(__('admin.common_fields.longitude'))->numeric()->minValue(-180)->maxValue(180),
            TextInput::make('timezone')->label(__('admin.cities.fields.timezone'))->nullable()->maxLength(64),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
            Toggle::make('is_featured')->label(__('admin.cities.fields.is_featured'))->default(false),
            TextInput::make('sort_order')->label(__('admin.common_fields.sort_order'))->numeric()->default(0)->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country.name_en')->label(__('admin.cities.fields.country'))->searchable()->sortable(),
                TextColumn::make('name_en')->label(__('admin.common_fields.name_en'))->searchable()->sortable(),
                TextColumn::make('name_ar')->label(__('admin.common_fields.name_ar'))->searchable()->sortable(),
                TextColumn::make('timezone')->label(__('admin.cities.fields.timezone')),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean(),
                IconColumn::make('is_featured')->label(__('admin.cities.fields.is_featured'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('country_id')->label(__('admin.cities.fields.country'))->relationship('country', 'name_en'),
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
                TernaryFilter::make('is_featured')->label(__('admin.cities.fields.is_featured')),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
