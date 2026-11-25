<?php

namespace App\Services\Shared;

use App\Services\Shared\Contracts\HandlesWebhooks;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpFoundation\Response;

/** Single thin entry for inbound provider webhooks. The adapter verifies its own signature. */
final class HandleProviderWebhook
{
    use AsController;

    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    public function asController(ActionRequest $request, string $service, string $provider): Response
    {
        // A pair the map does not name is a wrong URL, not our outage, so it must not reach resolve() and read as a 503.
        abort_unless(is_string(config("services-arch.{$service}.providers.{$provider}")), 404);

        $adapter = $this->registry->resolve($service, $provider);

        abort_unless($adapter instanceof HandlesWebhooks, 404);

        return $adapter->handleWebhook($request);
    }
}
