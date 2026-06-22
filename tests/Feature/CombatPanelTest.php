<?php

namespace Tests\Feature;

use App\Events\CampaignEventBroadcast;
use App\Livewire\CombatPanel;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class CombatPanelTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Campaign,1:User,2:User} */
    private function campaign(): array
    {
        $master = User::factory()->create();
        $player = User::factory()->create();
        $campaign = Campaign::factory()->create(['owner_id' => $master->id]);
        $campaign->members()->attach($master->id, ['role' => 'owner']);
        $campaign->members()->attach($player->id, ['role' => 'player']);

        return [$campaign, $master, $player];
    }

    private function npc(Campaign $campaign, User $master, array $attrs = []): Character
    {
        return Character::factory()->withSkills()->create(array_merge([
            'user_id'     => $master->id,
            'campaign_id' => $campaign->id,
            'name'        => 'Vilão',
            'hp_current'  => 20, 'hp_max' => 20,
            'chakra_current' => 20, 'chakra_max' => 20,
        ], $attrs));
    }

    public function test_jogador_nao_acessa_combate(): void
    {
        [$campaign, , $player] = $this->campaign();

        Livewire::actingAs($player)
            ->test(CombatPanel::class, ['campaignId' => $campaign->id])
            ->assertForbidden();
    }

    public function test_mestre_adiciona_e_remove_combatente(): void
    {
        [$campaign, $master] = $this->campaign();
        $npc = $this->npc($campaign, $master);

        $component = Livewire::actingAs($master)
            ->test(CombatPanel::class, ['campaignId' => $campaign->id]);

        $component->call('addCombatant', $npc->id);
        $this->assertContains($npc->id, $campaign->fresh()->combat);
        $component->assertSee('Vilão');

        $component->call('removeCombatant', $npc->id);
        $this->assertNotContains($npc->id, $campaign->fresh()->combat ?? []);
    }

    public function test_ajustar_vida_e_chakra_persiste(): void
    {
        [$campaign, $master] = $this->campaign();
        $npc = $this->npc($campaign, $master);

        $component = Livewire::actingAs($master)
            ->test(CombatPanel::class, ['campaignId' => $campaign->id]);

        $component->call('adjustHp', $npc->id, -5);
        $this->assertSame(15, $npc->fresh()->hp_current);

        // Não desce de 0
        $component->call('adjustHp', $npc->id, -999);
        $this->assertSame(0, $npc->fresh()->hp_current);

        $component->call('adjustChakra', $npc->id, -3);
        $this->assertSame(17, $npc->fresh()->chakra_current);
    }

    public function test_so_aceita_npc_da_propria_campanha(): void
    {
        [$campaign, $master] = $this->campaign();
        $outra = Campaign::factory()->create(['owner_id' => $master->id]);
        $npcOutra = $this->npc($outra, $master);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($master)
            ->test(CombatPanel::class, ['campaignId' => $campaign->id])
            ->call('addCombatant', $npcOutra->id);
    }

    public function test_share_roll_transmite_para_o_feed(): void
    {
        Event::fake([CampaignEventBroadcast::class]);
        [$campaign, $master] = $this->campaign();
        $npc = $this->npc($campaign, $master);

        Livewire::actingAs($master)
            ->test(CombatPanel::class, ['campaignId' => $campaign->id])
            ->call('shareRoll', $npc->id, 'Força → 18 (d20: 15 +3)', ['kind' => 'attr']);

        $this->assertDatabaseHas('campaign_events', [
            'campaign_id'  => $campaign->id,
            'character_id' => $npc->id,
            'actor'        => 'Vilão',
            'message'      => 'Força → 18 (d20: 15 +3)',
        ]);
        Event::assertDispatched(CampaignEventBroadcast::class);
    }
}
