<?php

namespace App\Domains\Authorization\Actions\Command;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Enums\SystemRole;
use App\Domains\Authorization\Models\Role;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes the Permission enum to the database and makes sure the system roles exist. Permissions removed from the enum
 * are deleted, which takes them off every role. Safe to run on every deploy.
 */
final class SyncAuthorizationCatalog
{
    use AsAction;

    public string $commandSignature = 'authorization:sync';

    public string $commandDescription = 'Sync permissions from the Permission enum and create the system roles';

    public function handle(): void
    {
        $names = array_column(Permission::cases(), 'value');

        foreach ($names as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        PermissionModel::query()->whereNotIn('name', $names)->delete();

        foreach (SystemRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Permissions and system roles are in sync.');
    }
}
