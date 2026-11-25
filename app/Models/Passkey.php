<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Passkeys\Passkey as BasePasskey;

/** The package's passkey with a ULID key, like every other table. */
class Passkey extends BasePasskey
{
    use HasUlids;
}
