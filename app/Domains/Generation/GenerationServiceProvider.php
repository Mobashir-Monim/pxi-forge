<?php

namespace App\Domains\Generation;

use App\Domains\Generation\Listeners\RecordProviderEvent;
use App\Services\ImageGeneration\Events\GenerationUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class GenerationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(GenerationUpdated::class, RecordProviderEvent::class);
    }
}
