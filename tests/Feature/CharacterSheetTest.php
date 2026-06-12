<?php

namespace Tests\Feature;

use App\Livewire\CharacterSheet;
use App\Models\Character;
use App\Models\User;
use App\Support\SkillDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CharacterSheetTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function userWithCharacter(): array
    {
        $user      = User::factory()->create();
        $character = Character::factory()->withSkills()->create(['user_id' => $user->id]);

        return [$user, $character];
    }

    // -------------------------------------------------------------------------
    // Acesso
    // -------------------------------------------------------------------------

    public function test_visitante_nao_acessa_ficha(): void
    {
        $character = Character::factory()->withSkills()->create();

        $this->get(route('fichas.editar', $character))
            ->assertRedirect(route('login'));
    }

    public function test_jogador_nao_acessa_ficha_de_outro(): void
    {
        [$owner, $character] = $this->userWithCharacter();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('fichas.editar', $character))
            ->assertForbidden();
    }

    public function test_dono_acessa_propria_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $this->actingAs($user)
            ->get(route('fichas.editar', $character))
            ->assertOk()
            ->assertSeeLivewire(CharacterSheet::class);
    }

    // -------------------------------------------------------------------------
    // Criação
    // -------------------------------------------------------------------------

    public function test_criar_ficha_gera_personagem_com_18_pericias(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('fichas.criar'))
            ->assertRedirect();

        $character = $user->characters()->first();

        $this->assertNotNull($character);
        $this->assertCount(18, $character->skills);
    }

    public function test_skills_criadas_correspondem_as_definicoes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('fichas.criar'));

        $skillNames = $user->characters()->first()
            ->skills()
            ->pluck('name')
            ->toArray();

        foreach (SkillDefinitions::ALL as $def) {
            $this->assertContains($def['name'], $skillNames);
        }
    }

    // -------------------------------------------------------------------------
    // Edição e save
    // -------------------------------------------------------------------------

    public function test_renomear_personagem_e_salvar_persiste_no_banco(): void
    {
        [$user, $character] = $this->userWithCharacter();

        Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('name', 'Naruto Uzumaki')
            ->call('save')
            ->assertDispatched('saved');

        $this->assertDatabaseHas('characters', [
            'id'   => $character->id,
            'name' => 'Naruto Uzumaki',
        ]);
    }

    public function test_alterar_barras_e_salvar_persiste_no_banco(): void
    {
        [$user, $character] = $this->userWithCharacter();

        Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('hp_current', 15)
            ->set('hp_max', 30)
            ->set('chakra_current', 8)
            ->set('chakra_max', 25)
            ->call('save');

        $this->assertDatabaseHas('characters', [
            'id'             => $character->id,
            'hp_current'     => 15,
            'hp_max'         => 30,
            'chakra_current' => 8,
            'chakra_max'     => 25,
        ]);
    }

    public function test_alterar_atributos_e_salvar_persiste_no_banco(): void
    {
        [$user, $character] = $this->userWithCharacter();

        Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('forca', 14)
            ->set('agilidade', 16)
            ->set('constituicao', 12)
            ->set('inteligencia', 10)
            ->set('sabedoria', 13)
            ->set('carisma', 8)
            ->set('ninjutsu', 3)
            ->set('genjutsu', 1)
            ->set('taijutsu', 5)
            ->call('save');

        $this->assertDatabaseHas('characters', [
            'id'           => $character->id,
            'forca'        => 14,
            'agilidade'    => 16,
            'constituicao' => 12,
            'inteligencia' => 10,
            'sabedoria'    => 13,
            'carisma'      => 8,
            'ninjutsu'     => 3,
            'genjutsu'     => 1,
            'taijutsu'     => 5,
        ]);
    }

    public function test_alterar_pericias_e_salvar_persiste_no_banco(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character]);

        // Marca primeira perícia como treinada com valor 4
        $skills = $component->get('skills');
        $skills[0]['trained'] = true;
        $skills[0]['value']   = 4;

        $component->set('skills', $skills)->call('save');

        $this->assertDatabaseHas('character_skills', [
            'id'      => $skills[0]['id'],
            'trained' => true,
            'value'   => 4,
        ]);
    }

    public function test_ajuste_hp_respeita_limite_maximo(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('hp_max', 20)
            ->set('hp_current', 20);

        // Tentar passar do máximo
        $component->call('adjustHp', 10);
        $this->assertEquals(20, $component->get('hp_current'));

        // Tentar passar de zero
        $component->call('adjustHp', -50);
        $this->assertEquals(0, $component->get('hp_current'));
    }

    public function test_ajuste_chakra_respeita_limite_maximo(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('chakra_max', 20)
            ->set('chakra_current', 20);

        $component->call('adjustChakra', 99);
        $this->assertEquals(20, $component->get('chakra_current'));

        $component->call('adjustChakra', -99);
        $this->assertEquals(0, $component->get('chakra_current'));
    }

    public function test_autosave_endpoint_salva_dados_no_banco(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $payload = [
            'name'           => 'Sasuke',
            'hp_current'     => 5,
            'hp_max'         => 40,
            'chakra_current' => 10,
            'chakra_max'     => 35,
            'forca'          => 12,
            'agilidade'      => 18,
            'constituicao'   => 11,
            'inteligencia'   => 14,
            'sabedoria'      => 10,
            'carisma'        => 9,
            'ninjutsu'       => 4,
            'genjutsu'       => 5,
            'taijutsu'       => 3,
            'skills'         => $character->skills->map(fn ($s) => [
                'id'      => $s->id,
                'value'   => 2,
                'trained' => true,
            ])->toArray(),
        ];

        $this->actingAs($user)
            ->postJson(route('fichas.autosave', $character), $payload)
            ->assertNoContent();

        $this->assertDatabaseHas('characters', [
            'id'         => $character->id,
            'name'       => 'Sasuke',
            'hp_current' => 5,
            'ninjutsu'   => 4,
        ]);

        $this->assertDatabaseHas('character_skills', [
            'character_id' => $character->id,
            'value'        => 2,
            'trained'      => true,
        ]);
    }

    public function test_autosave_endpoint_rejeita_outro_usuario(): void
    {
        [$owner, $character] = $this->userWithCharacter();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->postJson(route('fichas.autosave', $character), ['name' => 'Hack'])
            ->assertForbidden();
    }
}
