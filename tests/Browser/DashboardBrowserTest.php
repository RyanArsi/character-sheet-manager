<?php

namespace Tests\Browser;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DashboardBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_editar_ficha_pelo_dashboard_e_voltar(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $character = Character::factory()->withSkills()->create([
            'user_id' => $user->id,
            'name'    => 'Minha Ficha',
        ]);

        $this->browse(function (Browser $browser) use ($user, $character) {
            $browser->loginAs($user)
                ->visit(route('dashboard'))
                ->waitFor("@dash-ficha-{$character->id}")
                ->click("@dash-ficha-{$character->id}")
                // abriu o editor com a tela de origem registrada
                ->waitFor('@sheet-back', 10)
                ->assertQueryStringHas('from')
                ->click('@sheet-back')
                // voltou para o dashboard
                ->waitForLocation('/dashboard', 10)
                ->assertSee('Minhas Fichas');
        });
    }
}
