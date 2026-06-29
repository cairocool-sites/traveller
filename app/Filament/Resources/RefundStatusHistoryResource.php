<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RefundStatusHistoryResource\Pages\ListRefundStatusHistories;
use App\Models\Refund;
use App\Models\RefundStatusHistory;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class RefundStatusHistoryResource extends Resource
{
    protected static ?string $model = RefundStatusHistory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('created_at')->dateTime(), TextColumn::make('refund.booking.booking_reference'), TextColumn::make('from_status'), TextColumn::make('to_status'), TextColumn::make('reason')->limit(80)]);
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Refund::class);
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
        return ['index' => ListRefundStatusHistories::route('/')];
    }
}
