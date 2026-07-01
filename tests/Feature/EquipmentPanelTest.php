<?php

namespace Tests\Feature;

use App\Livewire\EquipmentPanel;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class EquipmentPanelTest extends TestCase
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

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->assertForbidden();
    }

    public function test_criar_equipamento_atribui_a_ficha_na_mochila(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Katana')
            ->set('tagsInput', 'arma, lâmina')
            ->set('space', 3)
            ->call('save')
            ->assertHasNoErrors();

        $equipment = Equipment::firstWhere('name', 'Katana');

        $this->assertNotNull($equipment);
        $this->assertSame($user->id, $equipment->user_id);
        $this->assertSame(3, $equipment->space);
        $this->assertSame(['Arma', 'Lâmina'], $equipment->tags);

        // Atribuído à ficha, no local padrão "mochila"
        $pivot = $character->equipments()->where('equipments.id', $equipment->id)->first();
        $this->assertNotNull($pivot);
        $this->assertSame('mochila', $pivot->pivot->location);
    }

    public function test_nome_e_obrigatorio(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_espaco_deve_ser_inteiro(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Item')
            ->set('space', 'abc')
            ->call('save')
            ->assertHasErrors(['space']);
    }

    public function test_jogador_atribui_equipamento_de_membro_da_campanha(): void
    {
        $master = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::create(['owner_id' => $master->id, 'name' => 'Vila da Folha']);
        $campaign->members()->attach($master->id, ['role' => 'owner']);
        $campaign->members()->attach($player->id, ['role' => 'player']);

        $character = Character::factory()->create(['user_id' => $player->id]);
        $campaign->characters()->attach($character->id);

        $itemDoMestre = Equipment::create(['user_id' => $master->id, 'name' => 'Kunai']);

        $this->actingAs($player);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('assign', $itemDoMestre->id);

        $this->assertTrue($character->equipments()->where('equipments.id', $itemDoMestre->id)->exists());
    }

    public function test_nao_atribui_equipamento_fora_do_pool(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $estranho = User::factory()->create();
        $itemAlheio = Equipment::create(['user_id' => $estranho->id, 'name' => 'Secreto']);

        $this->actingAs($user);

        try {
            Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
                ->call('assign', $itemAlheio->id);
            $this->fail('Deveria ter recusado um equipamento fora do pool.');
        } catch (ModelNotFoundException) {
            // esperado
        }

        $this->assertFalse($character->equipments()->where('equipments.id', $itemAlheio->id)->exists());
    }

    public function test_mover_equipamento_de_local(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $item = Equipment::create(['user_id' => $user->id, 'name' => 'Pergaminho de Invocação']);
        $character->equipments()->attach($item->id); // default mochila

        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('setLocation', $item->id, 'pergaminhos');

        $pivot = $character->equipments()->where('equipments.id', $item->id)->first();
        $this->assertSame('pergaminhos', $pivot->pivot->location);
    }

    public function test_local_invalido_e_ignorado(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $item = Equipment::create(['user_id' => $user->id, 'name' => 'Corda']);
        $character->equipments()->attach($item->id);

        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('setLocation', $item->id, 'bolso');

        $pivot = $character->equipments()->where('equipments.id', $item->id)->first();
        $this->assertSame('mochila', $pivot->pivot->location); // inalterado
    }

    public function test_limites_de_espaco_persistem_na_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->set('limitMochila', 10)
            ->set('limitCarregando', 4)
            ->set('limitPergaminhos', 6);

        $character->refresh();
        $this->assertSame(10, $character->space_mochila);
        $this->assertSame(4, $character->space_carregando);
        $this->assertSame(6, $character->space_pergaminhos);
    }

    public function test_criar_equipamento_salva_dados_dano_e_volume(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Arco')
            ->set('test_dice', 'd20+[forca]')
            ->set('damage_dice', '2d6+[forca]')
            ->set('volume', 65)
            ->call('save')
            ->assertHasNoErrors();

        $equipment = Equipment::firstWhere('name', 'Arco');

        $this->assertSame('d20+[forca]', $equipment->test_dice);
        $this->assertSame('2d6+[forca]', $equipment->damage_dice);
        $this->assertSame(65, $equipment->volume);
    }

    public function test_upload_de_midia_e_armazenado(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $audio = UploadedFile::fake()->create('clang.mp3', 200, 'audio/mpeg');

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Espada Sino')
            ->set('media', $audio)
            ->call('save')
            ->assertHasNoErrors();

        $equipment = Equipment::firstWhere('name', 'Espada Sino');

        $this->assertNotNull($equipment->media);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($equipment->media);
    }

    public function test_editar_equipamento_carrega_campos(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $item = Equipment::create([
            'user_id'     => $user->id,
            'name'        => 'Shuriken',
            'test_dice'   => 'd20+[agilidade]',
            'damage_dice' => '1d6',
            'space'       => 1,
            'volume'      => 20,
        ]);

        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('startEdit', $item->id)
            ->assertSet('test_dice', 'd20+[agilidade]')
            ->assertSet('damage_dice', '1d6')
            ->assertSet('space', 1)
            ->assertSet('volume', 20);
    }

    public function test_exporta_equipamentos_da_biblioteca_em_json(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Equipment::create(['user_id' => $user->id, 'name' => 'Mochila', 'tags' => ['utilidade']]);

        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->call('exportEquipments')
            ->assertFileDownloaded();
    }

    public function test_importa_equipamentos_de_json(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $json = json_encode([
            'equipments' => [
                ['name' => 'Katana', 'tags' => ['arma'], 'space' => 3, 'damage_dice' => '2d6'],
                ['name' => 'Cantil', 'tags' => ['utilidade'], 'space' => 1],
            ],
            'tags' => [
                ['name' => 'arma', 'description' => 'Itens ofensivos.'],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('equipamentos.json', $json);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importEquipments')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('equipments', ['user_id' => $user->id, 'name' => 'Katana', 'space' => 3]);
        $this->assertDatabaseHas('equipments', ['user_id' => $user->id, 'name' => 'Cantil']);
        $this->assertDatabaseHas('tags', ['name' => 'Arma', 'description' => 'Itens ofensivos.']);
    }

    public function test_import_nao_duplica_equipamento_existente(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Equipment::create(['user_id' => $user->id, 'name' => 'Katana']);
        $this->actingAs($user);

        $json = json_encode(['equipments' => [['name' => 'Katana'], ['name' => 'Novo']]]);
        $file = UploadedFile::fake()->createWithContent('equipamentos.json', $json);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importEquipments')
            ->assertHasNoErrors();

        $this->assertSame(1, Equipment::where('user_id', $user->id)->where('name', 'Katana')->count());
        $this->assertDatabaseHas('equipments', ['user_id' => $user->id, 'name' => 'Novo']);
    }

    public function test_import_rejeita_arquivo_invalido(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent('lixo.json', 'isto não é json');

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importEquipments')
            ->assertHasErrors('importFile');
    }

    public function test_filtro_por_tag(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Equipment::create(['user_id' => $user->id, 'name' => 'Espada', 'tags' => ['arma']]);
        Equipment::create(['user_id' => $user->id, 'name' => 'Tenda', 'tags' => ['utilidade']]);

        $this->actingAs($user);

        Livewire::test(EquipmentPanel::class, ['characterId' => $character->id])
            ->set('view', 'browse')
            ->call('toggleFilter', 'arma')
            ->assertSee('Espada')
            ->assertDontSee('Tenda');
    }
}
