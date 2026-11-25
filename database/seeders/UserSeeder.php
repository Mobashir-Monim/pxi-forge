<?php

namespace Database\Seeders;

use App\Domains\Authorization\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo accounts, one per role, all with the password "password". Never run in production. */
class UserSeeder extends Seeder
{
    private const array USERS = [
        'superadmin@pxi.test' => ['Super Admin', SystemRole::SuperAdmin->value],
        'admin@pxi.test' => ['Admin', 'Admin'],
        'member@pxi.test' => ['Member', SystemRole::Member->value],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipped demo users in production.');

            return;
        }

        foreach (self::USERS as $email => [$name, $role]) {
            $user = User::query()->where('email', $email)->first()
                ?? User::factory()->create(['email' => $email, 'name' => $name]);

            $user->syncRoles([$role]);
        }
    }
}
