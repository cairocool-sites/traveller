<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @var array<int, string>
     */
    protected array $roles = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->getRecord() instanceof User) {
            $data['roles'] = $this->getRecord()->roles->pluck('name')->all();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->roles = array_values($data['roles'] ?? []);
        unset($data['roles']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (array_key_exists('is_active', $data) && $record instanceof User && $record->is_active !== $data['is_active']) {
            abort_unless(Gate::allows('deactivate', $record), 403);
        }

        return parent::handleRecordUpdate($record, $data);
    }

    protected function afterSave(): void
    {
        if ($this->record instanceof User && Gate::allows('assignRoles', $this->record)) {
            $this->record->syncRoles($this->roles);
        }
    }
}
