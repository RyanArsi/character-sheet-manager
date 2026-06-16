<?php

namespace Tests\Feature;

use App\Livewire\CharacterSheet;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Cria uma campanha já com o dono como membro (estado pós-criação). */
    private function makeCampaign(?User $owner = null): Campaign
    {
        $owner ??= User::factory()->create();
        $campaign = Campaign::factory()->create(['owner_id' => $owner->id]);
        $campaign->members()->attach($owner->id, ['role' => 'owner']);

        return $campaign;
    }

    /** Coloca um jogador (com fichas) dentro da campanha. */
    private function joinPlayer(Campaign $campaign, User $player, array $characters = []): void
    {
        $campaign->members()->attach($player->id, ['role' => 'player']);
        if ($characters) {
            $campaign->characters()->syncWithoutDetaching($characters);
        }
    }

    // -------------------------------------------------------------------------
    // Criar campanha
    // -------------------------------------------------------------------------

    public function test_visitante_nao_acessa_campanhas(): void
    {
        $this->get(route('campanhas.index'))->assertRedirect(route('login'));
        $this->post(route('campanhas.criar'), ['name' => 'X'])->assertRedirect(route('login'));
    }

    public function test_usuario_cria_campanha_e_vira_mestre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('campanhas.criar'), [
                'name'        => 'A Vila da Folha',
                'description' => 'Campanha de teste',
            ])
            ->assertRedirect();

        $campaign = Campaign::first();

        $this->assertNotNull($campaign);
        $this->assertSame($user->id, $campaign->owner_id);
        $this->assertNotEmpty($campaign->invite_token);
        $this->assertDatabaseHas('campaign_user', [
            'campaign_id' => $campaign->id,
            'user_id'     => $user->id,
            'role'        => 'owner',
        ]);
    }

    public function test_criar_campanha_exige_nome(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('campanhas.criar'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_cada_campanha_recebe_token_unico(): void
    {
        $a = $this->makeCampaign();
        $b = $this->makeCampaign();

        $this->assertNotEquals($a->invite_token, $b->invite_token);
    }

    // -------------------------------------------------------------------------
    // Convite / entrar
    // -------------------------------------------------------------------------

    public function test_token_invalido_retorna_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('convite.ver', 'token-que-nao-existe'))
            ->assertNotFound();
    }

    public function test_jogador_entra_na_campanha_com_uma_ficha(): void
    {
        $campaign = $this->makeCampaign();
        $player   = User::factory()->create();
        $character = Character::factory()->create(['user_id' => $player->id]);

        $this->actingAs($player)
            ->post(route('convite.entrar', $campaign->invite_token), [
                'characters' => [$character->id],
            ])
            ->assertRedirect(route('campanhas.ver', $campaign));

        $this->assertDatabaseHas('campaign_user', [
            'campaign_id' => $campaign->id,
            'user_id'     => $player->id,
            'role'        => 'player',
        ]);
        $this->assertDatabaseHas('campaign_characters', [
            'campaign_id'  => $campaign->id,
            'character_id' => $character->id,
        ]);
    }

    public function test_jogador_entra_com_multiplas_fichas(): void
    {
        $campaign = $this->makeCampaign();
        $player   = User::factory()->create();
        $chars    = Character::factory()->count(3)->create(['user_id' => $player->id]);

        $this->actingAs($player)
            ->post(route('convite.entrar', $campaign->invite_token), [
                'characters' => $chars->pluck('id')->toArray(),
            ]);

        $this->assertCount(3, $campaign->fresh()->characters);
    }

    public function test_nao_adiciona_ficha_de_outro_usuario(): void
    {
        $campaign     = $this->makeCampaign();
        $player        = User::factory()->create();
        $alheia        = Character::factory()->create(); // pertence a outro usuário

        $this->actingAs($player)
            ->post(route('convite.entrar', $campaign->invite_token), [
                'characters' => [$alheia->id],
            ]);

        // Entrou como membro, mas a ficha alheia NÃO foi adicionada.
        $this->assertDatabaseHas('campaign_user', [
            'campaign_id' => $campaign->id,
            'user_id'     => $player->id,
        ]);
        $this->assertDatabaseMissing('campaign_characters', [
            'campaign_id'  => $campaign->id,
            'character_id' => $alheia->id,
        ]);
    }

    public function test_pode_entrar_sem_ficha(): void
    {
        $campaign = $this->makeCampaign();
        $player   = User::factory()->create();

        $this->actingAs($player)
            ->post(route('convite.entrar', $campaign->invite_token), [])
            ->assertRedirect(route('campanhas.ver', $campaign));

        $this->assertDatabaseHas('campaign_user', [
            'campaign_id' => $campaign->id,
            'user_id'     => $player->id,
            'role'        => 'player',
        ]);
    }

    public function test_entrar_duas_vezes_nao_duplica_ficha(): void
    {
        $campaign  = $this->makeCampaign();
        $player    = User::factory()->create();
        $character = Character::factory()->create(['user_id' => $player->id]);

        $payload = ['characters' => [$character->id]];

        $this->actingAs($player)->post(route('convite.entrar', $campaign->invite_token), $payload);
        $this->actingAs($player)->post(route('convite.entrar', $campaign->invite_token), $payload);

        $this->assertCount(1, $campaign->fresh()->characters);
        $this->assertDatabaseCount('campaign_user', 2); // mestre + jogador, sem duplicar
    }

    // -------------------------------------------------------------------------
    // Ver campanha (autorização de membro)
    // -------------------------------------------------------------------------

    public function test_nao_membro_nao_ve_a_campanha(): void
    {
        $campaign = $this->makeCampaign();
        $estranho = User::factory()->create();

        $this->actingAs($estranho)
            ->get(route('campanhas.ver', $campaign))
            ->assertForbidden();
    }

    public function test_membro_ve_a_campanha(): void
    {
        $campaign = $this->makeCampaign();
        $player   = User::factory()->create();
        $this->joinPlayer($campaign, $player);

        $this->actingAs($player)
            ->get(route('campanhas.ver', $campaign))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Mestre vê/edita ficha do jogador
    // -------------------------------------------------------------------------

    public function test_mestre_acessa_ficha_de_jogador_da_campanha(): void
    {
        $owner    = User::factory()->create();
        $campaign = $this->makeCampaign($owner);
        $player   = User::factory()->create();
        $character = Character::factory()->withSkills()->create(['user_id' => $player->id]);
        $this->joinPlayer($campaign, $player, [$character->id]);

        $this->actingAs($owner)
            ->get(route('fichas.editar', $character))
            ->assertOk()
            ->assertSeeLivewire(CharacterSheet::class);
    }

    public function test_mestre_edita_e_salva_ficha_do_jogador(): void
    {
        $owner     = User::factory()->create();
        $campaign  = $this->makeCampaign($owner);
        $player    = User::factory()->create();
        $character = Character::factory()->withSkills()->create(['user_id' => $player->id]);
        $this->joinPlayer($campaign, $player, [$character->id]);

        Livewire::actingAs($owner)
            ->test(CharacterSheet::class, ['character' => $character])
            ->set('name', 'Editado pelo Mestre')
            ->call('save')
            ->assertDispatched('saved');

        $this->assertDatabaseHas('characters', [
            'id'   => $character->id,
            'name' => 'Editado pelo Mestre',
        ]);
    }

    public function test_mestre_usa_autosave_da_ficha_do_jogador(): void
    {
        $owner     = User::factory()->create();
        $campaign  = $this->makeCampaign($owner);
        $player    = User::factory()->create();
        $character = Character::factory()->withSkills()->create(['user_id' => $player->id]);
        $this->joinPlayer($campaign, $player, [$character->id]);

        $this->actingAs($owner)
            ->postJson(route('fichas.autosave', $character), ['name' => 'Autosave do Mestre'])
            ->assertNoContent();

        $this->assertDatabaseHas('characters', [
            'id'   => $character->id,
            'name' => 'Autosave do Mestre',
        ]);
    }

    public function test_mestre_de_outra_campanha_nao_edita_ficha(): void
    {
        // Ficha está na campanha A; o mestre testado é de outra campanha B.
        $campaignA = $this->makeCampaign();
        $player    = User::factory()->create();
        $character = Character::factory()->withSkills()->create(['user_id' => $player->id]);
        $this->joinPlayer($campaignA, $player, [$character->id]);

        $outroMestre = User::factory()->create();
        $this->makeCampaign($outroMestre);

        $this->actingAs($outroMestre)
            ->get(route('fichas.editar', $character))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Banir jogador
    // -------------------------------------------------------------------------

    public function test_mestre_bane_jogador_e_remove_suas_fichas(): void
    {
        $owner     = User::factory()->create();
        $campaign  = $this->makeCampaign($owner);
        $player    = User::factory()->create();
        $character = Character::factory()->create(['user_id' => $player->id]);
        $this->joinPlayer($campaign, $player, [$character->id]);

        $this->actingAs($owner)
            ->delete(route('campanhas.banir-membro', [$campaign, $player]))
            ->assertRedirect(route('campanhas.ver', $campaign));

        $this->assertDatabaseMissing('campaign_user', [
            'campaign_id' => $campaign->id,
            'user_id'     => $player->id,
        ]);
        $this->assertDatabaseMissing('campaign_characters', [
            'campaign_id'  => $campaign->id,
            'character_id' => $character->id,
        ]);
        // A ficha em si continua existindo, só saiu da campanha.
        $this->assertDatabaseHas('characters', ['id' => $character->id]);
    }

    public function test_mestre_nao_pode_banir_a_si_mesmo(): void
    {
        $owner    = User::factory()->create();
        $campaign = $this->makeCampaign($owner);

        $this->actingAs($owner)
            ->delete(route('campanhas.banir-membro', [$campaign, $owner]))
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_user', [
            'campaign_id' => $campaign->id,
            'user_id'     => $owner->id,
        ]);
    }

    public function test_jogador_nao_pode_banir_ninguem(): void
    {
        $campaign = $this->makeCampaign();
        $player   = User::factory()->create();
        $outro    = User::factory()->create();
        $this->joinPlayer($campaign, $player);
        $this->joinPlayer($campaign, $outro);

        $this->actingAs($player)
            ->delete(route('campanhas.banir-membro', [$campaign, $outro]))
            ->assertForbidden();

        $this->assertDatabaseHas('campaign_user', [
            'campaign_id' => $campaign->id,
            'user_id'     => $outro->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Remover ficha / regenerar convite / excluir
    // -------------------------------------------------------------------------

    public function test_jogador_remove_a_propria_ficha_da_campanha(): void
    {
        $campaign  = $this->makeCampaign();
        $player    = User::factory()->create();
        $character = Character::factory()->create(['user_id' => $player->id]);
        $this->joinPlayer($campaign, $player, [$character->id]);

        $this->actingAs($player)
            ->delete(route('campanhas.remover-ficha', [$campaign, $character]))
            ->assertRedirect();

        $this->assertDatabaseMissing('campaign_characters', [
            'campaign_id'  => $campaign->id,
            'character_id' => $character->id,
        ]);
    }

    public function test_mestre_regenera_token_de_convite(): void
    {
        $owner    = User::factory()->create();
        $campaign = $this->makeCampaign($owner);
        $tokenAntigo = $campaign->invite_token;

        $this->actingAs($owner)
            ->post(route('campanhas.regenerar-convite', $campaign))
            ->assertRedirect(route('campanhas.ver', $campaign));

        $this->assertNotEquals($tokenAntigo, $campaign->fresh()->invite_token);
    }

    public function test_jogador_nao_regenera_token(): void
    {
        $campaign = $this->makeCampaign();
        $player   = User::factory()->create();
        $this->joinPlayer($campaign, $player);

        $this->actingAs($player)
            ->post(route('campanhas.regenerar-convite', $campaign))
            ->assertForbidden();
    }

    public function test_mestre_exclui_campanha(): void
    {
        $owner    = User::factory()->create();
        $campaign = $this->makeCampaign($owner);

        $this->actingAs($owner)
            ->delete(route('campanhas.excluir', $campaign))
            ->assertRedirect(route('campanhas.index'));

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_jogador_nao_exclui_campanha(): void
    {
        $campaign = $this->makeCampaign();
        $player   = User::factory()->create();
        $this->joinPlayer($campaign, $player);

        $this->actingAs($player)
            ->delete(route('campanhas.excluir', $campaign))
            ->assertForbidden();

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }
}
