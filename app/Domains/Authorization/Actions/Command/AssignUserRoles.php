<?php

namespace App\Domains\Authorization\Actions\Command;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Set the whole list of roles a user holds. Nobody changes their own roles, and every role given or taken must be
 * within the actor's reach (see Role::isWithinReachOf). A role out of reach that the user already holds must stay in
 * the list, since dropping it would be taking it.
 */
final class AssignUserRoles
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor->can(Permission::UsersAssignRoles->value) && ! $actor->is($request->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Absent means none: a form with every box cleared posts no list at all.
            'roles' => ['nullable', 'array'],
            'roles.*' => ['ulid', 'distinct', 'exists:roles,id'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        /** @var User $actor */
        $actor = $request->user();
        /** @var User $user */
        $user = $request->route('user');

        $current = $user->roles()->pluck('id')->all();
        $requested = array_map(strval(...), $request->input('roles') ?? []);
        $changed = array_merge(array_diff($requested, $current), array_diff($current, $requested));

        $outOfReach = Role::query()->with('permissions')->whereKey($changed)->get()
            ->reject(fn (Role $role): bool => $role->isWithinReachOf($actor));

        if ($outOfReach->isNotEmpty()) {
            $validator->errors()->add('roles', 'You can\'t give or take these roles: '.$outOfReach->pluck('name')->join(', ').'.');
        }
    }

    /**
     * @param  list<string>  $roleIds
     */
    public function handle(User $user, array $roleIds): void
    {
        $user->syncRoles(Role::query()->whereKey($roleIds)->get());
    }

    public function asController(ActionRequest $request, User $user): RedirectResponse
    {
        $this->handle($user, array_values(array_map(strval(...), $request->validated('roles') ?? [])));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Roles for {$user->name} saved."]);

        return back();
    }
}
