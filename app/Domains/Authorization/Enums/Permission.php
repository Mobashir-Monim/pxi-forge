<?php

namespace App\Domains\Authorization\Enums;

/**
 * Every capability the app gates on, named `<area>.<action>`. Each action in an area is its own permission, so a custom
 * role can hold any single button without the rest of its area. New features add their cases here, then run
 * `php artisan authorization:sync` to write them to the database.
 */
enum Permission: string
{
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    case UsersView = 'users.view';
    case UsersAssignRoles = 'users.assign-roles';

    case ChatsView = 'chats.view';

    case GenerationsCreate = 'generations.create';
    case GenerationsRetry = 'generations.retry';

    case ReferencesUpload = 'references.upload';

    /** What every member can do from the start. Seeded once, editable after. */
    public const array MEMBER_DEFAULTS = [self::ChatsView, self::GenerationsCreate, self::GenerationsRetry, self::ReferencesUpload];

    /** The heading this permission is listed under on the role form. */
    public function group(): string
    {
        return ucfirst(explode('.', $this->value)[0]);
    }

    public function label(): string
    {
        return match ($this) {
            self::RolesView => 'View roles',
            self::RolesCreate => 'Create roles',
            self::RolesUpdate => 'Edit roles',
            self::RolesDelete => 'Delete roles',
            self::UsersView => 'View users',
            self::UsersAssignRoles => 'Assign roles to users',
            self::ChatsView => 'View own chats',
            self::GenerationsCreate => 'Generate images',
            self::GenerationsRetry => 'Retry failed generations',
            self::ReferencesUpload => 'Upload reference images',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RolesView => 'See the list of roles and what each one allows.',
            self::RolesCreate => 'Make new custom roles.',
            self::RolesUpdate => 'Rename custom roles and change their permissions.',
            self::RolesDelete => 'Delete custom roles. Users holding one lose it.',
            self::UsersView => 'See the list of users and their roles.',
            self::UsersAssignRoles => 'Give roles to other users or take them away.',
            self::ChatsView => 'Open their own chats and see each generation in them.',
            self::GenerationsCreate => 'Send prompts to the image generator and see cost estimates. Each image is charged to them.',
            self::GenerationsRetry => 'Send a failed generation again with the same settings.',
            self::ReferencesUpload => 'Upload images from their device to use as references.',
        };
    }

    /**
     * The catalog as the role form shows it, grouped by area.
     *
     * @return array<string, list<array{name: string, label: string, description: string}>>
     */
    public static function catalog(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = [
                'name' => $permission->value,
                'label' => $permission->label(),
                'description' => $permission->description(),
            ];
        }

        return $groups;
    }
}
