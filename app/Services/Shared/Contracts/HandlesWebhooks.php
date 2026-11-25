<?php

namespace App\Services\Shared\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Opt-in for provider adapters that receive inbound webhooks. */
interface HandlesWebhooks
{
    public function handleWebhook(Request $request): Response;
}
