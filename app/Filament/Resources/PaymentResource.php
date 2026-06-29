<?php

namespace App\Filament\Resources;

use App\Enums\ManualPaymentStatus;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('booking.booking_reference')->searchable()->copyable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('method.code')->searchable(),
                TextColumn::make('amount_minor')->numeric()->sortable(),
                TextColumn::make('currency.code'),
                TextColumn::make('submitted_reference')->searchable(),
                TextColumn::make('reviewed_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(ManualPaymentStatus::cases())->mapWithKeys(fn ($status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                Action::make('under_review')
                    ->label(__('admin.payments.actions.under_review'))
                    ->visible(fn (Payment $record): bool => Gate::allows('review', $record) && $record->status === ManualPaymentStatus::Submitted)
                    ->action(fn (Payment $record) => app(PaymentService::class)->markUnderReview($record)),
                Action::make('approve')
                    ->label(__('admin.payments.actions.approve'))
                    ->visible(fn (Payment $record): bool => Gate::allows('approve', $record) && in_array($record->status, [ManualPaymentStatus::Submitted, ManualPaymentStatus::UnderReview], true))
                    ->form([TextInput::make('reason')->label(__('admin.payments.fields.reason'))->maxLength(255)])
                    ->action(fn (Payment $record, array $data) => app(PaymentService::class)->approve($record, $data['reason'] ?? '', auth()->user()?->hasRole('super_admin') ?? false)),
                Action::make('reject')
                    ->label(__('admin.payments.actions.reject'))
                    ->visible(fn (Payment $record): bool => Gate::allows('reject', $record) && in_array($record->status, [ManualPaymentStatus::Submitted, ManualPaymentStatus::UnderReview], true))
                    ->form([TextInput::make('reason')->label(__('admin.payments.fields.reason'))->required()->maxLength(255)])
                    ->action(fn (Payment $record, array $data) => app(PaymentService::class)->reject($record, $data['reason'])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Payment::class);
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
        return ['index' => ListPayments::route('/')];
    }
}
