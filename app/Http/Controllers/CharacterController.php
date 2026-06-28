<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Support\SkillDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CharacterController extends Controller
{
    public function create(): RedirectResponse
    {
        $character = Character::create(['user_id' => auth()->id()]);

        foreach (SkillDefinitions::ALL as $def) {
            $character->skills()->create([
                'name' => $def['name'],
                'attribute' => $def['attribute'],
                'category' => $def['category'],
            ]);
        }

        return redirect()->route('fichas.editar', $character);
    }

    public function autosave(Request $request, Character $character): Response
    {
        abort_unless($character->canBeManagedBy(auth()->user()), 403);

        $data = $request->json()->all();

        $character->update(array_filter([
            'name'           => $data['name']           ?? null,
            'cla'            => $data['cla']            ?? null,
            'level'          => $data['level']          ?? null,
            'pt'             => $data['pt']             ?? null,
            'hp_current'     => $data['hp_current']     ?? null,
            'hp_max'         => $data['hp_max']         ?? null,
            'chakra_current' => $data['chakra_current'] ?? null,
            'chakra_max'     => $data['chakra_max']     ?? null,
            'defense'        => $data['defense']        ?? null,
            'forca'          => $data['forca']          ?? null,
            'agilidade'      => $data['agilidade']      ?? null,
            'constituicao'   => $data['constituicao']   ?? null,
            'inteligencia'   => $data['inteligencia']   ?? null,
            'sabedoria'      => $data['sabedoria']      ?? null,
            'carisma'        => $data['carisma']        ?? null,
            'ninjutsu'       => $data['ninjutsu']       ?? null,
            'genjutsu'       => $data['genjutsu']       ?? null,
            'taijutsu'       => $data['taijutsu']       ?? null,
        ], fn ($v) => $v !== null));

        if (! empty($data['skills'])) {
            foreach ($data['skills'] as $skill) {
                $character->skills()
                    ->where('id', $skill['id'])
                    ->update(array_filter([
                        'value'          => $skill['value'] ?? null,
                        'trained'        => $skill['trained'] ?? null,
                        'training_level' => $skill['training_level'] ?? null,
                        'attribute'      => $skill['attribute'] ?? null,
                    ], fn ($v) => $v !== null));
            }
        }

        return response()->noContent();
    }
}
