<?php

use App\Services\Shared\HandleProviderWebhook;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/{service}/{provider}', HandleProviderWebhook::class)
    ->name('webhooks.handle');
