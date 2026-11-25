<?php

namespace App\Domains\Authorization\Actions\Query;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

/** `/users`, every user with the roles they hold, and the roles the viewer may give or take. */
final class ListUsers
{
    use AsController;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::UsersView->value);
    }

    public function asController(ActionRequest $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $users = User::query()->with('roles')->orderBy('name')->paginate(25)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_ids' => $user->roles->pluck('id')->all(),
                'is_self' => $user->is($actor),
            ]);

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => Role::query()->with('permissions')->orderBy('name')->get()->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'within_reach' => $role->isWithinReachOf($actor),
            ])->all(),
            'can' => ['assign_roles' => $actor->can(Permission::UsersAssignRoles->value)],
        ]);
    }
}
