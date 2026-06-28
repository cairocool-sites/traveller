<?php

namespace App\Filament\Resources\HotelResource\RelationManagers;

use App\Enums\HotelContactType;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('admin.hotels.relations.contacts');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('contact_type')->label(__('admin.hotels.fields.contact_type'))->options(HotelContactType::options())->required(),
            TextInput::make('department')->label(__('admin.hotels.fields.department'))->maxLength(255),
            TextInput::make('contact_name')->label(__('admin.hotels.fields.contact_name'))->maxLength(255),
            TextInput::make('phone')->label(__('admin.hotels.fields.phone'))->maxLength(64),
            TextInput::make('mobile')->label(__('admin.hotels.fields.mobile'))->maxLength(64),
            TextInput::make('email')->label(__('admin.hotels.fields.email'))->email()->maxLength(255),
            Textarea::make('notes')->label(__('admin.hotels.fields.notes'))->rows(3),
            Toggle::make('is_primary')->label(__('admin.hotels.fields.is_primary'))->default(false),
            Toggle::make('is_active')->label(__('admin.common_fields.is_active'))->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact_type')->label(__('admin.hotels.fields.contact_type'))->formatStateUsing(fn (HotelContactType $state): string => __('admin.hotels.contact_types.'.$state->value)),
                TextColumn::make('department')->label(__('admin.hotels.fields.department')),
                TextColumn::make('contact_name')->label(__('admin.hotels.fields.contact_name')),
                TextColumn::make('email')->label(__('admin.hotels.fields.email')),
                IconColumn::make('is_primary')->label(__('admin.hotels.fields.is_primary'))->boolean(),
                IconColumn::make('is_active')->label(__('admin.common_fields.is_active'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord())),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord())),
            ]);
    }
}
