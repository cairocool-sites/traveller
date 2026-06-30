<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Models\Booking;
use App\Services\Booking\BookingReconciliationService;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Reservations';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.bookings.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.bookings.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.bookings.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('admin.bookings.fields.created_at'))->dateTime()->sortable(),
                TextColumn::make('booking_reference')->label(__('admin.bookings.fields.booking_reference'))->searchable()->copyable(),
                TextColumn::make('supplier_booking_reference')->label(__('admin.bookings.fields.supplier_booking_reference'))->searchable()->toggleable(),
                TextColumn::make('idempotency_key')->label(__('admin.bookings.fields.client_reference'))->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label(__('admin.bookings.fields.status'))->badge()->sortable(),
                TextColumn::make('payment_status')->label(__('admin.bookings.fields.payment_status'))->badge()->sortable(),
                TextColumn::make('supplier.code')->label(__('admin.bookings.fields.supplier'))->sortable(),
                TextColumn::make('check_in')->label(__('admin.bookings.fields.check_in'))->date()->sortable(),
                TextColumn::make('check_out')->label(__('admin.bookings.fields.check_out'))->date()->sortable(),
                TextColumn::make('currency.code')->label(__('admin.bookings.fields.currency')),
                TextColumn::make('total_amount_minor')->label(__('admin.bookings.fields.total'))->numeric()->sortable(),
                TextColumn::make('contact_email')->label(__('admin.bookings.fields.contact_email'))->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(BookingStatus::cases())->mapWithKeys(fn (BookingStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                Action::make('preview_voucher')
                    ->label(__('admin.bookings.actions.preview_voucher'))
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (Booking $record): bool => Gate::allows('view', $record) && in_array($record->status, [BookingStatus::Confirmed, BookingStatus::ManualReview], true))
                    ->url(fn (Booking $record): string => route('admin.bookings.voucher', $record))
                    ->openUrlInNewTab(),
                Action::make('download_voucher')
                    ->label(__('admin.bookings.actions.download_voucher'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Booking $record): bool => Gate::allows('view', $record) && in_array($record->status, [BookingStatus::Confirmed, BookingStatus::ManualReview], true))
                    ->url(fn (Booking $record): string => route('admin.bookings.voucher', ['booking' => $record, 'download' => true]))
                    ->openUrlInNewTab(),
                Action::make('reconcile')
                    ->label(__('admin.bookings.actions.reconcile'))
                    ->visible(fn (Booking $record): bool => Gate::allows('reconcile', $record))
                    ->action(fn (Booking $record) => app(BookingReconciliationService::class)->reconcile($record)),
                Action::make('view_reconciliation')
                    ->label(__('admin.bookings.actions.view_reconciliation'))
                    ->icon('heroicon-o-scale')
                    ->visible(fn (Booking $record): bool => Gate::allows('reconcile', $record))
                    ->url(fn (Booking $record): string => route('admin.bookings.reconciliation', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Booking::class);
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
        return ['index' => ListBookings::route('/')];
    }
}
