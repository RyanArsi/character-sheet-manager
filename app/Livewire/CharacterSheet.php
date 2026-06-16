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

    #[Rule('required|string|max:100')]
    public string $name = '';
    public int $level = 1;
    public int $xp = 0;

    public int $hp_current = 20;
    public int $hp_max = 20;
    public int $chakra_current = 20;
    public int $chakra_max = 20;

    public int $forca = 0;
    public int $agilidade = 0;
    public int $constituicao = 0;
    public int $inteligencia = 0;
    public int $sabedoria = 0;
    public int $carisma = 0;

    public int $ninjutsu = 0;
    public int $genjutsu = 0;
    public int $taijutsu = 0;

    public array $skills = [];

    public $newAvatar;
    public ?string $avatarPath = null;

    public function mount(Character $character): void
    {
        abort_unless($character->canBeManagedBy(auth()->user()), 403);

        $this->characterId = $character->id;
        $this->fill($character->only([
            'name', 'level', 'xp',
            'hp_current', 'hp_max', 'chakra_current', 'chakra_max',
            'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
            'ninjutsu', 'genjutsu', 'taijutsu',
        ]));
        $this->avatarPath = $character->avatar;

        $this->skills = $character->skills()
            ->orderBy('id')
            ->get(['id', 'name', 'attribute', 'value', 'trained', 'training_level'])
            ->map(fn ($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'attribute'      => $s->attribute,
                'value'          => $s->value,
                'trained'        => $s->trained,
                'training_level' => $s->training_level,
            ])
            ->toArray();
    }

    // Chamado pelo Alpine após cada round-trip — sincroniza localStorage com estado do servidor
    public function updated(): void
    {
        $this->dispatch('sync-storage', state: $this->currentState());
    }

    // Restaura estado vindo do localStorage (chamado pelo Alpine no init)
    public function restoreFromSession(array $data): void
    {
        $fields = ['name', 'hp_current', 'hp_max', 'chakra_current', 'chakra_max',
                   'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
                   'ninjutsu', 'genjutsu', 'taijutsu'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $this->$field = $data[$field];
            }
        }

        if (! empty($data['skills'])) {
            foreach ($data['skills'] as $incoming) {
                foreach ($this->skills as $i => $skill) {
                    if ($skill['id'] === $incoming['id']) {
                        $this->skills[$i]['value']          = $incoming['value'];
                        $this->skills[$i]['trained']        = $incoming['trained'];
                        $this->skills[$i]['training_level'] = $incoming['training_level'] ?? 0;
                        break;
                    }
                }
            }
        }
    }

    public function save(): void
    {
        $this->validate();

        $character = Character::find($this->characterId);

        $character->update([
            'name'           => $this->name,
            'level'          => $this->level,
            'xp'             => $this->xp,
            'hp_current'     => $this->hp_current,
            'hp_max'         => $this->hp_max,
            'chakra_current' => $this->chakra_current,
            'chakra_max'     => $this->chakra_max,
            'forca'          => $this->forca,
            'agilidade'      => $this->agilidade,
            'constituicao'   => $this->constituicao,
            'inteligencia'   => $this->inteligencia,
            'sabedoria'      => $this->sabedoria,
            'carisma'        => $this->carisma,
            'ninjutsu'       => $this->ninjutsu,
            'genjutsu'       => $this->genjutsu,
            'taijutsu'       => $this->taijutsu,
        ]);

        foreach ($this->skills as $skill) {
            $character->skills()
                ->where('id', $skill['id'])
                ->update([
                    'value'          => $skill['value'],
                    'trained'        => $skill['trained'],
                    'training_level' => $skill['training_level'] ?? 0,
                ]);
        }

        $this->dispatch('saved');
    }

    public function adjustAttr(string $field, int $delta): void
    {
        $allowed = ['forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
                    'ninjutsu', 'genjutsu', 'taijutsu'];

        if (! in_array($field, $allowed)) {
            return;
        }

        $this->$field = max(0, $this->$field + $delta);
    }

    public function cycleTraining(int $index): void
    {
        if (! isset($this->skills[$index])) return;

        $current = $this->skills[$index]['training_level'] ?? 0;
        $this->skills[$index]['training_level'] = ($current + 1) % 6;
        $this->skills[$index]['trained'] = $this->skills[$index]['training_level'] > 0;
    }

    public function adjustHp(int $delta): void
    {
        $this->hp_current = max(0, min($this->hp_max, $this->hp_current + $delta));
    }

    public function adjustChakra(int $delta): void
    {
        $this->chakra_current = max(0, min($this->chakra_max, $this->chakra_current + $delta));
    }

    public function uploadAvatar(): void
    {
        $this->validateOnly('newAvatar', ['newAvatar' => 'image|max:2048']);

        $path = $this->newAvatar->store('avatars', 'public');

        Character::find($this->characterId)->update(['avatar' => $path]);
        $this->avatarPath = $path;
        $this->newAvatar = null;
    }

    private function currentState(): array
    {
        return [
            'name'           => $this->name,
            'hp_current'     => $this->hp_current,
            'hp_max'         => $this->hp_max,
            'chakra_current' => $this->chakra_current,
            'chakra_max'     => $this->chakra_max,
            'forca'          => $this->forca,
            'agilidade'      => $this->agilidade,
            'constituicao'   => $this->constituicao,
            'inteligencia'   => $this->inteligencia,
            'sabedoria'      => $this->sabedoria,
            'carisma'        => $this->carisma,
            'ninjutsu'       => $this->ninjutsu,
            'genjutsu'       => $this->genjutsu,
            'taijutsu'       => $this->taijutsu,
            'skills'         => $this->skills,
        ];
    }

    public function render()
    {
        return view('livewire.character-sheet');
    }
}
