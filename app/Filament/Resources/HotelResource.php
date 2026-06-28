<?php

namespace App\Filament\Resources;

use App\Enums\HotelStatus;
use App\Enums\PropertyType;
use App\Filament\Resources\HotelResource\Pages\CreateHotel;
use App\Filament\Resources\HotelResource\Pages\EditHotel;
use App\Filament\Resources\HotelResource\Pages\ListHotels;
use App\Filament\Resources\HotelResource\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\HotelResource\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\HotelResource\RelationManagers\PolicyRelationManager;
use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Facility;
use App\Models\Hotel;
use App\Services\Hotel\HotelCatalogService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class HotelResource extends Resource
{
    protected static ?string $model = Hotel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Hotel Management';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.hotels.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.hotels.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.hotels.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('hotel_tabs')->tabs([
                Tab::make(__('admin.hotels.tabs.core'))->schema([
                    Section::make()->schema([
                        Select::make('country_id')->label(__('admin.cities.fields.country'))->options(fn (): array => Country::query()->orderBy('name_en')->pluck('name_en', 'id')->all())->searchable()->required(),
                        Select::make('city_id')->label(__('admin.areas.fields.city'))->options(fn (): array => City::query()->with('country')->get()->mapWithKeys(fn (City $city): array => [$city->id => "{$city->name_en}, {$city->country?->name_en}"])->all())->searchable()->required(),
                        Select::make('area_id')->label(__('admin.hotels.fields.area'))->options(fn (): array => Area::query()->with('city')->get()->mapWithKeys(fn (Area $area): array => [$area->id => "{$area->name_en}, {$area->city?->name_en}"])->all())->searchable()->nullable(),
                        Select::make('default_currency_id')->label(__('admin.hotels.fields.default_currency'))->options(fn (): array => Currency::query()->orderBy('code')->pluck('code', 'id')->all())->searchable()->nullable(),
                        TextInput::make('name')->label(__('admin.hotels.fields.name'))->required()->maxLength(255),
                        TextInput::make('slug')->label(__('admin.hotels.fields.slug'))->required()->unique(ignoreRecord: true)->maxLength(255),
                        TextInput::make('internal_code')->label(__('admin.hotels.fields.internal_code'))->required()->unique(ignoreRecord: true)->maxLength(64),
                        Select::make('property_type')->label(__('admin.hotels.fields.property_type'))->options(PropertyType::options())->default(PropertyType::Hotel->value)->required(),
                        Select::make('status')->label(__('admin.hotels.fields.status'))->options(HotelStatus::options())->default(HotelStatus::Draft->value)->required(),
                    ])->columns(2),
                ]),
                Tab::make(__('admin.hotels.tabs.content'))->schema([
                    Section::make(__('admin.locales.en'))->schema([
                        TextInput::make('translation_en_name')->label(__('admin.hotels.fields.translated_name'))->maxLength(255),
                        Textarea::make('translation_en_short_description')->label(__('admin.hotels.fields.short_description'))->rows(3),
                        Textarea::make('translation_en_description')->label(__('admin.hotels.fields.description'))->rows(6),
                        Textarea::make('translation_en_address_text')->label(__('admin.hotels.fields.address_text'))->rows(3),
                        TextInput::make('translation_en_meta_title')->label(__('admin.hotels.fields.meta_title'))->maxLength(255),
                        Textarea::make('translation_en_meta_description')->label(__('admin.hotels.fields.meta_description'))->rows(3),
                    ])->columns(1),
                    Section::make(__('admin.locales.ar'))->schema([
                        TextInput::make('translation_ar_name')->label(__('admin.hotels.fields.translated_name'))->maxLength(255),
                        Textarea::make('translation_ar_short_description')->label(__('admin.hotels.fields.short_description'))->rows(3),
                        Textarea::make('translation_ar_description')->label(__('admin.hotels.fields.description'))->rows(6),
                        Textarea::make('translation_ar_address_text')->label(__('admin.hotels.fields.address_text'))->rows(3),
                        TextInput::make('translation_ar_meta_title')->label(__('admin.hotels.fields.meta_title'))->maxLength(255),
                        Textarea::make('translation_ar_meta_description')->label(__('admin.hotels.fields.meta_description'))->rows(3),
                    ])->columns(1),
                ]),
                Tab::make(__('admin.hotels.tabs.details'))->schema([
                    Section::make()->schema([
                        TextInput::make('star_rating')->label(__('admin.hotels.fields.star_rating'))->numeric()->minValue(1)->maxValue(5)->nullable(),
                        TextInput::make('latitude')->label(__('admin.common_fields.latitude'))->numeric()->minValue(-90)->maxValue(90),
                        TextInput::make('longitude')->label(__('admin.common_fields.longitude'))->numeric()->minValue(-180)->maxValue(180),
                        TextInput::make('timezone')->label(__('admin.cities.fields.timezone'))->maxLength(64),
                        TextInput::make('total_rooms')->label(__('admin.hotels.fields.total_rooms'))->numeric()->minValue(0),
                        TextInput::make('year_opened')->label(__('admin.hotels.fields.year_opened'))->numeric()->minValue(1800)->maxValue((int) date('Y')),
                        TextInput::make('year_renovated')->label(__('admin.hotels.fields.year_renovated'))->numeric()->minValue(1800)->maxValue((int) date('Y')),
                        TextInput::make('check_in_time')->label(__('admin.hotels.fields.check_in_time')),
                        TextInput::make('check_out_time')->label(__('admin.hotels.fields.check_out_time')),
                        Toggle::make('is_featured')->label(__('admin.cities.fields.is_featured'))->default(false),
                        Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
                    ])->columns(2),
                ]),
                Tab::make(__('admin.hotels.tabs.contact'))->schema([
                    Section::make()->schema([
                        TextInput::make('address_line_1')->label(__('admin.hotels.fields.address_line_1'))->maxLength(255),
                        TextInput::make('address_line_2')->label(__('admin.hotels.fields.address_line_2'))->maxLength(255),
                        TextInput::make('postal_code')->label(__('admin.hotels.fields.postal_code'))->maxLength(32),
                        TextInput::make('primary_phone')->label(__('admin.hotels.fields.primary_phone'))->maxLength(64),
                        TextInput::make('primary_email')->label(__('admin.hotels.fields.primary_email'))->email()->maxLength(255),
                        TextInput::make('website_url')->label(__('admin.hotels.fields.website_url'))->url()->maxLength(255),
                    ])->columns(2),
                ]),
                Tab::make(__('admin.hotels.tabs.facilities'))->schema([
                    Select::make('facility_ids')->label(__('admin.facilities.plural_model_label'))->multiple()->options(fn (): array => Facility::query()->orderBy('code')->pluck('code', 'id')->all())->searchable()->disabled(fn (?Hotel $record): bool => $record instanceof Hotel && Gate::denies('manageFacilities', $record))->dehydrated(),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('internal_code')->label(__('admin.hotels.fields.internal_code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('admin.hotels.fields.name'))->searchable()->sortable(),
                TextColumn::make('city.name_en')->label(__('admin.areas.fields.city'))->sortable(),
                TextColumn::make('status')->label(__('admin.hotels.fields.status'))->formatStateUsing(fn (HotelStatus $state): string => __('admin.hotels.statuses.'.$state->value))->badge()->sortable(),
                TextColumn::make('property_type')->label(__('admin.hotels.fields.property_type'))->formatStateUsing(fn (PropertyType $state): string => __('admin.hotels.property_types.'.$state->value))->sortable(),
                TextColumn::make('star_rating')->label(__('admin.hotels.fields.star_rating'))->sortable(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean()->sortable(),
                IconColumn::make('is_featured')->label(__('admin.cities.fields.is_featured'))->boolean()->sortable(),
                TextColumn::make('published_at')->label(__('admin.hotels.fields.published_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('country_id')->label(__('admin.cities.fields.country'))->relationship('country', 'name_en'),
                SelectFilter::make('city_id')->label(__('admin.areas.fields.city'))->relationship('city', 'name_en'),
                SelectFilter::make('area_id')->label(__('admin.hotels.fields.area'))->relationship('area', 'name_en'),
                SelectFilter::make('status')->label(__('admin.hotels.fields.status'))->options(HotelStatus::options()),
                SelectFilter::make('star_rating')->label(__('admin.hotels.fields.star_rating'))->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5']),
                SelectFilter::make('property_type')->label(__('admin.hotels.fields.property_type'))->options(PropertyType::options()),
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
                TernaryFilter::make('is_featured')->label(__('admin.cities.fields.is_featured')),
                TernaryFilter::make('published_at')->label(__('admin.hotels.fields.published_at'))->nullable(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                Action::make('publish')
                    ->label(__('admin.hotels.actions.publish'))
                    ->visible(fn (Hotel $record): bool => Gate::allows('publish', $record) && $record->status !== HotelStatus::Published)
                    ->action(fn (Hotel $record): Hotel => app(HotelCatalogService::class)->publish($record, Auth::user())),
                Action::make('unpublish')
                    ->label(__('admin.hotels.actions.unpublish'))
                    ->visible(fn (Hotel $record): bool => Gate::allows('publish', $record) && $record->status === HotelStatus::Published)
                    ->requiresConfirmation()
                    ->action(fn (Hotel $record): Hotel => app(HotelCatalogService::class)->unpublish($record, Auth::user())),
                Action::make('toggle_active')
                    ->label(fn (Hotel $record): string => $record->is_active ? __('admin.users.actions.deactivate') : __('admin.users.actions.activate'))
                    ->visible(fn (Hotel $record): bool => Gate::allows('update', $record))
                    ->requiresConfirmation()
                    ->action(fn (Hotel $record): bool => tap($record)->forceFill(['is_active' => ! $record->is_active, 'updated_by' => Auth::id()])->save()),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            ImagesRelationManager::class,
            PolicyRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['country', 'city', 'area']);
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', Hotel::class);
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create', Hotel::class);
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHotels::route('/'),
            'create' => CreateHotel::route('/create'),
            'edit' => EditHotel::route('/{record}/edit'),
        ];
    }
}
