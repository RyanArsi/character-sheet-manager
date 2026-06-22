<?php

namespace Tests\Feature;

use App\Livewire\TestPanel;
use App\Models\Character;
use App\Models\CharacterTest;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TestPanelTest extends TestCase
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

        Livewire::test(TestPanel::class, ['characterId' => $character->id])
            ->assertForbidden();
    }

    public function test_criar_teste_na_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(TestPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Esquiva')
            ->set('test_dice', 'd20+agilidade')
            ->set('damage_dice', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('view', 'list');

        $test = CharacterTest::firstWhere('name', 'Esquiva');

        $this->assertNotNull($test);
        $this->assertSame($character->id, $test->character_id);
        $this->assertSame('d20+agilidade', $test->test_dice);
        $this->assertNull($test->damage_dice);
    }

    public function test_nome_e_obrigatorio(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(TestPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', '')
            ->set('test_dice', 'd20')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_editar_teste(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $test = $character->tests()->create(['name' => 'Antigo', 'test_dice' => 'd20']);
        $this->actingAs($user);

        Livewire::test(TestPanel::class, ['characterId' => $character->id])
            ->call('startEdit', $test->id)
            ->assertSet('name', 'Antigo')
            ->assertSet('test_dice', 'd20')
            ->set('name', 'Ataque pesado')
            ->set('damage_dice', '4d6+forca')
            ->call('save')
            ->assertHasNoErrors();

        $test->refresh();
        $this->assertSame('Ataque pesado', $test->name);
        $this->assertSame('4d6+forca', $test->damage_dice);
    }

    public function test_excluir_teste(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $test = $character->tests()->create(['name' => 'Some logo', 'test_dice' => 'd20']);
        $this->actingAs($user);

        Livewire::test(TestPanel::class, ['characterId' => $character->id])
            ->call('deleteTest', $test->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('character_tests', ['id' => $test->id]);
    }

    public function test_estranho_nao_edita_teste_de_outra_ficha(): void
    {
        [, $character] = $this->userWithCharacter();
        $test = $character->tests()->create(['name' => 'Privado', 'test_dice' => 'd20']);

        [$other, $otherCharacter] = $this->userWithCharacter();
        $this->actingAs($other);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(TestPanel::class, ['characterId' => $otherCharacter->id])
            ->call('startEdit', $test->id);
    }
}
