<?php

namespace Database\Seeders;

use App\Domains\Authorization\Actions\Command\SyncAuthorizationCatalog;
use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Models\Role;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    /** Kept from Admin so only a super admin can promote people or delete roles. */
    private const array ADMIN_EXCLUDED = [Permission::RolesDelete, Permission::UsersAssignRoles];

    public function run(): void
    {
        SyncAuthorizationCatalog::run();

        // Admin is an ordinary role, editable in the UI, so its permissions are only set when it's first created and a
        // reseed never undoes edits made since.
        $admin = Role::findOrCreate('Admin', 'web');

        if ($admin->wasRecentlyCreated) {
            $admin->syncPermissions(array_map(
                fn (Permission $permission): string => $permission->value,
                array_filter(Permission::cases(), fn (Permission $permission): bool => ! in_array($permission, self::ADMIN_EXCLUDED, true)),
            ));
        }
    }
}
