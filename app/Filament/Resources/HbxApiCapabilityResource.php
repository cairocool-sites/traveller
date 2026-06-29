<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HbxApiCapabilityResource\Pages\ListHbxApiCapabilities;
use App\Models\HbxApiCapability;
use App\Models\Supplier;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class HbxApiCapabilityResource extends Resource
{
    protected static ?string $model = HbxApiCapability::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 19;

    public static function getModelLabel(): string
    {
        return __('admin.hbx_capabilities.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.hbx_capabilities.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('api_family')->label(__('admin.hbx_capabilities.fields.api_family'))->searchable()->sortable(),
                TextColumn::make('display_name')->label(__('admin.hbx_capabilities.fields.display_name'))->searchable()->sortable(),
                TextColumn::make('capability_code')->label(__('admin.hbx_capabilities.fields.capability_code'))->searchable()->copyable()->toggleable(),
                TextColumn::make('http_method')->label(__('admin.hbx_capabilities.fields.http_method'))->badge()->toggleable(),
                TextColumn::make('endpoint_path')->label(__('admin.hbx_capabilities.fields.endpoint_path'))->searchable()->toggleable(),
                IconColumn::make('implemented')->label(__('admin.hbx_capabilities.fields.implemented'))->boolean()->sortable(),
                IconColumn::make('configured')->label(__('admin.hbx_capabilities.fields.configured'))->boolean()->sortable(),
                IconColumn::make('credential_access_confirmed')->label(__('admin.hbx_capabilities.fields.credential_access_confirmed'))->boolean()->sortable(),
                IconColumn::make('sandbox_tested')->label(__('admin.hbx_capabilities.fields.sandbox_tested'))->boolean()->sortable(),
                IconColumn::make('admin_enabled')->label(__('admin.hbx_capabilities.fields.admin_enabled'))->boolean()->sortable(),
                IconColumn::make('public_enabled')->label(__('admin.hbx_capabilities.fields.public_enabled'))->boolean()->sortable(),
                IconColumn::make('production_enabled')->label(__('admin.hbx_capabilities.fields.production_enabled'))->boolean()->sortable(),
                TextColumn::make('last_successful_call_at')->label(__('admin.hbx_capabilities.fields.last_successful_call_at'))->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('api_family')->label(__('admin.hbx_capabilities.fields.api_family'))->options(fn (): array => HbxApiCapability::query()->orderBy('api_family')->pluck('api_family', 'api_family')->all()),
                TernaryFilter::make('implemented')->label(__('admin.hbx_capabilities.fields.implemented')),
                TernaryFilter::make('admin_enabled')->label(__('admin.hbx_capabilities.fields.admin_enabled')),
                TernaryFilter::make('public_enabled')->label(__('admin.hbx_capabilities.fields.public_enabled')),
            ])
            ->defaultSort('api_family');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Supplier::class);
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
        return ['index' => ListHbxApiCapabilities::route('/')];
    }
}
