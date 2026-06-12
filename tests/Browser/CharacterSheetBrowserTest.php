<?php

namespace Tests\Browser;

use App\Models\Character;
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
}
