<?php

namespace App\Filament\Resources\HotelResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PolicyRelationManager extends RelationManager
{
    protected static string $relationship = 'policy';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('admin.hotels.relations.policy');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('check_in_from')->label(__('admin.hotels.fields.check_in_from')),
            TextInput::make('check_in_until')->label(__('admin.hotels.fields.check_in_until')),
            TextInput::make('check_out_from')->label(__('admin.hotels.fields.check_out_from')),
            TextInput::make('check_out_until')->label(__('admin.hotels.fields.check_out_until')),
            Textarea::make('children_policy')->label(__('admin.hotels.fields.children_policy'))->rows(3),
            Textarea::make('extra_bed_policy')->label(__('admin.hotels.fields.extra_bed_policy'))->rows(3),
            Textarea::make('pet_policy')->label(__('admin.hotels.fields.pet_policy'))->rows(3),
            Textarea::make('smoking_policy')->label(__('admin.hotels.fields.smoking_policy'))->rows(3),
            Textarea::make('cancellation_notes')->label(__('admin.hotels.fields.cancellation_notes'))->rows(3),
            Textarea::make('important_information')->label(__('admin.hotels.fields.important_information'))->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('check_in_from')->label(__('admin.hotels.fields.check_in_from')),
                TextColumn::make('check_out_until')->label(__('admin.hotels.fields.check_out_until')),
                TextColumn::make('updated_at')->label(__('admin.hotels.fields.updated_at'))->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()->visible(fn (): bool => Gate::allows('managePolicies', $this->getOwnerRecord()) && $this->getOwnerRecord()->policy()->doesntExist()),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => Gate::allows('managePolicies', $this->getOwnerRecord())),
            ]);
    }
}
