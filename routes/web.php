<?php

use App\Domains\Authorization\Actions\Command\AssignUserRoles;
use App\Domains\Authorization\Actions\Command\CreateRole;
use App\Domains\Authorization\Actions\Command\DeleteRole;
use App\Domains\Authorization\Actions\Command\UpdateRole;
use App\Domains\Authorization\Actions\Query\ListRoles;
use App\Domains\Authorization\Actions\Query\ListUsers;
use App\Domains\Authorization\Actions\Query\ShowRole;
use App\Domains\Generation\Actions\Command\RetryGeneration;
use App\Domains\Generation\Actions\Command\SendGeneration;
use App\Domains\Generation\Actions\Command\UploadReference;
use App\Domains\Generation\Actions\Query\EstimateGenerationCost;
use App\Domains\Generation\Actions\Query\ShowChat;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\LoginViewResponse;

// Guests get Fortify's login screen on the root, signed-in users are sent to the dashboard by the guest middleware.
Route::get('/', fn () => app(LoginViewResponse::class))->middleware('guest')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('generations/estimate', EstimateGenerationCost::class)->name('generations.estimate');
    Route::post('generations', SendGeneration::class)->name('generations.store');
    Route::post('generations/{generation}/retry', RetryGeneration::class)->name('generations.retry');
    Route::post('references', UploadReference::class)->name('references.store');

    Route::get('chats/{chat}', ShowChat::class)->name('chats.show');
    Route::post('chats/{chat}/generations', SendGeneration::class)->name('chats.generations.store');

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
