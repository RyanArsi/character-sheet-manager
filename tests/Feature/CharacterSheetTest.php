<?php

namespace Tests\Feature;

use App\Events\CampaignMediaBroadcast;
use App\Livewire\CharacterSheet;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use App\Support\SkillDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_ajuste_hp_pode_passar_do_maximo_mas_nao_de_zero(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('hp_max', 20)
            ->set('hp_current', 20);

        // Pode ultrapassar o máximo (ex.: vida temporária)
        $component->call('adjustHp', 10);
        $this->assertEquals(30, $component->get('hp_current'));

        // Mas nunca desce de zero
        $component->call('adjustHp', -50);
        $this->assertEquals(0, $component->get('hp_current'));
    }

    public function test_ajuste_chakra_pode_passar_do_maximo_mas_nao_de_zero(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('chakra_max', 20)
            ->set('chakra_current', 20);

        $component->call('adjustChakra', 99);
        $this->assertEquals(119, $component->get('chakra_current'));

        $component->call('adjustChakra', -999);
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

    public function test_apagar_campos_numericos_vale_zero_sem_erro(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character]);

        // Apagar o input (envia "") não deve quebrar e deve valer 0
        $component->set('hp_current', '');
        $this->assertSame(0, $component->get('hp_current'));

        $component->set('chakra_max', '');
        $this->assertSame(0, $component->get('chakra_max'));

        // Valor normal continua sendo preservado
        $component->set('hp_max', '35');
        $this->assertSame(35, $component->get('hp_max'));

        // Valor de perícia apagado vale 0
        $component->set('skills.0.value', '');
        $this->assertSame(0, $component->get('skills.0.value'));

        // Nível tem regra própria: vazio cai para o mínimo 1
        $component->set('level', '');
        $this->assertSame(1, $component->get('level'));
    }

    // -------------------------------------------------------------------------
    // Mídia compartilhada com a campanha (broadcast de reprodução)
    // -------------------------------------------------------------------------

    public function test_share_media_transmite_url_para_a_campanha(): void
    {
        Event::fake([CampaignMediaBroadcast::class]);

        [$user, $character] = $this->userWithCharacter();
        $campaign = Campaign::factory()->create(['owner_id' => $user->id]);
        $campaign->members()->attach($user->id, ['role' => 'owner']);
        $campaign->characters()->attach($character->id);

        Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->call('shareMedia', $campaign->id, 'https://exemplo.test/audio/jutsu.mp3', 80);

        Event::assertDispatched(CampaignMediaBroadcast::class, function ($e) use ($campaign) {
            return $e->campaignId === $campaign->id
                && $e->url === 'https://exemplo.test/audio/jutsu.mp3'
                && $e->volume === 80;
        });
    }

    public function test_share_media_rejeita_campanha_alheia(): void
    {
        Event::fake([CampaignMediaBroadcast::class]);

        [$user, $character] = $this->userWithCharacter();
        // Campanha em que a ficha NÃO está
        $other = Campaign::factory()->create();

        Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->call('shareMedia', $other->id, 'https://exemplo.test/audio/jutsu.mp3', 100)
            ->assertForbidden();

        Event::assertNotDispatched(CampaignMediaBroadcast::class);
    }

    public function test_share_media_ignora_url_vazia(): void
    {
        Event::fake([CampaignMediaBroadcast::class]);

        [$user, $character] = $this->userWithCharacter();
        $campaign = Campaign::factory()->create(['owner_id' => $user->id]);
        $campaign->members()->attach($user->id, ['role' => 'owner']);
        $campaign->characters()->attach($character->id);

        Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->call('shareMedia', $campaign->id, '   ', 100);

        Event::assertNotDispatched(CampaignMediaBroadcast::class);
    }
}
