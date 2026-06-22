<?php

namespace Tests\Feature;

use App\Livewire\CampaignLibrary;
use App\Models\Action;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignLibraryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Campaign,1:User,2:User,3:User} [campanha, mestre, jogador1, jogador2] */
    private function campaignWithMembers(): array
    {
        $master  = User::factory()->create();
        $player1 = User::factory()->create();
        $player2 = User::factory()->create();

        $campaign = Campaign::factory()->create(['owner_id' => $master->id]);
        $campaign->members()->attach($master->id, ['role' => 'owner']);
        $campaign->members()->attach($player1->id, ['role' => 'player']);
        $campaign->members()->attach($player2->id, ['role' => 'player']);

        return [$campaign, $master, $player1, $player2];
    }

    public function test_estranho_nao_acessa_a_biblioteca(): void
    {
        [$campaign] = $this->campaignWithMembers();
        $intruder = User::factory()->create();

        Livewire::actingAs($intruder)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->assertForbidden();
    }

    public function test_biblioteca_mostra_itens_dos_membros(): void
    {
        [$campaign, $master, $player1] = $this->campaignWithMembers();
        $player1->jutsus()->create(['name' => 'Rasengan Visivel']);

        Livewire::actingAs($master)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->assertSee('Rasengan Visivel');
    }

    public function test_item_oculto_some_para_jogador_comum_mas_aparece_para_criador_e_mestre(): void
    {
        [$campaign, $master, $player1, $player2] = $this->campaignWithMembers();
        $player1->jutsus()->create(['name' => 'TecnicaSecreta', 'hidden' => true]);

        // Outro jogador não vê
        Livewire::actingAs($player2)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->assertDontSee('TecnicaSecreta');

        // Criador vê
        Livewire::actingAs($player1)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->assertSee('TecnicaSecreta');

        // Mestre vê
        Livewire::actingAs($master)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->assertSee('TecnicaSecreta');
    }

    public function test_mestre_pode_ocultar_item_de_jogador(): void
    {
        [$campaign, $master, $player1] = $this->campaignWithMembers();
        $jutsu = $player1->jutsus()->create(['name' => 'Chidori']);

        Livewire::actingAs($master)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->call('toggleHidden', 'jutsus', $jutsu->id);

        $this->assertTrue($jutsu->fresh()->hidden);
    }

    public function test_criador_pode_ocultar_proprio_item(): void
    {
        [$campaign, , $player1] = $this->campaignWithMembers();
        $jutsu = $player1->jutsus()->create(['name' => 'Kage Bunshin']);

        Livewire::actingAs($player1)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->call('toggleHidden', 'jutsus', $jutsu->id);

        $this->assertTrue($jutsu->fresh()->hidden);
    }

    public function test_jogador_comum_nao_pode_ocultar_item_alheio(): void
    {
        [$campaign, , $player1, $player2] = $this->campaignWithMembers();
        $jutsu = $player1->jutsus()->create(['name' => 'Katon']);

        Livewire::actingAs($player2)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->call('toggleHidden', 'jutsus', $jutsu->id)
            ->assertForbidden();

        $this->assertFalse($jutsu->fresh()->hidden);
    }

    public function test_membro_cria_acao(): void
    {
        [$campaign, , $player1] = $this->campaignWithMembers();

        Livewire::actingAs($player1)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->call('startCreate', 'actions')
            ->set('fName', 'Escalar muro')
            ->set('fTags', 'físico, atletismo')
            ->set('fTest', 'd20+agilidade')
            ->call('save')
            ->assertHasNoErrors();

        $action = Action::firstWhere('name', 'Escalar muro');
        $this->assertNotNull($action);
        $this->assertSame($player1->id, $action->user_id);
        $this->assertSame(['físico', 'atletismo'], $action->tags);
    }

    public function test_membro_cria_jutsu_na_biblioteca(): void
    {
        [$campaign, , $player1] = $this->campaignWithMembers();

        Livewire::actingAs($player1)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->call('startCreate', 'jutsus')
            ->set('fName', 'Bola de Fogo')
            ->set('fChakra', '10')
            ->set('fTest', 'd20+ninjutsu')
            ->set('fDamage', '4d6')
            ->call('save')
            ->assertHasNoErrors();

        $jutsu = \App\Models\Jutsu::firstWhere('name', 'Bola de Fogo');
        $this->assertNotNull($jutsu);
        $this->assertSame($player1->id, $jutsu->user_id);
        $this->assertSame('10', $jutsu->chakra_cost);
        $this->assertSame('4d6', $jutsu->damage_dice);
    }

    public function test_so_o_criador_edita_o_jutsu(): void
    {
        [$campaign, $master, $player1] = $this->campaignWithMembers();
        $jutsu = $player1->jutsus()->create(['name' => 'Original']);

        // Mestre não é o criador: startEdit não acha o jutsu dele
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($master)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->call('startEdit', 'jutsus', $jutsu->id);
    }

    public function test_filtro_por_tipo_esconde_outros_tipos(): void
    {
        [$campaign, $master, $player1] = $this->campaignWithMembers();
        $player1->jutsus()->create(['name' => 'JutsuUnico']);
        $player1->talents()->create(['name' => 'TalentoUnico']);

        Livewire::actingAs($master)
            ->test(CampaignLibrary::class, ['campaignId' => $campaign->id])
            ->assertSee('JutsuUnico')
            ->assertSee('TalentoUnico')
            ->call('setType', 'jutsus')
            ->assertSee('JutsuUnico')
            ->assertDontSee('TalentoUnico');
    }
}
