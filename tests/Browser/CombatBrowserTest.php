<?php

namespace Tests\Browser;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CombatBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @return array{0:User,1:Campaign,2:Character} [mestre, campanha, npc] */
    private function setup_combat(bool $inCombat = false, array $npcAttrs = []): array
    {
        $master = User::factory()->create(['password' => bcrypt('password')]);
        $campaign = Campaign::factory()->create(['owner_id' => $master->id]);
        $campaign->members()->attach($master->id, ['role' => 'owner']);

        $npc = Character::factory()->withSkills()->create(array_merge([
            'user_id'        => $master->id,
            'campaign_id'    => $campaign->id,
            'name'           => 'Vilao Teste',
            'hp_current'     => 20, 'hp_max' => 20,
            'chakra_current' => 20, 'chakra_max' => 20,
            'forca'          => 12,
        ], $npcAttrs));

        if ($inCombat) {
            $campaign->update(['combat' => [$npc->id]]);
        }

        return [$master, $campaign, $npc];
    }

    private function openCombat(Browser $browser, User $master, Campaign $campaign): Browser
    {
        return $browser->loginAs($master)
            ->visit(route('campanhas.ver', $campaign))
            ->waitUntil("typeof window.Alpine !== 'undefined'", 10)
            ->waitFor('@camp-tab-combate')
            ->click('@camp-tab-combate')
            ->pause(400);
    }

    public function test_mestre_adiciona_combatente_e_ve_o_card(): void
    {
        [$master, $campaign, $npc] = $this->setup_combat();

        $this->browse(function (Browser $browser) use ($master, $campaign, $npc) {
            $this->openCombat($browser, $master, $campaign)
                ->waitFor('@combat-add-select')
                ->select('@combat-add-select', (string) $npc->id)
                ->waitFor('@combat-add')
                ->click('@combat-add')
                ->waitFor("@combat-card-{$npc->id}", 5)
                ->assertSeeIn("@combat-card-{$npc->id}", 'Vilao Teste');
        });
    }

    public function test_ajustar_vida_diminui_o_valor(): void
    {
        [$master, $campaign, $npc] = $this->setup_combat(inCombat: true);

        $this->browse(function (Browser $browser) use ($master, $campaign, $npc) {
            $this->openCombat($browser, $master, $campaign)
                ->waitFor("@combat-card-{$npc->id}")
                ->assertSeeIn("@combat-card-{$npc->id}", '20/20')
                ->click("@combat-hp-down-{$npc->id}")
                ->pause(800)
                ->assertSeeIn("@combat-card-{$npc->id}", '15/20');
        });
    }

    public function test_rolar_iniciativa_entra_na_ordem(): void
    {
        [$master, $campaign, $npc] = $this->setup_combat(inCombat: true);

        $this->browse(function (Browser $browser) use ($master, $campaign, $npc) {
            $this->openCombat($browser, $master, $campaign)
                ->waitFor("@combat-init-{$npc->id}")
                ->click("@combat-init-{$npc->id}")
                ->pause(800)
                ->assertSeeIn('@combat-initiative', 'Vilao Teste');
        });
    }

    public function test_clicar_atributo_mostra_toast(): void
    {
        [$master, $campaign, $npc] = $this->setup_combat(inCombat: true);

        $this->browse(function (Browser $browser) use ($master, $campaign, $npc) {
            $this->openCombat($browser, $master, $campaign)
                ->waitFor("@combat-expand-{$npc->id}")
                ->click("@combat-expand-{$npc->id}")
                ->waitFor("@combat-sub-status-{$npc->id}")
                ->click("@combat-sub-status-{$npc->id}")
                ->waitFor("@combat-stat-{$npc->id}-forca")
                ->click("@combat-stat-{$npc->id}-forca")
                ->waitFor('#combat-roll-toast', 5)
                ->assertVisible('#combat-roll-toast')
                ->assertSeeIn('#combat-roll-toast', 'total');
        });
    }

    public function test_adicionar_condicao_mostra_chip(): void
    {
        [$master, $campaign, $npc] = $this->setup_combat(inCombat: true);

        $this->browse(function (Browser $browser) use ($master, $campaign, $npc) {
            $this->openCombat($browser, $master, $campaign)
                ->waitFor("@combat-init-{$npc->id}")
                ->click("@combat-init-{$npc->id}")
                ->pause(800);

            // pega o id da entrada criada para selecionar como alvo
            $entryId = $campaign->fresh()->initiative['entries'][0]['id'];

            $browser->waitFor('@combat-cond-name')
                ->type('@combat-cond-name', 'Atordoado')
                ->select('@combat-cond-target', $entryId)
                ->click('@combat-add-condition')
                ->pause(800)
                ->assertSeeIn('@combat-initiative', 'Atordoado');
        });
    }
}
