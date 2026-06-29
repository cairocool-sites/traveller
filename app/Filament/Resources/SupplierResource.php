<?php

namespace App\Filament\Resources;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierIntegrationType;
use App\Enums\SupplierStatus;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Filament\Resources\SupplierResource\RelationManagers\CredentialsRelationManager;
use App\Models\Supplier;
use App\Services\Supplier\SupplierHealthCheckService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cloud';

    protected static string|\UnitEnum|null $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.suppliers.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.suppliers.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.suppliers.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('supplier_tabs')->tabs([
                Tab::make(__('admin.suppliers.tabs.identity'))->schema([
                    Section::make()->schema([
                        TextInput::make('name')->label(__('admin.suppliers.fields.name'))->required()->maxLength(255),
                        TextInput::make('code')->label(__('admin.suppliers.fields.code'))->required()->unique(ignoreRecord: true)->maxLength(64),
                        Select::make('integration_type')->label(__('admin.suppliers.fields.integration_type'))->options(SupplierIntegrationType::options())->required(),
                        Select::make('environment')->label(__('admin.suppliers.fields.environment'))->options(SupplierEnvironment::options())->default(SupplierEnvironment::Sandbox->value)->required(),
                        Select::make('status')->label(__('admin.suppliers.fields.status'))->options(SupplierStatus::options())->default(SupplierStatus::Inactive->value)->required(),
                        TextInput::make('priority')->label(__('admin.suppliers.fields.priority'))->numeric()->minValue(1)->default(100)->required(),
                        TextInput::make('base_url')->label(__('admin.suppliers.fields.base_url'))->url()->nullable()->maxLength(255),
                    ])->columns(2),
                ]),
                Tab::make(__('admin.suppliers.tabs.capabilities'))->schema([
                    Section::make()->schema([
                        Toggle::make('search_enabled')->label(__('admin.suppliers.fields.search_enabled')),
                        Toggle::make('details_enabled')->label(__('admin.suppliers.fields.details_enabled')),
                        Toggle::make('check_rate_enabled')->label(__('admin.suppliers.fields.check_rate_enabled')),
                        Toggle::make('booking_enabled')->label(__('admin.suppliers.fields.booking_enabled')),
                        Toggle::make('cancellation_enabled')->label(__('admin.suppliers.fields.cancellation_enabled')),
                        Toggle::make('booking_lookup_enabled')->label(__('admin.suppliers.fields.booking_lookup_enabled')),
                        Toggle::make('health_check_enabled')->label(__('admin.suppliers.fields.health_check_enabled')),
                    ])->columns(2),
                ]),
                Tab::make(__('admin.suppliers.tabs.resilience'))->schema([
                    Section::make()->schema([
                        TextInput::make('timeout_seconds')->label(__('admin.suppliers.fields.timeout_seconds'))->numeric()->minValue(1)->maxValue(120)->default(15)->required(),
                        TextInput::make('connect_timeout_seconds')->label(__('admin.suppliers.fields.connect_timeout_seconds'))->numeric()->minValue(1)->maxValue(60)->default(5)->required(),
                        TextInput::make('max_retries')->label(__('admin.suppliers.fields.max_retries'))->numeric()->minValue(0)->maxValue(5)->default(0)->required(),
                        TextInput::make('retry_delay_milliseconds')->label(__('admin.suppliers.fields.retry_delay_milliseconds'))->numeric()->minValue(0)->maxValue(10000)->default(250)->required(),
                    ])->columns(2),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.suppliers.fields.name'))->searchable()->sortable(),
                TextColumn::make('code')->label(__('admin.suppliers.fields.code'))->searchable()->sortable(),
                TextColumn::make('integration_type')->label(__('admin.suppliers.fields.integration_type'))->badge()->sortable(),
                TextColumn::make('environment')->label(__('admin.suppliers.fields.environment'))->badge()->sortable(),
                TextColumn::make('status')->label(__('admin.suppliers.fields.status'))->badge()->sortable(),
                TextColumn::make('priority')->label(__('admin.suppliers.fields.priority'))->sortable(),
                TextColumn::make('health_status')->label(__('admin.suppliers.fields.health_status'))->badge()->sortable(),
                TextColumn::make('last_health_check_at')->label(__('admin.suppliers.fields.last_health_check_at'))->dateTime()->sortable(),
                IconColumn::make('search_enabled')->label(__('admin.suppliers.fields.search_enabled'))->boolean(),
                IconColumn::make('booking_enabled')->label(__('admin.suppliers.fields.booking_enabled'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('admin.suppliers.fields.status'))->options(SupplierStatus::options()),
                SelectFilter::make('environment')->label(__('admin.suppliers.fields.environment'))->options(SupplierEnvironment::options()),
                SelectFilter::make('integration_type')->label(__('admin.suppliers.fields.integration_type'))->options(SupplierIntegrationType::options()),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                Action::make('health_check')
                    ->label(__('admin.suppliers.actions.health_check'))
                    ->visible(fn (Supplier $record): bool => Gate::allows('runHealthCheck', $record))
                    ->action(fn (Supplier $record) => app(SupplierHealthCheckService::class)->check($record)),
                Action::make('disable')
                    ->label(__('admin.suppliers.actions.disable'))
                    ->visible(fn (Supplier $record): bool => Gate::allows('update', $record) && $record->status !== SupplierStatus::Disabled)
                    ->requiresConfirmation()
                    ->action(fn (Supplier $record): bool => tap($record)->forceFill(['status' => SupplierStatus::Disabled, 'updated_by' => Auth::id()])->save()),
            ])
            ->defaultSort('priority');
    }

    public static function getRelations(): array
    {
        return [CredentialsRelationManager::class];
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Supplier::class);
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create', Supplier::class);
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
