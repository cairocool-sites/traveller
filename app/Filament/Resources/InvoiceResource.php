<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Invoice;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Documents';

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('issued_at')->dateTime()->sortable(), TextColumn::make('invoice_number')->searchable()->copyable(), TextColumn::make('booking.booking_reference')->searchable(), TextColumn::make('total_minor')->numeric(), TextColumn::make('status')->badge()])->defaultSort('issued_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Invoice::class);
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
        return ['index' => ListInvoices::route('/')];
    }
}
