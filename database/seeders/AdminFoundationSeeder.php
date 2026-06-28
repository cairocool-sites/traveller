<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Admin\Access;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminFoundationSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Access::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Access::ROLES as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(
                Permission::query()
                    ->whereIn('name', Access::ROLE_PERMISSIONS[$roleName] ?? ['access_admin'])
                    ->where('guard_name', 'web')
                    ->get(),
            );
        }

        $this->createDevelopmentSuperAdmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createDevelopmentSuperAdmin(): void
    {
        $name = env('ADMIN_NAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($name) || blank($email) || blank($password)) {
            $this->command?->warn('Skipping initial super admin creation. Set ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD in .env to create one.');

            return;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            $this->command?->warn('Skipping initial super admin creation. ADMIN_* values are incomplete or invalid.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_active' => true,
                'preferred_locale' => 'ar',
            ],
        );

        if (! $user->is_active || ! $user->hasVerifiedEmail()) {
            $user->forceFill([
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        $user->assignRole('super_admin');
        $this->command?->info('Initial super admin is ready. Password was not logged.');
    }
}
