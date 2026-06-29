<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CredentialsRelationManager extends RelationManager
{
    protected static string $relationship = 'credentials';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('credential_key')->label(__('admin.suppliers.credentials.key'))->required()->maxLength(128),
            TextInput::make('encrypted_value')
                ->label(__('admin.suppliers.credentials.value'))
                ->password()
                ->revealable(false)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),
            Toggle::make('is_secret')->label(__('admin.suppliers.credentials.is_secret'))->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('credential_key')->label(__('admin.suppliers.credentials.key'))->searchable(),
                TextColumn::make('masked')->label(__('admin.suppliers.credentials.value'))->state('[REDACTED]'),
                IconColumn::make('is_secret')->label(__('admin.suppliers.credentials.is_secret'))->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->headerActions([CreateAction::make()->visible(fn (): bool => Gate::allows('manageCredentials', $this->getOwnerRecord()))])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => Gate::allows('manageCredentials', $this->getOwnerRecord())),
                DeleteAction::make()->visible(fn (): bool => Gate::allows('manageCredentials', $this->getOwnerRecord())),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('manageCredentials', $ownerRecord);
    }
}
