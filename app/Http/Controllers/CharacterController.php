<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Support\SkillDefinitions;
use Illuminate\Http\RedirectResponse;

class CharacterController extends Controller
{
    public function create(): RedirectResponse
    {
        $character = Character::create(['user_id' => auth()->id()]);

        foreach (SkillDefinitions::ALL as $def) {
            $character->skills()->create([
                'name' => $def['name'],
                'attribute' => $def['attribute'],
            ]);
        }

        return redirect()->route('fichas.editar', $character);
    }
}
