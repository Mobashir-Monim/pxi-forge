<?php

use App\Domains\Authorization\Enums\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('seeds one demo user per role and reseeding is safe', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(3);

    $superAdmin = User::query()->where('email', 'superadmin@pxi.test')->sole();
    $admin = User::query()->where('email', 'admin@pxi.test')->sole();
    $member = User::query()->where('email', 'member@pxi.test')->sole();

    expect($superAdmin->isSuperAdmin())->toBeTrue()
        ->and($admin->getRoleNames()->all())->toBe(['Admin'])
        ->and($admin->can(Permission::RolesUpdate->value))->toBeTrue()
        ->and($admin->can(Permission::RolesDelete->value))->toBeFalse()
        ->and($admin->can(Permission::UsersAssignRoles->value))->toBeFalse()
        ->and($member->getRoleNames()->all())->toBe(['Member']);
});
