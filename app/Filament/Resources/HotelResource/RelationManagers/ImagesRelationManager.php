<?php

namespace App\Filament\Resources\HotelResource\RelationManagers;

use App\Enums\HotelImageType;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('admin.hotels.relations.images');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label(__('admin.hotels.fields.image'))
                ->disk('public')
                ->directory('hotels')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120)
                ->required(),
            Select::make('image_type')->label(__('admin.hotels.fields.image_type'))->options(HotelImageType::options())->default(HotelImageType::Other->value)->required(),
            TextInput::make('alt_text')->label(__('admin.hotels.fields.alt_text'))->maxLength(255),
            TextInput::make('caption')->label(__('admin.hotels.fields.caption'))->maxLength(255),
            TextInput::make('sort_order')->label(__('admin.common_fields.sort_order'))->numeric()->default(0)->minValue(0),
            Toggle::make('is_primary')->label(__('admin.hotels.fields.is_primary'))->default(false),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
            TextInput::make('uploaded_by')->hidden()->default(fn (): ?int => Auth::id()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('path')->label(__('admin.hotels.fields.path'))->searchable(),
                TextColumn::make('image_type')->label(__('admin.hotels.fields.image_type'))->formatStateUsing(fn (HotelImageType $state): string => __('admin.hotels.image_types.'.$state->value)),
                IconColumn::make('is_primary')->label(__('admin.hotels.fields.is_primary'))->boolean(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean(),
                TextColumn::make('sort_order')->label(__('admin.common_fields.sort_order'))->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->visible(fn (): bool => Gate::allows('manageMedia', $this->getOwnerRecord())),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => Gate::allows('manageMedia', $this->getOwnerRecord())),
            ])
            ->defaultSort('sort_order');
    }
}
