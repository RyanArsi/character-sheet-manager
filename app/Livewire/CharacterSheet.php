<?php

namespace App\Livewire;

use App\Models\Character;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.sheet')]
class CharacterSheet extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $characterId;

    // Identidade
    #[Rule('required|string|max:100')]
    public string $name = '';
    public ?string $race = '';
    public ?string $village = '';
    public int $level = 1;
    public int $xp = 0;

    // Barras
    public int $hp_current = 20;
    public int $hp_max = 20;
    public int $chakra_current = 20;
    public int $chakra_max = 20;

    // Atributos
    public int $forca = 10;
    public int $agilidade = 10;
    public int $constituicao = 10;
    public int $inteligencia = 10;
    public int $sabedoria = 10;
    public int $carisma = 10;

    // Especializações
    public int $ninjutsu = 0;
    public int $genjutsu = 0;
    public int $taijutsu = 0;

    // Perícias: [['id' => ..., 'name' => ..., 'attribute' => ..., 'value' => ..., 'trained' => ...]]
    public array $skills = [];

    // Avatar
    public $newAvatar;
    public ?string $avatarPath = null;

    public function mount(Character $character): void
    {
        abort_unless(auth()->id() === $character->user_id, 403);

        $this->characterId = $character->id;
        $this->fill($character->only([
            'name', 'race', 'village', 'level', 'xp',
            'hp_current', 'hp_max', 'chakra_current', 'chakra_max',
            'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
            'ninjutsu', 'genjutsu', 'taijutsu',
        ]));
        $this->avatarPath = $character->avatar;

        $this->skills = $character->skills()
            ->orderBy('id')
            ->get(['id', 'name', 'attribute', 'value', 'trained'])
            ->map(fn ($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'attribute' => $s->attribute,
                'value'     => $s->value,
                'trained'   => $s->trained,
            ])
            ->toArray();
    }

    public function save(): void
    {
        $this->validate();

        $character = Character::find($this->characterId);

        $character->update([
            'name' => $this->name,
            'race' => $this->race ?? '',
            'village' => $this->village ?? '',
            'level' => $this->level,
            'xp' => $this->xp,
            'hp_current' => $this->hp_current,
            'hp_max' => $this->hp_max,
            'chakra_current' => $this->chakra_current,
            'chakra_max' => $this->chakra_max,
            'forca' => $this->forca,
            'agilidade' => $this->agilidade,
            'constituicao' => $this->constituicao,
            'inteligencia' => $this->inteligencia,
            'sabedoria' => $this->sabedoria,
            'carisma' => $this->carisma,
            'ninjutsu' => $this->ninjutsu,
            'genjutsu' => $this->genjutsu,
            'taijutsu' => $this->taijutsu,
        ]);

        foreach ($this->skills as $skill) {
            $character->skills()
                ->where('id', $skill['id'])
                ->update(['value' => $skill['value'], 'trained' => $skill['trained']]);
        }

        $this->dispatch('saved');
    }

    public function uploadAvatar(): void
    {
        $this->validateOnly('newAvatar', [
            'newAvatar' => 'image|max:2048',
        ]);

        $path = $this->newAvatar->store('avatars', 'public');

        Character::find($this->characterId)->update(['avatar' => $path]);
        $this->avatarPath = $path;
        $this->newAvatar = null;
    }

    public function render()
    {
        return view('livewire.character-sheet');
    }
}
