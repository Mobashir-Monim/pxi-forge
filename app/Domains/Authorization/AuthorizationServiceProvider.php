<?php

namespace App\Domains\Authorization;

use App\Domains\Authorization\Actions\Command\MakeSuperAdmin;
use App\Domains\Authorization\Actions\Command\SyncAuthorizationCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\Facades\Actions;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The super admin passes every check. Returning null lets everyone else fall through to their permissions.
        Gate::before(static fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        if ($this->app->runningInConsole()) {
            Actions::registerCommandsForAction(SyncAuthorizationCatalog::class);
            Actions::registerCommandsForAction(MakeSuperAdmin::class);
        }
    }
}
