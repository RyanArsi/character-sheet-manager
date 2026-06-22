<?php

namespace Tests\Feature;

use App\Livewire\NotePanel;
use App\Models\Character;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotePanelTest extends TestCase
{
    use RefreshDatabase;

    private function userWithCharacter(): array
    {
        $user = User::factory()->create();
        $character = Character::factory()->create(['user_id' => $user->id]);

        return [$user, $character];
    }

    public function test_estranho_nao_monta_o_painel(): void
    {
        [, $character] = $this->userWithCharacter();
        $intruder = User::factory()->create();

        $this->actingAs($intruder);

        Livewire::test(NotePanel::class, ['characterId' => $character->id])
            ->assertForbidden();
    }

    public function test_criar_nota_na_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(NotePanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('title', 'Missão em Suna')
            ->set('body', "Linha 1\nLinha 2 sem limite de tamanho")
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('view', 'list');

        $note = Note::firstWhere('title', 'Missão em Suna');

        $this->assertNotNull($note);
        $this->assertSame($character->id, $note->character_id);
        $this->assertStringContainsString('sem limite', $note->body);
    }

    public function test_titulo_e_obrigatorio(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(NotePanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('title', '')
            ->set('body', 'qualquer coisa')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);
    }

    public function test_editar_nota(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $note = $character->noteEntries()->create(['title' => 'Antigo', 'body' => 'velho']);
        $this->actingAs($user);

        Livewire::test(NotePanel::class, ['characterId' => $character->id])
            ->call('startEdit', $note->id)
            ->assertSet('title', 'Antigo')
            ->assertSet('body', 'velho')
            ->set('title', 'Novo')
            ->set('body', 'conteúdo atualizado')
            ->call('save')
            ->assertHasNoErrors();

        $note->refresh();
        $this->assertSame('Novo', $note->title);
        $this->assertSame('conteúdo atualizado', $note->body);
    }

    public function test_excluir_nota(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $note = $character->noteEntries()->create(['title' => 'Some logo', 'body' => null]);
        $this->actingAs($user);

        Livewire::test(NotePanel::class, ['characterId' => $character->id])
            ->call('deleteNote', $note->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('character_notes', ['id' => $note->id]);
    }

    public function test_estranho_nao_edita_nota_de_outra_ficha(): void
    {
        [, $character] = $this->userWithCharacter();
        $note = $character->noteEntries()->create(['title' => 'Privado', 'body' => 'segredo']);

        [$other, $otherCharacter] = $this->userWithCharacter();
        $this->actingAs($other);

        // O painel do intruso é da própria ficha; a nota alheia não é encontrada.
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(NotePanel::class, ['characterId' => $otherCharacter->id])
            ->call('startEdit', $note->id);
    }
}
