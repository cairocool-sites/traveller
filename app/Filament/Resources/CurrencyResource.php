<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages\CreateCurrency;
use App\Filament\Resources\CurrencyResource\Pages\EditCurrency;
use App\Filament\Resources\CurrencyResource\Pages\ListCurrencies;
use App\Models\Currency;
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

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 60;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.reference_data.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.currencies.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.currencies.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('admin.currencies.fields.code'))->required()->length(3)->alpha()->unique(ignoreRecord: true),
            TextInput::make('numeric_code')->label(__('admin.currencies.fields.numeric_code'))->nullable()->numeric()->minLength(3)->maxLength(3),
            TextInput::make('name_en')->label(__('admin.common_fields.name_en'))->required()->maxLength(255),
            TextInput::make('name_ar')->label(__('admin.common_fields.name_ar'))->required()->maxLength(255),
            TextInput::make('symbol')->label(__('admin.currencies.fields.symbol'))->required()->maxLength(12),
            TextInput::make('decimal_places')->label(__('admin.currencies.fields.decimal_places'))->numeric()->minValue(0)->maxValue(4)->default(2)->required(),
            TextInput::make('rounding_increment')->label(__('admin.currencies.fields.rounding_increment'))->numeric()->minValue(0)->nullable(),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
            Toggle::make('is_base')->label(__('admin.currencies.fields.is_base'))->default(false),
            TextInput::make('sort_order')->label(__('admin.common_fields.sort_order'))->numeric()->default(0)->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('admin.currencies.fields.code'))->searchable()->sortable(),
                TextColumn::make('name_en')->label(__('admin.common_fields.name_en'))->searchable()->sortable(),
                TextColumn::make('symbol')->label(__('admin.currencies.fields.symbol')),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean(),
                IconColumn::make('is_base')->label(__('admin.currencies.fields.is_base'))->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
                TernaryFilter::make('is_base')->label(__('admin.currencies.fields.is_base')),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurrencies::route('/'),
            'create' => CreateCurrency::route('/create'),
            'edit' => EditCurrency::route('/{record}/edit'),
        ];
    }
}
