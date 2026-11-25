<?php

namespace App\Domains\Authorization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string $id
 * @property string $name
 * @property string $guard_name
 */
class Permission extends SpatiePermission
{
    use HasUlids;
}
