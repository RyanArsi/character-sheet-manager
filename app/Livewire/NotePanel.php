<?php

namespace App\Livewire;

use App\Models\Character;
use App\Models\Note;
use Livewire\Attributes\Locked;
use Livewire\Component;

class NotePanel extends Component
{
    #[Locked]
    public int $characterId;

    /** Modo do painel: list | form */
    public string $view = 'list';

    // ---- Formulário (criar/editar) ----
    public ?int $editingId = null;
    public string $title = '';
    public string $body = '';

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

    public function startEdit(int $noteId): void
    {
        $note = $this->character()->noteEntries()->findOrFail($noteId);

        $this->editingId = $note->id;
        $this->title     = $note->title;
        $this->body      = $note->body ?? '';
        $this->view      = 'form';
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->view = 'list';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'body']);
    }

    // ---------- Persistência ----------
    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:150',
            'body'  => 'nullable|string',
        ]);

        $data = [
            'title' => $this->title,
            'body'  => $this->body ?: null,
        ];

        if ($this->editingId) {
            $note = $this->character()->noteEntries()->findOrFail($this->editingId);
            $note->update($data);
        } else {
            $this->character()->noteEntries()->create($data);
        }

        $this->resetForm();
        $this->view = 'list';
    }

    public function deleteNote(int $noteId): void
    {
        $this->character()->noteEntries()->findOrFail($noteId)->delete();
        $this->resetForm();
        $this->view = 'list';
    }

    public function render()
    {
        $notes = $this->character()
            ->noteEntries()
            ->latest('updated_at')
            ->get();

        return view('livewire.note-panel', [
            'notes' => $notes,
        ]);
    }
}
