<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'owner_id'    => User::factory(),
            'name'        => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            // invite_token é gerado automaticamente no booted() do model
        ];
    }
}
