<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceiptResource\Pages\ListReceipts;
use App\Models\Receipt;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';

    protected static string|\UnitEnum|null $navigationGroup = 'Documents';

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('issued_at')->dateTime()->sortable(), TextColumn::make('receipt_number')->searchable()->copyable(), TextColumn::make('payment.booking.booking_reference')->searchable(), TextColumn::make('amount_minor')->numeric(), TextColumn::make('status')->badge()])->defaultSort('issued_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Receipt::class);
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
        return ['index' => ListReceipts::route('/')];
    }
}
