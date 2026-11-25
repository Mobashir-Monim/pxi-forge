<?php

namespace App\Domains\Authorization\Actions\Query;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Models\Role;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

/** `/roles`, every role with how many permissions it grants and how many users hold it. */
final class ListRoles
{
    use AsController;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::RolesView->value);
    }

    public function asController(): Response
    {
        $roles = Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get();

        return Inertia::render('roles/Index', [
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->isSystem(),
                'is_super_admin' => $role->isSuperAdmin(),
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
            ])->all(),
        ]);
    }
}
