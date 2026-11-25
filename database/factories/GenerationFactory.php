<?php

namespace Database\Factories;

use App\Domains\Generation\Models\Chat;
use App\Domains\Generation\Models\Generation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Generation> */
class GenerationFactory extends Factory
{
    protected $model = Generation::class;

    public function definition(): array
    {
        return [
            'chat_id' => Chat::factory(),
            'user_id' => fn (array $attributes) => Chat::query()->whereKey($attributes['chat_id'])->value('user_id'),
            'message' => 'A lighthouse at dawn',
            'prompt' => 'A lighthouse at dawn',
            'size' => '1024x1024',
            'quality' => 'medium',
            'references' => [],
            'estimate_min_nd' => 0,
            'estimate_max_nd' => 0,
        ];
    }
}
