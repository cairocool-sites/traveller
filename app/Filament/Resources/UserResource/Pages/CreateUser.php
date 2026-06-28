<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;

class CreateUser extends CreateRecord
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
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roles = array_values($data['roles'] ?? []);
        unset($data['roles']);

        $data['email_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof User && Gate::allows('assignRoles', $this->record)) {
            $this->record->syncRoles($this->roles);
        }
    }
}
