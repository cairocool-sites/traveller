<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CancellationStatusHistoryResource\Pages\ListCancellationStatusHistories;
use App\Models\CancellationStatusHistory;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CancellationStatusHistoryResource extends Resource
{
    protected static ?string $model = CancellationStatusHistory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Reservations';

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('created_at')->dateTime(), TextColumn::make('cancellation.booking.booking_reference'), TextColumn::make('from_status'), TextColumn::make('to_status'), TextColumn::make('reason')->limit(80)]);
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', BookingCancellation::class);
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
        return ['index' => ListCancellationStatusHistories::route('/')];
    }
}
