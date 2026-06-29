<?php

namespace App\Filament\Resources;

use App\Enums\SupplierErrorType;
use App\Enums\SupplierOperation;
use App\Filament\Resources\SupplierOperationLogResource\Pages\ListSupplierOperationLogs;
use App\Models\Supplier;
use App\Models\SupplierOperationLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class SupplierOperationLogResource extends Resource
{
    protected static ?string $model = SupplierOperationLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 21;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.suppliers.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.supplier_logs.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.supplier_logs.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('supplier.code')->label(__('admin.suppliers.fields.code'))->searchable(),
                TextColumn::make('correlation_id')->label(__('admin.supplier_logs.fields.correlation_id'))->searchable()->copyable(),
                TextColumn::make('operation')->label(__('admin.supplier_logs.fields.operation'))->badge()->sortable(),
                IconColumn::make('successful')->label(__('admin.supplier_logs.fields.successful'))->boolean()->sortable(),
                TextColumn::make('error_type')->label(__('admin.supplier_logs.fields.error_type'))->badge()->sortable(),
                TextColumn::make('duration_ms')->label(__('admin.supplier_logs.fields.duration_ms'))->sortable(),
                TextColumn::make('booking_reference')->label(__('admin.supplier_logs.fields.booking_reference'))->searchable(),
            ])
            ->filters([
                SelectFilter::make('supplier_id')->label(__('admin.supplier_logs.fields.supplier'))->options(fn (): array => Supplier::query()->orderBy('code')->pluck('code', 'id')->all()),
                SelectFilter::make('operation')->label(__('admin.supplier_logs.fields.operation'))->options(SupplierOperation::options()),
                SelectFilter::make('successful')->label(__('admin.supplier_logs.fields.successful'))->options([1 => 'Yes', 0 => 'No']),
                SelectFilter::make('error_type')->label(__('admin.supplier_logs.fields.error_type'))->options(collect(SupplierErrorType::cases())->mapWithKeys(fn (SupplierErrorType $type): array => [$type->value => $type->value])->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', SupplierOperationLog::class);
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
        return ['index' => ListSupplierOperationLogs::route('/')];
    }
}
