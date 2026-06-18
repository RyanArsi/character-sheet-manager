<?php

namespace Tests\Feature;

use App\Livewire\JutsuPanel;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Jutsu;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class JutsuPanelTest extends TestCase
{
    use RefreshDatabase;

    private function userWithCharacter(): array
    {
        $user = User::factory()->create();
        $character = Character::factory()->create(['user_id' => $user->id]);

        return [$user, $character];
    }

    public function test_estranho_nao_monta_o_painel(): void
    {
        [, $character] = $this->userWithCharacter();
        $intruder = User::factory()->create();

        $this->actingAs($intruder);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->assertForbidden();
    }

    public function test_criar_jutsu_atribui_a_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Rasengan')
            ->set('tagsInput', 'vento, ofensivo')
            ->set('chakra_cost', '15')
            ->call('save')
            ->assertHasNoErrors();

        $jutsu = Jutsu::firstWhere('name', 'Rasengan');

        $this->assertNotNull($jutsu);
        $this->assertSame($user->id, $jutsu->user_id);
        $this->assertSame(['vento', 'ofensivo'], $jutsu->tags);
        $this->assertTrue($character->jutsus()->where('jutsus.id', $jutsu->id)->exists());
    }

    public function test_nome_e_obrigatorio(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_jogador_atribui_jutsu_de_membro_da_campanha(): void
    {
        // Mestre cria um jutsu; jogador da mesma campanha consegue atribuir à sua ficha.
        $master = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::create(['owner_id' => $master->id, 'name' => 'Vila da Folha']);
        $campaign->members()->attach($master->id, ['role' => 'owner']);
        $campaign->members()->attach($player->id, ['role' => 'player']);

        $character = Character::factory()->create(['user_id' => $player->id]);
        $campaign->characters()->attach($character->id);

        $jutsuDoMestre = Jutsu::create(['user_id' => $master->id, 'name' => 'Katon']);

        $this->actingAs($player);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('assign', $jutsuDoMestre->id);

        $this->assertTrue($character->jutsus()->where('jutsus.id', $jutsuDoMestre->id)->exists());
    }

    public function test_nao_atribui_jutsu_fora_do_pool(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $estranho = User::factory()->create();
        $jutsuAlheio = Jutsu::create(['user_id' => $estranho->id, 'name' => 'Secreto']);

        $this->actingAs($user);

        try {
            Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
                ->call('assign', $jutsuAlheio->id);
            $this->fail('Deveria ter recusado um jutsu fora do pool.');
        } catch (ModelNotFoundException) {
            // esperado: jutsu fora do pool não é encontrado
        }

        $this->assertFalse($character->jutsus()->where('jutsus.id', $jutsuAlheio->id)->exists());
    }

    public function test_criar_jutsu_registra_tags_no_dicionario(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Katon')
            ->set('tagsInput', 'fogo, ofensivo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tags', ['name' => 'fogo']);
        $this->assertDatabaseHas('tags', ['name' => 'ofensivo']);
    }

    public function test_criar_jutsu_salva_dados_dano_e_volume(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Chidori')
            ->set('test_dice', 'd20+[ninjutsu]')
            ->set('damage_dice', '4d6+[forca]')
            ->set('volume', 70)
            ->call('save')
            ->assertHasNoErrors();

        $jutsu = Jutsu::firstWhere('name', 'Chidori');

        $this->assertSame('d20+[ninjutsu]', $jutsu->test_dice);
        $this->assertSame('4d6+[forca]', $jutsu->damage_dice);
        $this->assertSame(70, $jutsu->volume);
    }

    public function test_upload_de_midia_e_armazenado(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $audio = UploadedFile::fake()->create('jutsu.mp3', 200, 'audio/mpeg');

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Rasengan')
            ->set('media', $audio)
            ->call('save')
            ->assertHasNoErrors();

        $jutsu = Jutsu::firstWhere('name', 'Rasengan');

        $this->assertNotNull($jutsu->media);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($jutsu->media);
    }

    public function test_editar_jutsu_carrega_campos_de_rolagem(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $jutsu = Jutsu::create([
            'user_id'     => $user->id,
            'name'        => 'Katon',
            'test_dice'   => 'd20+[ninjutsu]',
            'damage_dice' => '3d6',
            'volume'      => 40,
        ]);

        $this->actingAs($user);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('startEdit', $jutsu->id)
            ->assertSet('test_dice', 'd20+[ninjutsu]')
            ->assertSet('damage_dice', '3d6')
            ->assertSet('volume', 40);
    }

    public function test_ver_e_editar_significado_da_tag(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('openTag', 'fogo')
            ->assertSet('tagName', 'fogo')
            ->assertSet('editingTag', false)
            ->call('editTag')
            ->assertSet('editingTag', true)
            ->set('tagDescription', 'Jutsus de elemento fogo.')
            ->call('saveTag')
            ->assertSet('editingTag', false);

        $this->assertDatabaseHas('tags', [
            'name'        => 'fogo',
            'description' => 'Jutsus de elemento fogo.',
        ]);
    }

    public function test_exporta_jutsus_da_biblioteca_em_json(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Jutsu::create(['user_id' => $user->id, 'name' => 'Rasengan', 'tags' => ['vento']]);

        $this->actingAs($user);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->call('exportJutsus')
            ->assertFileDownloaded();
    }

    public function test_importa_jutsus_de_json(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $json = json_encode([
            'jutsus' => [
                ['name' => 'Chidori', 'tags' => ['raio'], 'chakra_cost' => '20', 'description' => 'Corta.'],
                ['name' => 'Kage Bunshin', 'tags' => ['suporte']],
            ],
            'tags' => [
                ['name' => 'raio', 'description' => 'Elemento relâmpago.'],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('jutsus.json', $json);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importJutsus')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jutsus', ['user_id' => $user->id, 'name' => 'Chidori']);
        $this->assertDatabaseHas('jutsus', ['user_id' => $user->id, 'name' => 'Kage Bunshin']);
        $this->assertDatabaseHas('tags', ['name' => 'raio', 'description' => 'Elemento relâmpago.']);
    }

    public function test_import_nao_duplica_jutsu_existente(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Jutsu::create(['user_id' => $user->id, 'name' => 'Chidori']);
        $this->actingAs($user);

        $json = json_encode(['jutsus' => [['name' => 'Chidori'], ['name' => 'Novo']]]);
        $file = UploadedFile::fake()->createWithContent('jutsus.json', $json);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importJutsus')
            ->assertHasNoErrors();

        $this->assertSame(1, Jutsu::where('user_id', $user->id)->where('name', 'Chidori')->count());
        $this->assertDatabaseHas('jutsus', ['user_id' => $user->id, 'name' => 'Novo']);
    }

    public function test_import_rejeita_arquivo_invalido(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent('lixo.json', 'isto não é json');

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importJutsus')
            ->assertHasErrors('importFile');
    }

    public function test_filtro_por_tag(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Jutsu::create(['user_id' => $user->id, 'name' => 'Bola de Fogo', 'tags' => ['fogo']]);
        Jutsu::create(['user_id' => $user->id, 'name' => 'Clone', 'tags' => ['suporte']]);

        $this->actingAs($user);

        Livewire::test(JutsuPanel::class, ['characterId' => $character->id])
            ->set('view', 'browse')
            ->call('toggleFilter', 'fogo')
            ->assertSee('Bola de Fogo')
            ->assertDontSee('Clone');
    }
}
