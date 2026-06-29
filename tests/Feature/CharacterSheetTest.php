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

    public function test_criar_ficha_gera_personagem_com_pericias_padrao(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('fichas.criar'))
            ->assertRedirect();

        $character = $user->characters()->first();

        $this->assertNotNull($character);
        $this->assertCount(count(SkillDefinitions::ALL), $character->skills);
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

    public function test_pontos_de_treinamento_persistem_no_banco(): void
    {
        [$user, $character] = $this->userWithCharacter();

        Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('pt', '3d6 + 2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('saved');

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'pt' => '3d6 + 2',
        ]);
    }

    public function test_pontos_de_treinamento_limitados_a_12_caracteres(): void
    {
        [$user, $character] = $this->userWithCharacter();

        Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('pt', str_repeat('x', 13))
            ->call('save')
            ->assertHasErrors(['pt' => 'max']);
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

    public function test_return_url_local_e_capturada(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $from = url('/campanhas/5').'#combate';

        Livewire::actingAs($user)
            ->withQueryParams(['from' => $from])
            ->test(CharacterSheet::class, ['character' => $character])
            ->assertSet('returnUrl', $from);
    }

    public function test_return_url_externa_e_ignorada(): void
    {
        [$user, $character] = $this->userWithCharacter();

        Livewire::actingAs($user)
            ->withQueryParams(['from' => 'https://evil.example.com/phish'])
            ->test(CharacterSheet::class, ['character' => $character])
            ->assertSet('returnUrl', null);
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

        // Atributos e especializações apagados também valem 0 (não podem quebrar)
        $component->set('forca', '');
        $this->assertSame(0, $component->get('forca'));

        $component->set('inteligencia', '');
        $this->assertSame(0, $component->get('inteligencia'));

        $component->set('taijutsu', '');
        $this->assertSame(0, $component->get('taijutsu'));

        // Nível tem regra própria: vazio cai para o mínimo 1
        $component->set('level', '');
        $this->assertSame(1, $component->get('level'));
    }

    public function test_atributos_e_especializacoes_aceitam_valores_negativos(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character]);

        // Digitar negativo é mantido
        $component->set('forca', -2);
        $this->assertSame(-2, $component->get('forca'));

        // Botão − pode levar abaixo de zero
        $component->set('taijutsu', 0)->call('adjustAttr', 'taijutsu', -1);
        $this->assertSame(-1, $component->get('taijutsu'));

        // Persiste no banco
        $component->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('characters', [
            'id'       => $character->id,
            'forca'    => -2,
            'taijutsu' => -1,
        ]);
    }

    public function test_trocar_atributo_de_rolagem_da_pericia(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character]);

        $component->call('setSkillAttribute', 0, 'for');
        $this->assertSame('for', $component->get('skills.0.attribute'));

        // Persiste no banco ao salvar
        $skillId = $component->get('skills.0.id');
        $component->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('character_skills', [
            'id'        => $skillId,
            'attribute' => 'for',
        ]);

        // Valor inválido é ignorado (mantém o anterior)
        $component->call('setSkillAttribute', 0, 'Inexistente');
        $this->assertSame('for', $component->get('skills.0.attribute'));
    }

    // -------------------------------------------------------------------------
    // Modos (modificadores ativáveis)
    // -------------------------------------------------------------------------

    public function test_modo_aplica_e_reverte_modificadores(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('forca', 10)->set('defense', 5)->set('hp_max', 20);

        $component->call('addMode')
            ->set('modes.0.mod_atributos', 3)
            ->set('modes.0.mod_ca', -2)     // modificador negativo
            ->set('modes.0.mod_vida_max', 5);

        // Ativar aplica
        $component->call('toggleMode', 0);
        $this->assertTrue($component->get('modes.0.active'));
        $this->assertSame(13, $component->get('forca'));
        $this->assertSame(3, $component->get('defense'));
        $this->assertSame(25, $component->get('hp_max'));

        // Persistiu o estado ativo
        $this->assertDatabaseHas('character_modes', [
            'character_id' => $character->id,
            'active'       => true,
            'mod_atributos' => 3,
        ]);

        // Desativar reverte exatamente
        $component->call('toggleMode', 0);
        $this->assertFalse($component->get('modes.0.active'));
        $this->assertSame(10, $component->get('forca'));
        $this->assertSame(5, $component->get('defense'));
        $this->assertSame(20, $component->get('hp_max'));
    }

    public function test_multiplos_modos_acumulam_nas_pericias(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character]);

        // Índice de uma perícia (categoria 'pericia')
        $skills = $component->get('skills');
        $p = collect($skills)->search(fn ($s) => ($s['category'] ?? 'pericia') === 'pericia');
        $component->set("skills.$p.value", 0);

        $component->call('addMode')->set('modes.0.mod_pericias', 2);
        $component->call('addMode')->set('modes.1.mod_pericias', -1);

        $component->call('toggleMode', 0)->call('toggleMode', 1);
        $this->assertSame(1, $component->get("skills.$p.value")); // 0 + 2 - 1

        // Desligar um remove só a parte dele
        $component->call('toggleMode', 0);
        $this->assertSame(-1, $component->get("skills.$p.value"));
    }

    public function test_excluir_modo_ativo_reverte_antes(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('chakra_max', 30);

        $component->call('addMode')->set('modes.0.mod_chakra_max', 10)->call('toggleMode', 0);
        $this->assertSame(40, $component->get('chakra_max'));

        $component->call('removeMode', 0);
        $this->assertSame(30, $component->get('chakra_max'));
        $this->assertCount(0, $component->get('modes'));
    }

    /** Captura todas as stats da ficha (atributos, especializações, barras, CA e perícias). */
    private function sheetSnapshot($component): array
    {
        $snap = [];
        foreach (['forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
                  'ninjutsu', 'genjutsu', 'taijutsu', 'hp_current', 'hp_max',
                  'chakra_current', 'chakra_max', 'defense'] as $f) {
            $snap[$f] = $component->get($f);
        }
        foreach ($component->get('skills') as $s) {
            $snap['skill_'.$s['id']] = $s['value'];
        }

        return $snap;
    }

    public function test_modo_so_de_dados_nao_altera_nenhuma_stat(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character]);

        $before = $this->sheetSnapshot($component);

        $component->call('addMode')->set('modes.0.mod_dados', 5);

        $component->call('toggleMode', 0);
        $this->assertTrue($component->get('modes.0.active'));
        // 'Dados' é bônus de rolagem — não pode mexer em nenhum valor da ficha.
        $this->assertSame($before, $this->sheetSnapshot($component));

        $component->call('toggleMode', 0);
        $this->assertSame($before, $this->sheetSnapshot($component));
    }

    public function test_modos_complexos_nunca_alteram_a_ficha_permanentemente(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character]);

        $before = $this->sheetSnapshot($component);

        $skills = $component->get('skills');
        $skillId = $skills[0]['id'];

        // Modo 1: todas as categorias, gerais + individuais, sinais misturados.
        $component->call('addMode')
            ->set('modes.0.mod_atributos', 3)
            ->set('modes.0.mod_especializacao', -2)
            ->set('modes.0.mod_pericias', 1)
            ->set('modes.0.mod_resistencias', -1)
            ->set('modes.0.mod_combate', 2)
            ->set('modes.0.mod_ca', -4)
            ->set('modes.0.mod_vida_atual', 5)
            ->set('modes.0.mod_vida_max', -3)
            ->set('modes.0.mod_chakra_atual', -6)
            ->set('modes.0.mod_chakra_max', 7)
            ->set('modes.0.mod_dados', 2)
            ->set('modes.0.individual.attrs.forca', -5)
            ->set('modes.0.individual.spec.ninjutsu', 4)
            ->set("modes.0.individual.skills.$skillId", -3);

        // Modo 2: simples, sobrepondo no mesmo atributo.
        $component->call('addMode')
            ->set('modes.1.mod_atributos', -1)
            ->set('modes.1.individual.attrs.forca', 10);

        // Liga os dois → algo tem que mudar.
        $component->call('toggleMode', 0)->call('toggleMode', 1);
        $this->assertNotSame($before, $this->sheetSnapshot($component));

        // Desliga em ORDEM INVERSA → volta exatamente ao original.
        $component->call('toggleMode', 1)->call('toggleMode', 0);
        $this->assertSame($before, $this->sheetSnapshot($component));

        // Liga de novo e desliga na MESMA ordem → também volta ao original.
        $component->call('toggleMode', 0)->call('toggleMode', 1);
        $component->call('toggleMode', 0)->call('toggleMode', 1);
        $this->assertSame($before, $this->sheetSnapshot($component));

        // Persiste com modos desligados e recarrega: ficha continua original.
        $component->call('save')->assertHasNoErrors();
        $fresh = Livewire::actingAs($user)->test(CharacterSheet::class, ['character' => $character]);
        $this->assertSame($before, $this->sheetSnapshot($fresh));
    }

    public function test_excluir_modo_ativo_nao_deixa_residuo_na_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character]);

        $before = $this->sheetSnapshot($component);

        $component->call('addMode')
            ->set('modes.0.mod_atributos', 4)
            ->set('modes.0.individual.attrs.forca', 9)
            ->call('toggleMode', 0);

        $this->assertNotSame($before, $this->sheetSnapshot($component));

        // Excluir um modo ATIVO deve reverter antes de remover.
        $component->call('removeMode', 0);
        $this->assertSame($before, $this->sheetSnapshot($component));
    }

    public function test_modo_aplica_modificadores_individuais_alem_dos_gerais(): void
    {
        [$user, $character] = $this->userWithCharacter();

        $component = Livewire::actingAs($user)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('forca', 10)->set('agilidade', 10);

        $skills = $component->get('skills');
        $sid = $skills[0]['id']; // primeira perícia (categoria 'pericia')
        $component->set('skills.0.value', 0);

        $component->call('addMode')
            ->set('modes.0.mod_atributos', 1)               // geral em todos os atributos
            ->set('modes.0.individual.attrs.forca', 2)       // individual só na força
            ->set("modes.0.individual.skills.$sid", 4);      // individual nessa perícia

        $component->call('toggleMode', 0);
        $this->assertSame(13, $component->get('forca'));      // 10 + 1 geral + 2 individual
        $this->assertSame(11, $component->get('agilidade'));  // 10 + 1 geral
        $this->assertSame(4, $component->get('skills.0.value')); // 0 + 4 individual

        // Reverte tudo ao desligar
        $component->call('toggleMode', 0);
        $this->assertSame(10, $component->get('forca'));
        $this->assertSame(10, $component->get('agilidade'));
        $this->assertSame(0, $component->get('skills.0.value'));

        // Persiste os individuais
        $component->call('save')->assertHasNoErrors();
        $mode = \App\Models\CharacterMode::where('character_id', $character->id)->first();
        $this->assertSame(2, $mode->individual['attrs']['forca']);
        $this->assertSame(4, (int) ($mode->individual['skills'][$sid] ?? 0));
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
