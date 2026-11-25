<?php

namespace App\Domains\Authorization\Actions\Query;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

/**
 * The role form. `/roles/create` opens it blank for a new role, `/roles/{role}` opens an existing one, read-only for
 * anyone who may view roles but not edit this one.
 */
final class ShowRole
{
    use AsController;

    public function authorize(ActionRequest $request): bool
    {
        $permission = $request->route('role') === null ? Permission::RolesCreate : Permission::RolesView;

        return $request->user()->can($permission->value);
    }

    public function asController(ActionRequest $request, ?Role $role = null): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('roles/Form', [
            'role' => $role === null ? null : [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->isSystem(),
                'is_super_admin' => $role->isSuperAdmin(),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
            'catalog' => Permission::catalog(),
            // Permissions the actor holds, the only ones they may tick.
            'grantable' => $actor->permissionNames(),
            'can' => [
                'update' => $role === null || $role->isEditableBy($actor),
                'delete' => $role?->isDeletableBy($actor) ?? false,
            ],
        ]);
    }
}
