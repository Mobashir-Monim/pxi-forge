<?php

use App\Domains\Authorization\Actions\Command\AssignUserRoles;
use App\Domains\Authorization\Actions\Command\CreateRole;
use App\Domains\Authorization\Actions\Command\DeleteRole;
use App\Domains\Authorization\Actions\Command\UpdateRole;
use App\Domains\Authorization\Actions\Query\ListRoles;
use App\Domains\Authorization\Actions\Query\ListUsers;
use App\Domains\Authorization\Actions\Query\ShowRole;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', ListRoles::class)->name('index');
        Route::get('create', ShowRole::class)->name('create');
        Route::post('/', CreateRole::class)->name('store');
        Route::get('{role}', ShowRole::class)->name('show');
        Route::put('{role}', UpdateRole::class)->name('update');
        Route::delete('{role}', DeleteRole::class)->name('destroy');
    });

    Route::get('users', ListUsers::class)->name('users.index');
    Route::put('users/{user}/roles', AssignUserRoles::class)->name('users.roles.update');
});

require __DIR__.'/settings.php';
