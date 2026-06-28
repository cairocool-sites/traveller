<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CountryResource\Pages\CreateCountry;
use App\Filament\Resources\CountryResource\Pages\EditCountry;
use App\Filament\Resources\CountryResource\Pages\ListCountries;
use App\Models\Country;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.reference_data.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.countries.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.countries.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('iso2')->label(__('admin.countries.fields.iso2'))->required()->length(2)->alpha()->unique(ignoreRecord: true),
            TextInput::make('iso3')->label(__('admin.countries.fields.iso3'))->required()->length(3)->alpha()->unique(ignoreRecord: true),
            TextInput::make('numeric_code')->label(__('admin.countries.fields.numeric_code'))->nullable()->numeric()->minLength(3)->maxLength(3),
            TextInput::make('phone_code')->label(__('admin.countries.fields.phone_code'))->nullable()->maxLength(12),
            TextInput::make('name_en')->label(__('admin.common_fields.name_en'))->required()->maxLength(255),
            TextInput::make('name_ar')->label(__('admin.common_fields.name_ar'))->required()->maxLength(255),
            TextInput::make('nationality_en')->label(__('admin.countries.fields.nationality_en'))->nullable()->maxLength(255),
            TextInput::make('nationality_ar')->label(__('admin.countries.fields.nationality_ar'))->nullable()->maxLength(255),
            TextInput::make('currency_code')->label(__('admin.countries.fields.currency_code'))->nullable()->length(3)->alpha(),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
            TextInput::make('sort_order')->label(__('admin.common_fields.sort_order'))->numeric()->default(0)->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('iso2')->label(__('admin.countries.fields.iso2'))->searchable()->sortable(),
                TextColumn::make('iso3')->label(__('admin.countries.fields.iso3'))->searchable()->sortable(),
                TextColumn::make('name_en')->label(__('admin.common_fields.name_en'))->searchable()->sortable(),
                TextColumn::make('name_ar')->label(__('admin.common_fields.name_ar'))->searchable()->sortable(),
                TextColumn::make('currency_code')->label(__('admin.countries.fields.currency_code'))->sortable(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCountries::route('/'),
            'create' => CreateCountry::route('/create'),
            'edit' => EditCountry::route('/{record}/edit'),
        ];
    }
}
