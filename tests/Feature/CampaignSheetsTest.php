<?php

namespace Tests\Feature;

use App\Livewire\CampaignSheets;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignSheetsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Campaign,1:User,2:User} [campanha, mestre, jogador] */
    private function campaign(): array
    {
        $master = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::factory()->create(['owner_id' => $master->id]);
        $campaign->members()->attach($master->id, ['role' => 'owner']);
        $campaign->members()->attach($player->id, ['role' => 'player']);

        return [$campaign, $master, $player];
    }

    public function test_jogador_nao_acessa_as_fichas_do_mestre(): void
    {
        [$campaign, , $player] = $this->campaign();

        Livewire::actingAs($player)
            ->test(CampaignSheets::class, ['campaignId' => $campaign->id])
            ->assertForbidden();
    }

    public function test_mestre_cria_grupo(): void
    {
        [$campaign, $master] = $this->campaign();

        Livewire::actingAs($master)
            ->test(CampaignSheets::class, ['campaignId' => $campaign->id])
            ->set('newGroupName', 'Vilões')
            ->call('createGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('campaign_sheet_groups', [
            'campaign_id' => $campaign->id,
            'name'        => 'Vilões',
        ]);
    }

    public function test_mestre_cria_ficha_de_npc_e_e_redirecionado(): void
    {
        [$campaign, $master] = $this->campaign();
        $group = $campaign->sheetGroups()->create(['name' => 'NPCs']);

        Livewire::actingAs($master)
            ->test(CampaignSheets::class, ['campaignId' => $campaign->id])
            ->call('createSheet', $group->id)
            ->assertRedirect();

        $sheet = Character::where('campaign_id', $campaign->id)->first();

        $this->assertNotNull($sheet);
        $this->assertSame($master->id, $sheet->user_id);
        $this->assertSame($group->id, $sheet->sheet_group_id);
        // Skills semeadas como numa ficha normal
        $this->assertSame(count(\App\Support\SkillDefinitions::ALL), $sheet->skills()->count());
    }

    public function test_ficha_de_npc_nao_aparece_para_jogador_nem_no_dashboard(): void
    {
        [$campaign, $master] = $this->campaign();
        $npc = Character::create(['user_id' => $master->id, 'campaign_id' => $campaign->id, 'name' => 'Vilão']);

        // Dashboard do mestre só lista fichas pessoais (campaign_id null)
        $personal = $master->characters()->whereNull('campaign_id')->pluck('id');
        $this->assertNotContains($npc->id, $personal->all());

        // Jogador não gerencia
        $player = $campaign->members()->where('role', 'player')->first();
        $this->assertFalse($npc->canBeManagedBy($player));
        $this->assertTrue($npc->canBeManagedBy($master));
    }

    public function test_npc_compartilha_e_acessa_a_campanha(): void
    {
        [$campaign, $master] = $this->campaign();
        $npc = Character::create(['user_id' => $master->id, 'campaign_id' => $campaign->id, 'name' => 'Chefe']);

        $this->assertTrue($npc->isInCampaign($campaign->id));
        $this->assertEqualsCanonicalizing([$campaign->id], $npc->relatedCampaignIds()->all());
    }

    public function test_excluir_grupo_mantem_fichas_sem_grupo(): void
    {
        [$campaign, $master] = $this->campaign();
        $group = $campaign->sheetGroups()->create(['name' => 'Temp']);
        $npc = Character::create(['user_id' => $master->id, 'campaign_id' => $campaign->id, 'sheet_group_id' => $group->id, 'name' => 'X']);

        Livewire::actingAs($master)
            ->test(CampaignSheets::class, ['campaignId' => $campaign->id])
            ->call('deleteGroup', $group->id);

        $this->assertDatabaseMissing('campaign_sheet_groups', ['id' => $group->id]);
        $this->assertNull($npc->fresh()->sheet_group_id);
        $this->assertNotNull($npc->fresh());
    }

    public function test_mover_e_excluir_ficha(): void
    {
        [$campaign, $master] = $this->campaign();
        $group = $campaign->sheetGroups()->create(['name' => 'Vilões']);
        $npc = Character::create(['user_id' => $master->id, 'campaign_id' => $campaign->id, 'name' => 'Z']);

        $component = Livewire::actingAs($master)
            ->test(CampaignSheets::class, ['campaignId' => $campaign->id]);

        $component->call('moveSheet', $npc->id, $group->id);
        $this->assertSame($group->id, $npc->fresh()->sheet_group_id);

        $component->call('deleteSheet', $npc->id);
        $this->assertDatabaseMissing('characters', ['id' => $npc->id]);
    }
}
