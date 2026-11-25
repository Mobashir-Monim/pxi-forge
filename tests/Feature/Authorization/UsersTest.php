<?php

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Enums\SystemRole;
use App\Domains\Authorization\Models\Role;
use App\Models\User;

it('forbids the users list and role assignment without their permissions', function () {
    $actor = userWith();
    $target = User::factory()->create();

    $this->actingAs($actor)->get(route('users.index'))->assertForbidden();
    $this->actingAs($actor)->put(route('users.roles.update', $target), ['roles' => []])->assertForbidden();
});

it('lists users for anyone who may view them', function () {
    $this->actingAs(userWith(Permission::UsersView))->get(route('users.index'))->assertOk();
});

it('assigns roles within the actor reach', function () {
    // Member grants the generating permissions, so the actor needs them too to hand it out.
    $actor = userWith(Permission::UsersAssignRoles, Permission::UsersView, ...Permission::MEMBER_DEFAULTS);
    $target = User::factory()->create();
    $member = Role::findByName(SystemRole::Member->value);

    $this->actingAs($actor)->put(route('users.roles.update', $target), ['roles' => [$member->id]])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->hasRole($member))->toBeTrue();
});

it('never lets users change their own roles', function () {
    $actor = userWith(Permission::UsersAssignRoles);

    $this->actingAs($actor)->put(route('users.roles.update', $actor), ['roles' => []])->assertForbidden();
});

it('refuses to give a role stronger than the actor', function () {
    $actor = userWith(Permission::UsersAssignRoles);
    $target = User::factory()->create();

    $this->actingAs($actor)->put(route('users.roles.update', $target), [
        'roles' => [Role::findByName(SystemRole::SuperAdmin->value)->id],
    ])->assertSessionHasErrors('roles');

    expect($target->fresh()->isSuperAdmin())->toBeFalse();
});

it('refuses to take a role stronger than the actor', function () {
    $actor = userWith(Permission::UsersAssignRoles);
    $target = superAdmin();

    $this->actingAs($actor)->put(route('users.roles.update', $target), ['roles' => []])
        ->assertSessionHasErrors('roles');

    expect($target->fresh()->isSuperAdmin())->toBeTrue();
});

it('lets a super admin hand out super admin', function () {
    $target = User::factory()->create();

    $this->actingAs(superAdmin())->put(route('users.roles.update', $target), [
        'roles' => [Role::findByName(SystemRole::SuperAdmin->value)->id],
    ])->assertSessionHasNoErrors();

    expect($target->fresh()->isSuperAdmin())->toBeTrue();
});

it('makes a super admin from the console', function () {
    $user = User::factory()->create();

    $this->artisan('authorization:super-admin', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->isSuperAdmin())->toBeTrue();
});
