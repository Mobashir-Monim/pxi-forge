<?php

namespace App\Domains\Authorization\Actions\Command;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateRole
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::RolesCreate->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(ActionRequest $request): array
    {
        /** @var User $actor */
        $actor = $request->user();

        return [
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
            ...Role::permissionRules($actor),
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    public function handle(string $name, array $permissions): Role
    {
        $role = Role::query()->create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return $role;
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $role = $this->handle($request->validated('name'), $request->validated('permissions') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} created."]);

        return to_route('roles.show', $role);
    }
}
