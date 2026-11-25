<?php

namespace App\Domains\Authorization\Enums;

/** Roles the catalog sync always creates. They can't be renamed or deleted. */
enum SystemRole: string
{
    /** Passes every check through Gate::before, so it holds no permission rows and can't be edited. */
    case SuperAdmin = 'Super Admin';

    /** Given to every new user at registration. Its permissions are editable. */
    case Member = 'Member';
}
