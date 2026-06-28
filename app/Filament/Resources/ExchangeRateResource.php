<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExchangeRateResource\Pages\CreateExchangeRate;
use App\Filament\Resources\ExchangeRateResource\Pages\EditExchangeRate;
use App\Filament\Resources\ExchangeRateResource\Pages\ListExchangeRates;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
use Illuminate\Support\Facades\Auth;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 70;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.reference_data.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.exchange_rates.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.exchange_rates.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('base_currency_id')->label(__('admin.exchange_rates.fields.base_currency'))->options(fn (): array => Currency::query()->where('is_active', true)->orderBy('code')->pluck('code', 'id')->all())->searchable()->required(),
            Select::make('quote_currency_id')->label(__('admin.exchange_rates.fields.quote_currency'))->options(fn (): array => Currency::query()->where('is_active', true)->orderBy('code')->pluck('code', 'id')->all())->searchable()->required()->different('base_currency_id'),
            TextInput::make('rate')->label(__('admin.exchange_rates.fields.rate'))->numeric()->minValue('0.0000000001')->required(),
            TextInput::make('source')->label(__('admin.exchange_rates.fields.source'))->default('manual')->required()->maxLength(64),
            DateTimePicker::make('effective_at')->label(__('admin.exchange_rates.fields.effective_at'))->default(now())->required(),
            DateTimePicker::make('expires_at')->label(__('admin.exchange_rates.fields.expires_at'))->nullable()->after('effective_at'),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
            TextInput::make('created_by')->hidden()->default(fn (): ?int => Auth::id()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('baseCurrency.code')->label(__('admin.exchange_rates.fields.base_currency'))->sortable(),
                TextColumn::make('quoteCurrency.code')->label(__('admin.exchange_rates.fields.quote_currency'))->sortable(),
                TextColumn::make('rate')->label(__('admin.exchange_rates.fields.rate')),
                TextColumn::make('source')->label(__('admin.exchange_rates.fields.source')),
                TextColumn::make('effective_at')->label(__('admin.exchange_rates.fields.effective_at'))->dateTime()->sortable(),
                TextColumn::make('expires_at')->label(__('admin.exchange_rates.fields.expires_at'))->dateTime()->sortable(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('base_currency_id')->label(__('admin.exchange_rates.fields.base_currency'))->relationship('baseCurrency', 'code'),
                SelectFilter::make('quote_currency_id')->label(__('admin.exchange_rates.fields.quote_currency'))->relationship('quoteCurrency', 'code'),
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->defaultSort('effective_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExchangeRates::route('/'),
            'create' => CreateExchangeRate::route('/create'),
            'edit' => EditExchangeRate::route('/{record}/edit'),
        ];
    }
}
