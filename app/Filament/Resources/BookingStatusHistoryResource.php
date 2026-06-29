<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingStatusHistoryResource\Pages\ListBookingStatusHistories;
use App\Models\BookingStatusHistory;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class BookingStatusHistoryResource extends Resource
{
    protected static ?string $model = BookingStatusHistory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Reservations';

    protected static ?int $navigationSort = 32;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.bookings.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.bookings.history_model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.bookings.history_plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('booking.booking_reference')->searchable()->copyable(),
                TextColumn::make('from_status')->badge(),
                TextColumn::make('to_status')->badge(),
                TextColumn::make('reason')->limit(80),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', BookingStatusHistory::class);
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
        return ['index' => ListBookingStatusHistories::route('/')];
    }
}
