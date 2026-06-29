<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SearchSessionResource\Pages\ListSearchSessions;
use App\Models\SearchSession;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class SearchSessionResource extends Resource
{
    protected static ?string $model = SearchSession::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 22;

    public static function getModelLabel(): string
    {
        return __('admin.search_sessions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.search_sessions.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('public_uuid')->label(__('admin.search_sessions.fields.public_uuid'))->searchable()->copyable(),
                TextColumn::make('destination_label')->label(__('admin.search_sessions.fields.destination'))->searchable(),
                TextColumn::make('check_in')->date()->sortable(),
                TextColumn::make('check_out')->date()->sortable(),
                TextColumn::make('currency')->sortable(),
                TextColumn::make('locale')->sortable(),
                TextColumn::make('correlation_id')->searchable()->copyable(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('currency')->options(fn (): array => SearchSession::query()->distinct()->pluck('currency', 'currency')->all()),
                SelectFilter::make('locale')->options(['ar' => 'Arabic', 'en' => 'English']),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', SearchSession::class);
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
        return ['index' => ListSearchSessions::route('/')];
    }
}
