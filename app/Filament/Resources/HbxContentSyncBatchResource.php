<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HbxContentSyncBatchResource\Pages\ListHbxContentSyncBatches;
use App\Models\HbxContentSyncBatch;
use App\Models\Supplier;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class HbxContentSyncBatchResource extends Resource
{
    protected static ?string $model = HbxContentSyncBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('admin.hbx_content_batches.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.hbx_content_batches.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('admin.hbx_content_batches.fields.created_at'))->dateTime()->sortable(),
                TextColumn::make('resource')->label(__('admin.hbx_content_batches.fields.resource'))->badge()->searchable()->sortable(),
                TextColumn::make('mode')->label(__('admin.hbx_content_batches.fields.mode'))->badge()->sortable(),
                TextColumn::make('status')->label(__('admin.hbx_content_batches.fields.status'))->badge()->sortable(),
                TextColumn::make('country_code')->label(__('admin.hbx_content_batches.fields.country_code'))->sortable()->toggleable(),
                TextColumn::make('language')->label(__('admin.hbx_content_batches.fields.language'))->sortable()->toggleable(),
                TextColumn::make('processed_count')->label(__('admin.hbx_content_batches.fields.processed_count'))->numeric()->sortable(),
                TextColumn::make('stored_count')->label(__('admin.hbx_content_batches.fields.stored_count'))->numeric()->sortable(),
                IconColumn::make('dry_run')->label(__('admin.hbx_content_batches.fields.dry_run'))->boolean()->sortable(),
                IconColumn::make('queued')->label(__('admin.hbx_content_batches.fields.queued'))->boolean()->sortable(),
                TextColumn::make('finished_at')->label(__('admin.hbx_content_batches.fields.finished_at'))->dateTime()->sortable()->toggleable(),
                TextColumn::make('error_message')->label(__('admin.hbx_content_batches.fields.error_message'))->limit(80)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('resource')->label(__('admin.hbx_content_batches.fields.resource'))->options(fn (): array => HbxContentSyncBatch::query()->orderBy('resource')->pluck('resource', 'resource')->all()),
                SelectFilter::make('status')->label(__('admin.hbx_content_batches.fields.status'))->options([
                    'pending' => 'Pending',
                    'running' => 'Running',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                ]),
                TernaryFilter::make('dry_run')->label(__('admin.hbx_content_batches.fields.dry_run')),
            ])
            ->defaultSort('created_at', 'desc');
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

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListHbxContentSyncBatches::route('/')];
    }
}
