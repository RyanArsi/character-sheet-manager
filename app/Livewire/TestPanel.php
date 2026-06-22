<?php

namespace App\Livewire;

use App\Models\Character;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TestPanel extends Component
{
    #[Locked]
    public int $characterId;

    /** Modo do painel: list | form */
    public string $view = 'list';

    // ---- Formulário (criar/editar) ----
    public ?int $editingId = null;
    public string $name = '';
    public string $test_dice = '';
    public string $damage_dice = '';

    public function mount(int $characterId): void
    {
        $character = Character::findOrFail($characterId);
        abort_unless($character->canBeManagedBy(auth()->user()), 403);

        $this->characterId = $characterId;
    }

    protected function character(): Character
    {
        return Character::findOrFail($this->characterId);
    }

    // ---------- Navegação ----------
    public function startCreate(): void
    {
        $this->resetForm();
        $this->view = 'form';
    }

    public function startEdit(int $testId): void
    {
        $test = $this->character()->tests()->findOrFail($testId);

        $this->editingId   = $test->id;
        $this->name        = $test->name;
        $this->test_dice   = $test->test_dice ?? '';
        $this->damage_dice = $test->damage_dice ?? '';
        $this->view        = 'form';
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->view = 'list';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'test_dice', 'damage_dice']);
    }

    // ---------- Persistência ----------
    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:120',
            'test_dice'   => 'nullable|string|max:120',
            'damage_dice' => 'nullable|string|max:120',
        ]);

        $data = [
            'name'        => $this->name,
            'test_dice'   => $this->test_dice ?: null,
            'damage_dice' => $this->damage_dice ?: null,
        ];

        if ($this->editingId) {
            $test = $this->character()->tests()->findOrFail($this->editingId);
            $test->update($data);
        } else {
            $this->character()->tests()->create($data);
        }

        $this->resetForm();
        $this->view = 'list';
    }

    public function deleteTest(int $testId): void
    {
        $this->character()->tests()->findOrFail($testId)->delete();
        $this->resetForm();
        $this->view = 'list';
    }

    public function render()
    {
        $tests = $this->character()
            ->tests()
            ->latest('updated_at')
            ->get();

        return view('livewire.test-panel', [
            'tests' => $tests,
        ]);
    }
}
