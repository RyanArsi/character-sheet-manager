<?php

namespace Tests\Browser;

use App\Models\Character;
use App\Models\Jutsu;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CharacterSheetBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private function createUserWithCharacter(): array
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);
        $character = Character::factory()->withSkills()->create([
            'user_id' => $user->id,
            'forca'   => 12,
            'ninjutsu' => 3,
        ]);

        return [$user, $character];
    }

    private function visitSheet(Browser $browser, User $user, Character $character): Browser
    {
        return $browser->loginAs($user)
            ->visit(route('fichas.editar', $character))
            ->waitUntil("typeof window.Alpine !== 'undefined' && document.querySelector('[x-data]')?._x_dataStack?.length > 0", 10)
            ->pause(500);
    }

    public function test_clicar_atributo_exibe_toast_com_resultado(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@roll-forca')
                ->waitFor('#roll-toast', 5)
                ->assertVisible('#roll-toast')
                ->assertSeeIn('#roll-toast', 'dado');
        });
    }

    public function test_toast_exibe_resultado_numerico(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@roll-forca')
                ->waitFor('#roll-toast', 5)
                ->assertSeeIn('#roll-toast', '=');
        });
    }

    public function test_historico_vazio_ao_abrir(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@history-btn')
                ->pause(500)
                ->assertSeeIn('#roll-history-drawer', 'Nenhuma rolagem');
        });
    }

    public function test_rolagem_aparece_no_historico(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@roll-forca')
                ->waitFor('#roll-toast', 5)
                ->click('@history-btn')
                ->waitUntil("document.querySelectorAll('#roll-history-drawer .bg-gray-800').length > 0", 5)
                ->assertSeeIn('#roll-history-drawer', '+');
        });
    }

    public function test_multiplas_rolagens_acumulam_no_historico(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@roll-forca')
                ->waitFor('#roll-toast', 5)
                ->click('@roll-ninjutsu')
                ->waitFor('#roll-toast', 5)
                ->click('@history-btn')
                ->waitUntil("document.querySelectorAll('#roll-history-drawer .bg-gray-800').length >= 2", 5)
                ->assertSeeIn('#roll-history-drawer', 'Ninjutsu')
                ->assertScript("return document.querySelectorAll('#roll-history-drawer .bg-gray-800').length >= 2");
        });
    }

    public function test_fechar_historico_esconde_drawer(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@history-btn')
                ->pause(500)
                ->assertVisible('#roll-history-drawer')
                ->click('@history-close-btn')
                ->pause(500)
                ->assertMissing('#roll-history-drawer');
        });
    }

    // ===== Aba Dados — rolador por notação =====

    public function test_rolar_notacao_simples_exibe_total(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@tab-dados')
                ->waitFor('@dice-input')
                ->type('@dice-input', 'd1') // d1 sempre resulta 1
                ->click('@dice-roll-btn')
                ->waitFor('@dice-result')
                ->assertSeeIn('@dice-total', '1');
        });
    }

    public function test_expressao_soma_atributo_da_ficha(): void
    {
        // forca = 12 (factory)  →  d1 + [forca] = 13
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@tab-dados')
                ->waitFor('@dice-input')
                ->type('@dice-input', 'd1+[forca]')
                ->click('@dice-roll-btn')
                ->waitFor('@dice-result')
                ->assertSeeIn('@dice-total', '13')
                ->assertSeeIn('@dice-result', 'forca');
        });
    }

    public function test_referencia_de_pericia_soma_valor_e_treinamento(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $character = Character::factory()->withSkills()->create(['user_id' => $user->id]);

        // Os dois modificadores da perícia: valor 3 + treinamento nível 2 (×2 = 4) = bônus 7
        // d1 + [bukijutsu] = 1 + 7 = 8
        $character->skills()->where('name', 'Bukijutsu')->update([
            'value'          => 3,
            'trained'        => true,
            'training_level' => 2,
        ]);

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@tab-dados')
                ->waitFor('@dice-input')
                ->type('@dice-input', 'd1+[bukijutsu]')
                ->click('@dice-roll-btn')
                ->waitFor('@dice-result')
                ->assertSeeIn('@dice-total', '8');
        });
    }

    public function test_referencia_inexistente_exibe_erro(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $this->visitSheet($browser, $user, $character)
                ->click('@tab-dados')
                ->waitFor('@dice-input')
                ->type('@dice-input', 'd1+[inexistente]')
                ->click('@dice-roll-btn')
                ->waitFor('@dice-error')
                ->assertSeeIn('@dice-error', 'Não encontrei');
        });
    }

    // ===== Uso de jutsu (rolagem + chakra + configuração) =====

    /** Visita a ficha com localStorage limpo (jutsuCfg persiste por ficha e os ids se repetem entre testes). */
    private function visitSheetClean(Browser $browser, User $user, Character $character): Browser
    {
        $browser->loginAs($user)
            ->visit(route('fichas.editar', $character))
            ->script('localStorage.clear()');

        return $browser->refresh()
            ->waitUntil("typeof window.Alpine !== 'undefined' && document.querySelector('[x-data]')?._x_dataStack?.length > 0", 10)
            ->pause(500);
    }

    private function assignedJutsu(Character $character, array $attrs = []): Jutsu
    {
        $jutsu = Jutsu::create(array_merge([
            'user_id' => $character->user_id,
            'name'    => 'Chidori',
        ], $attrs));

        $character->jutsus()->attach($jutsu->id);

        return $jutsu;
    }

    public function test_usar_jutsu_rola_teste_e_mostra_chakra_no_toast(): void
    {
        [$user, $character] = $this->createUserWithCharacter(); // forca = 12
        $jutsu = $this->assignedJutsu($character, [
            'test_dice'   => 'd1+[forca]', // 1 + 12 = 13
            'chakra_cost' => '5',
        ]);

        $this->browse(function (Browser $browser) use ($user, $character, $jutsu) {
            $this->visitSheetClean($browser, $user, $character)
                ->click('@tab-jutsus')
                ->waitFor('@jutsu-use-'.$jutsu->id)
                ->click('@jutsu-use-'.$jutsu->id)
                ->waitFor('@jutsu-toast')
                ->assertSeeIn('@jutsu-toast', '13')
                ->assertSeeIn('@jutsu-toast', 'chakra');
        });
    }

    public function test_engrenagem_desativa_rolagem_de_teste(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $jutsu = $this->assignedJutsu($character, [
            'test_dice'   => 'd1+[forca]', // daria 13 se o teste rolasse
            'chakra_cost' => '5',
        ]);

        $this->browse(function (Browser $browser) use ($user, $character, $jutsu) {
            $this->visitSheetClean($browser, $user, $character)
                ->click('@tab-jutsus')
                ->waitFor('@jutsu-config')
                ->click('@jutsu-config')
                ->waitFor('@cfg-test')
                ->click('@cfg-test')        // desmarca "rolar teste"
                ->click('@jutsu-config')    // fecha o popover
                ->click('@jutsu-use-'.$jutsu->id)
                ->waitFor('@jutsu-toast')
                ->assertSeeIn('@jutsu-toast', 'chakra')
                ->assertDontSeeIn('@jutsu-toast', '13');
        });
    }

    public function test_usar_talento_rola_teste_e_mostra_chakra_no_toast(): void
    {
        [$user, $character] = $this->createUserWithCharacter(); // forca = 12
        $talent = Talent::create([
            'user_id'     => $character->user_id,
            'name'        => 'Golpe Forte',
            'test_dice'   => 'd1+[forca]', // 1 + 12 = 13
            'chakra_cost' => '3',
        ]);
        $character->talents()->attach($talent->id);

        $this->browse(function (Browser $browser) use ($user, $character, $talent) {
            $this->visitSheetClean($browser, $user, $character)
                ->click('@tab-talentos')
                ->waitFor('@talent-use-'.$talent->id)
                ->click('@talent-use-'.$talent->id)
                ->waitFor('@jutsu-toast')
                ->assertSeeIn('@jutsu-toast', '13')
                ->assertSeeIn('@jutsu-toast', 'chakra');
        });
    }
}
