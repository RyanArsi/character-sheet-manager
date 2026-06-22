<?php

namespace Tests\Feature;

use App\Livewire\ActionPanel;
use App\Models\Action;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActionPanelTest extends TestCase
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

        Livewire::actingAs($intruder)
            ->test(ActionPanel::class, ['characterId' => $character->id])
            ->assertForbidden();
    }

    public function test_criar_acao_atribui_a_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();

        Livewire::actingAs($user)
            ->test(ActionPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Furtividade')
            ->set('tagsInput', 'social, sigilo')
            ->set('test_dice', 'd20+agilidade')
            ->call('save')
            ->assertHasNoErrors();

        $action = Action::firstWhere('name', 'Furtividade');

        $this->assertNotNull($action);
        $this->assertSame($user->id, $action->user_id);
        $this->assertSame(['social', 'sigilo'], $action->tags);
        $this->assertTrue($character->actions()->whereKey($action->id)->exists());
    }

    public function test_nome_e_obrigatorio(): void
    {
        [$user, $character] = $this->userWithCharacter();

        Livewire::actingAs($user)
            ->test(ActionPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', '')
            ->set('test_dice', 'd20')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_atribuir_e_remover_acao_da_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $action = $user->actions()->create(['name' => 'Intimidar', 'test_dice' => 'd20+carisma']);

        $component = Livewire::actingAs($user)
            ->test(ActionPanel::class, ['characterId' => $character->id]);

        $component->call('assign', $action->id);
        $this->assertTrue($character->actions()->whereKey($action->id)->exists());

        $component->call('unassign', $action->id);
        $this->assertFalse($character->actions()->whereKey($action->id)->exists());
    }

    public function test_editar_acao(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $action = $user->actions()->create(['name' => 'Antigo', 'test_dice' => 'd20']);

        Livewire::actingAs($user)
            ->test(ActionPanel::class, ['characterId' => $character->id])
            ->call('startEdit', $action->id)
            ->assertSet('name', 'Antigo')
            ->set('name', 'Escalar')
            ->set('damage_dice', '2d6')
            ->call('save')
            ->assertHasNoErrors();

        $action->refresh();
        $this->assertSame('Escalar', $action->name);
        $this->assertSame('2d6', $action->damage_dice);
    }
}
