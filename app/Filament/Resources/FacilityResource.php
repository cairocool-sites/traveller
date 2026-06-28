<?php

namespace App\Filament\Resources;

use App\Enums\FacilityCategory;
use App\Filament\Resources\FacilityResource\Pages\CreateFacility;
use App\Filament\Resources\FacilityResource\Pages\EditFacility;
use App\Filament\Resources\FacilityResource\Pages\ListFacilities;
use App\Models\Facility;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 80;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.reference_data.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin.facilities.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.facilities.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('admin.facilities.fields.code'))->required()->regex('/^[a-z0-9_]+$/')->unique(ignoreRecord: true)->maxLength(64),
            TextInput::make('icon')->label(__('admin.facilities.fields.icon'))->nullable()->maxLength(64),
            Select::make('category')->label(__('admin.facilities.fields.category'))->options(FacilityCategory::options())->required(),
            TextInput::make('name_en')->label(__('admin.facilities.fields.name_en'))->required()->maxLength(255)->dehydrated(),
            TextInput::make('name_ar')->label(__('admin.facilities.fields.name_ar'))->required()->maxLength(255)->dehydrated(),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
            TextInput::make('sort_order')->label(__('admin.common_fields.sort_order'))->numeric()->default(0)->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('admin.facilities.fields.code'))->searchable()->sortable(),
                TextColumn::make('translations.name')->label(__('admin.facilities.fields.name'))->searchable(),
                TextColumn::make('category')->label(__('admin.facilities.fields.category'))->formatStateUsing(fn (FacilityCategory $state): string => __('admin.facilities.categories.'.$state->value))->sortable(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('admin.facilities.fields.category'))->options(FacilityCategory::options()),
                TernaryFilter::make('is_active')->label(__('admin.common_fields.is_active')),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFacilities::route('/'),
            'create' => CreateFacility::route('/create'),
            'edit' => EditFacility::route('/{record}/edit'),
        ];
    }
}
