<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManualPaymentMethodResource\Pages\ListManualPaymentMethods;
use App\Models\ManualPaymentMethod;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ManualPaymentMethodResource extends Resource
{
    protected static ?string $model = ManualPaymentMethod::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_en')->required(),
            TextInput::make('name_ar')->required(),
            TextInput::make('instructions_en')->required(),
            TextInput::make('instructions_ar')->required(),
            Toggle::make('supports_attachment'),
            Toggle::make('requires_reference'),
            Toggle::make('is_active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable(),
            TextColumn::make('name_en')->searchable(),
            IconColumn::make('supports_attachment')->boolean(),
            IconColumn::make('requires_reference')->boolean(),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', ManualPaymentMethod::class);
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListManualPaymentMethods::route('/')];
    }
}
