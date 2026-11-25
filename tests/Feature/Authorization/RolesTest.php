<?php

use App\Domains\Authorization\Actions\Command\SyncAuthorizationCatalog;
use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Enums\SystemRole;
use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission as PermissionModel;

function customRole(Permission ...$permissions): Role
{
    $role = Role::create(['name' => 'Editors', 'guard_name' => 'web']);
    $role->syncPermissions(array_map(fn (Permission $p): string => $p->value, $permissions));

    return $role;
}

it('forbids every role route without its permission', function (string $method, Closure $url) {
    $role = customRole();

    $this->actingAs(userWith())->{$method}($url($role), ['name' => 'X'])->assertForbidden();
})->with([
    'index' => ['get', fn () => route('roles.index')],
    'create page' => ['get', fn () => route('roles.create')],
    'show' => ['get', fn (Role $role) => route('roles.show', $role)],
    'store' => ['post', fn () => route('roles.store')],
    'update' => ['put', fn (Role $role) => route('roles.update', $role)],
    'destroy' => ['delete', fn (Role $role) => route('roles.destroy', $role)],
]);

it('lets each permission open only its own route', function () {
    $viewer = userWith(Permission::RolesView);

    $this->actingAs($viewer)->get(route('roles.index'))->assertOk();
    $this->actingAs($viewer)->get(route('roles.show', customRole()))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.update', false)->where('can.delete', false));
    $this->actingAs($viewer)->get(route('roles.create'))->assertForbidden();
});

it('creates a role with the permissions the actor holds', function () {
    $actor = userWith(Permission::RolesCreate, Permission::RolesView);

    $this->actingAs($actor)->post(route('roles.store'), [
        'name' => 'Auditors',
        'permissions' => [Permission::RolesView->value],
    ])->assertRedirect();

    expect(Role::findByName('Auditors')->permissions->pluck('name')->all())->toBe([Permission::RolesView->value]);
});

it('refuses to grant a permission the actor does not hold', function () {
    $actor = userWith(Permission::RolesCreate);

    $this->actingAs($actor)->post(route('roles.store'), [
        'name' => 'Escalated',
        'permissions' => [Permission::UsersAssignRoles->value],
    ])->assertSessionHasErrors('permissions.0');

    expect(Role::query()->where('name', 'Escalated')->exists())->toBeFalse();
});

it('rejects unknown permissions', function () {
    $this->actingAs(superAdmin())->post(route('roles.store'), [
        'name' => 'Odd',
        'permissions' => ['nuke.everything'],
    ])->assertSessionHasErrors('permissions.0');
});

it('updates a role name and replaces its permissions', function () {
    $role = customRole(Permission::RolesView);

    $this->actingAs(superAdmin())->put(route('roles.update', $role), [
        'name' => 'Reviewers',
        'permissions' => [Permission::UsersView->value],
    ])->assertRedirect(route('roles.show', $role));

    $role->refresh();
    expect($role->name)->toBe('Reviewers')
        ->and($role->permissions->pluck('name')->all())->toBe([Permission::UsersView->value]);
});

it('never edits the super admin role', function () {
    $role = Role::findByName(SystemRole::SuperAdmin->value);

    $this->actingAs(superAdmin())->put(route('roles.update', $role), ['name' => $role->name])->assertForbidden();
});

it('keeps system role names', function () {
    $member = Role::findByName(SystemRole::Member->value);

    $this->actingAs(superAdmin())->put(route('roles.update', $member), ['name' => 'Renamed'])
        ->assertSessionHasErrors('name');
});

it('refuses to edit or delete a role stronger than the actor', function () {
    $actor = userWith(Permission::RolesUpdate, Permission::RolesDelete);
    $stronger = customRole(Permission::UsersAssignRoles);

    $this->actingAs($actor)->put(route('roles.update', $stronger), ['name' => 'Weakened'])->assertForbidden();
    $this->actingAs($actor)->delete(route('roles.destroy', $stronger))->assertForbidden();
});

it('deletes a custom role and takes it off its holders', function () {
    $role = customRole(Permission::UsersView);
    $holder = User::factory()->create()->assignRole($role);

    $this->actingAs(superAdmin())->delete(route('roles.destroy', $role))->assertRedirect(route('roles.index'));

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse()
        ->and($holder->fresh()->can(Permission::UsersView->value))->toBeFalse();
});

it('never deletes a system role', function () {
    $this->actingAs(superAdmin())->delete(route('roles.destroy', Role::findByName(SystemRole::Member->value)))
        ->assertForbidden();
});

it('shares the user permissions with the frontend', function () {
    $this->actingAs(userWith(Permission::RolesView))->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', [Permission::RolesView->value]));

    $this->actingAs(superAdmin())->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', array_column(Permission::cases(), 'value')));
});

it('syncs the catalog from the enum and drops stale permissions', function () {
    PermissionModel::findOrCreate('legacy.thing', 'web');

    SyncAuthorizationCatalog::run();

    expect(PermissionModel::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::cases())->pluck('value')->sort()->values()->all());
});
