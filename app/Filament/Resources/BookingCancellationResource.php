<?php

namespace App\Filament\Resources;

use App\Enums\CancellationStatus;
use App\Filament\Resources\BookingCancellationResource\Pages\ListBookingCancellations;
use App\Models\BookingCancellation;
use App\Services\Cancellation\CancellationService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class BookingCancellationResource extends Resource
{
    protected static ?string $model = BookingCancellation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-x-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Reservations';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('requested_at')->dateTime()->sortable(),
            TextColumn::make('booking.booking_reference')->searchable()->copyable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('penalty_amount_minor')->numeric(),
            TextColumn::make('refundable_amount_minor')->numeric(),
        ])->filters([
            SelectFilter::make('status')->options(collect(CancellationStatus::cases())->mapWithKeys(fn ($status): array => [$status->value => $status->value])->all()),
        ])->recordActions([
            Action::make('submit_supplier')
                ->visible(fn (BookingCancellation $record): bool => Gate::allows('submitSupplier', $record) && in_array($record->status, [CancellationStatus::Requested, CancellationStatus::UnderReview, CancellationStatus::ManualReview], true))
                ->form([TextInput::make('reason')->maxLength(255)])
                ->action(fn (BookingCancellation $record) => app(CancellationService::class)->submitSupplier($record)),
        ])->defaultSort('requested_at', 'desc');
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
        return ['index' => ListBookingCancellations::route('/')];
    }
}
