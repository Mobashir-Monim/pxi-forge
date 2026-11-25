<?php

namespace App\Domains\Authorization\Actions\Command;

use App\Domains\Authorization\Enums\SystemRole;
use App\Models\User;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/** The way in for the first operator: nobody can grant the super admin role in the UI until someone holds it. */
final class MakeSuperAdmin
{
    use AsAction;

    public string $commandSignature = 'authorization:super-admin {email}';

    public string $commandDescription = 'Give a user the Super Admin role';

    public function handle(User $user): void
    {
        $user->assignRole(SystemRole::SuperAdmin->value);
    }

    public function asCommand(Command $command): int
    {
        $user = User::query()->where('email', $command->argument('email'))->first();

        if ($user === null) {
            $command->error('No user has that email.');

            return Command::FAILURE;
        }

        $this->handle($user);
        $command->info("{$user->email} is now a Super Admin.");

        return Command::SUCCESS;
    }
}
