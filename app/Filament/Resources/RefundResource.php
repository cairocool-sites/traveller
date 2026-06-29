<?php

namespace App\Filament\Resources;

use App\Enums\RefundStatus;
use App\Filament\Resources\RefundResource\Pages\ListRefunds;
use App\Models\Refund;
use App\Services\Refund\RefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('requested_at')->dateTime()->sortable(),
            TextColumn::make('booking.booking_reference')->searchable()->copyable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('requested_amount_minor')->numeric(),
            TextColumn::make('refunded_amount_minor')->numeric(),
        ])->filters([
            SelectFilter::make('status')->options(collect(RefundStatus::cases())->mapWithKeys(fn ($status): array => [$status->value => $status->value])->all()),
        ])->recordActions([
            Action::make('approve')->visible(fn (Refund $record): bool => Gate::allows('approve', $record) && in_array($record->status, [RefundStatus::Pending, RefundStatus::UnderReview], true))->form([TextInput::make('reason')->maxLength(255)])->action(fn (Refund $record, array $data) => app(RefundService::class)->approve($record, $data['reason'] ?? '', auth()->user()?->hasRole('super_admin') ?? false)),
            Action::make('complete')->visible(fn (Refund $record): bool => Gate::allows('complete', $record) && in_array($record->status, [RefundStatus::Approved, RefundStatus::Processing], true))->form([TextInput::make('reference')->required(), TextInput::make('reason')->maxLength(255)])->action(fn (Refund $record, array $data) => app(RefundService::class)->complete($record, $data['reference'], $data['reason'] ?? '', auth()->user()?->hasRole('super_admin') ?? false)),
            Action::make('reject')->visible(fn (Refund $record): bool => Gate::allows('reject', $record) && in_array($record->status, [RefundStatus::Pending, RefundStatus::UnderReview], true))->form([TextInput::make('reason')->required()])->action(fn (Refund $record, array $data) => app(RefundService::class)->reject($record, $data['reason'])),
        ])->defaultSort('requested_at', 'desc');
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
        return ['index' => ListRefunds::route('/')];
    }
}
