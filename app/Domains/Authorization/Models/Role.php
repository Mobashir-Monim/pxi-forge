<?php

namespace App\Domains\Authorization\Models;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Authorization\Enums\SystemRole;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $id
 * @property string $name
 * @property string $guard_name
 */
class Role extends SpatieRole
{
    use HasUlids;

    public function isSuperAdmin(): bool
    {
        return $this->name === SystemRole::SuperAdmin->value;
    }

    public function isSystem(): bool
    {
        return SystemRole::tryFrom($this->name) !== null;
    }

    /**
     * Whether the actor may edit, delete, give or take this role. Only a super admin touches the super admin role, and
     * anyone else only touches roles that grant nothing they don't hold themselves, so nobody can raise anyone above
     * their own level, or strip a role stronger than theirs.
     */
    public function isWithinReachOf(User $actor): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return ! $this->isSuperAdmin() && $this->permissions->every(fn ($permission): bool => $actor->can($permission->name));
    }

    public function isEditableBy(User $actor): bool
    {
        return $actor->can(Permission::RolesUpdate->value) && ! $this->isSuperAdmin() && $this->isWithinReachOf($actor);
    }

    public function isDeletableBy(User $actor): bool
    {
        return $actor->can(Permission::RolesDelete->value) && ! $this->isSystem() && $this->isWithinReachOf($actor);
    }

    /**
     * Validation for the permission list a role form posts: known permissions only, and only ones the actor holds.
     *
     * @return array<string, mixed>
     */
    public static function permissionRules(User $actor): array
    {
        return [
            // Absent means none: a form with every box cleared posts no list at all.
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::enum(Permission::class),
                static function (string $attribute, mixed $value, Closure $fail) use ($actor): void {
                    if (is_string($value) && ! $actor->can($value)) {
                        $fail('You can only grant permissions you hold yourself.');
                    }
                },
            ],
        ];
    }
}
