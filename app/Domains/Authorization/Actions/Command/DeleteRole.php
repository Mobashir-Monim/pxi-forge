<?php

namespace App\Domains\Authorization\Actions\Command;

use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/** Delete a custom role. Users holding it lose it, and with it every permission it granted them. */
final class DeleteRole
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var Role $role */
        $role = $request->route('role');

        return $role->isDeletableBy($actor);
    }

    public function handle(Role $role): void
    {
        $role->delete();
    }

    public function asController(Role $role): RedirectResponse
    {
        $this->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} deleted."]);

        return to_route('roles.index');
    }
}
