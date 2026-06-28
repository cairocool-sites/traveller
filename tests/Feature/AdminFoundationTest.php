<?php

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\Admin\Access;
use Database\Seeders\AdminFoundationSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Pest\Expectation;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminFoundationSeeder::class);
});

it('redirects guests to the admin login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('allows active verified users with admin permission to access the admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('operations_admin');

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('blocks inactive admin users from the admin panel', function () {
    $user = User::factory()->create(['is_active' => false]);
    $user->assignRole('operations_admin');

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('blocks users without admin permission from the admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('creates the expected roles and permissions idempotently', function () {
    $this->seed(AdminFoundationSeeder::class);

    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe(collect(Access::ROLES)->sort()->values()->all())
        ->and(Permission::query()->pluck('name')->sort()->values()->all())->toBe(collect(Access::PERMISSIONS)->sort()->values()->all());
});

it('grants super admins all permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect(Access::PERMISSIONS)
        ->each(fn (Expectation $permission) => $permission->and($user->can($permission->value))->toBeTrue());
});

it('allows general managers to create users and assign non super admin roles', function () {
    $manager = User::factory()->create();
    $target = User::factory()->create();
    $manager->assignRole('general_manager');

    $this->actingAs($manager);

    expect(Gate::allows('create', User::class))->toBeTrue()
        ->and(Gate::allows('assignRoles', $target))->toBeTrue()
        ->and(UserResource::roleOptions())->not->toHaveKey('super_admin');
});

it('prevents non super admins from updating super admin users', function () {
    $manager = User::factory()->create();
    $manager->assignRole('general_manager');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $this->actingAs($manager);

    expect(Gate::denies('update', $superAdmin))->toBeTrue()
        ->and(Gate::denies('assignRoles', $superAdmin))->toBeTrue();
});

it('prevents deactivating the final active super admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $this->actingAs($superAdmin);

    expect(Gate::denies('deactivate', $superAdmin))->toBeTrue();
});
