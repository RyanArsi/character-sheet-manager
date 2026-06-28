<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CharacterFactory extends Factory
{
    protected $model = Character::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'name'           => fake()->name(),
            'level'          => 1,
            'xp'             => 0,
            'hp_current'     => 20,
            'hp_max'         => 20,
            'chakra_current' => 20,
            'chakra_max'     => 20,
            'defense'        => 0,
            'forca'          => 10,
            'agilidade'      => 10,
            'constituicao'   => 10,
            'inteligencia'   => 10,
            'sabedoria'      => 10,
            'carisma'        => 10,
            'ninjutsu'       => 0,
            'genjutsu'       => 0,
            'taijutsu'       => 0,
        ];
    }

    public function withSkills(): static
    {
        return $this->afterCreating(function (Character $character) {
            foreach (\App\Support\SkillDefinitions::ALL as $def) {
                $character->skills()->create([
                    'name'      => $def['name'],
                    'attribute' => $def['attribute'],
                    'category'  => $def['category'],
                    'value'     => 0,
                    'trained'   => false,
                ]);
            }
        });
    }
}
