<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Support\Admin\Access;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('admin.users.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.users.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.users.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.users.fields.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('admin.users.fields.email'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('password')
                ->label(__('admin.users.fields.password'))
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->minLength(12)
                ->maxLength(255),
            Toggle::make('is_active')
                ->label(__('admin.users.fields.is_active'))
                ->default(true)
                ->disabled(fn (?User $record): bool => $record instanceof User && Gate::denies('deactivate', $record)),
            Select::make('preferred_locale')
                ->label(__('admin.users.fields.preferred_locale'))
                ->options([
                    'ar' => __('admin.locales.ar'),
                    'en' => __('admin.locales.en'),
                ])
                ->default('ar')
                ->required(),
            Select::make('roles')
                ->label(__('admin.users.fields.roles'))
                ->multiple()
                ->options(fn (): array => static::roleOptions())
                ->disabled(fn (?User $record): bool => $record instanceof User && Gate::denies('assignRoles', $record))
                ->dehydrated(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.users.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('admin.users.fields.email'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('admin.users.fields.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('preferred_locale')
                    ->label(__('admin.users.fields.preferred_locale'))
                    ->formatStateUsing(fn (string $state): string => __('admin.locales.'.$state))
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('admin.users.fields.roles'))
                    ->badge()
                    ->separator(','),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.users.fields.is_active')),
                SelectFilter::make('roles')
                    ->label(__('admin.users.fields.roles'))
                    ->relationship('roles', 'name'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggle_active')
                    ->label(fn (User $record): string => $record->is_active ? __('admin.users.actions.deactivate') : __('admin.users.actions.activate'))
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => Gate::allows('deactivate', $record))
                    ->action(fn (User $record): bool => tap($record)->forceFill(['is_active' => ! $record->is_active])->save()),
            ])
            ->defaultSort('name');
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        $currentRole = Auth::user()?->roles->pluck('name')->first();
        $assignableRoles = Access::assignableRolesFor($currentRole);

        return Role::query()
            ->whereIn('name', $assignableRoles)
            ->orderBy('name')
            ->pluck('name', 'name')
            ->mapWithKeys(fn (string $name, string $key): array => [$key => __('admin.roles.names.'.$name)])
            ->all();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', User::class);
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create', User::class);
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
