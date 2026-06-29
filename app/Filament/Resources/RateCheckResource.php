<?php

namespace App\Filament\Resources;

use App\Enums\RateCheckStatus;
use App\Filament\Resources\RateCheckResource\Pages\ListRateChecks;
use App\Models\RateCheck;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class RateCheckResource extends Resource
{
    protected static ?string $model = RateCheck::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|\UnitEnum|null $navigationGroup = 'Reservations';

    protected static ?int $navigationSort = 31;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.bookings.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.bookings.rate_check_model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.bookings.rate_check_plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('public_uuid')->searchable()->copyable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('supplier.code')->sortable(),
                TextColumn::make('currency.code'),
                TextColumn::make('original_amount_minor')->numeric()->sortable(),
                TextColumn::make('checked_amount_minor')->numeric()->sortable(),
                TextColumn::make('correlation_id')->searchable()->copyable(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(RateCheckStatus::cases())->mapWithKeys(fn (RateCheckStatus $status): array => [$status->value => $status->value])->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', RateCheck::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListRateChecks::route('/')];
    }
}
