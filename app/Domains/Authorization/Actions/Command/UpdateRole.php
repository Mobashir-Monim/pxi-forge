<?php

namespace App\Domains\Authorization\Actions\Command;

use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/** Rename a role and set its whole permission list. System roles keep their name. */
final class UpdateRole
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->role($request)->isEditableBy($actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(ActionRequest $request): array
    {
        /** @var User $actor */
        $actor = $request->user();
        $role = $this->role($request);

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('roles', 'name')->ignore($role->id),
                ...($role->isSystem() ? [Rule::in([$role->name])] : []),
            ],
            ...Role::permissionRules($actor),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return ['name.in' => 'System roles can\'t be renamed.'];
    }

    /**
     * @param  list<string>  $permissions
     */
    public function handle(Role $role, string $name, array $permissions): Role
    {
        $role->update(['name' => $name]);
        $role->syncPermissions($permissions);

        return $role;
    }

    public function asController(ActionRequest $request, Role $role): RedirectResponse
    {
        $this->handle($role, $request->validated('name'), $request->validated('permissions') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} saved."]);

        return to_route('roles.show', $role);
    }

    private function role(ActionRequest $request): Role
    {
        /** @var Role */
        return $request->route('role');
    }
}
