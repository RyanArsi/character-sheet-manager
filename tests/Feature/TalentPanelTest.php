<?php

namespace Tests\Feature;

use App\Livewire\TalentPanel;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class TalentPanelTest extends TestCase
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

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->assertForbidden();
    }

    public function test_criar_talento_atribui_a_ficha(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Visão Aguçada')
            ->set('tagsInput', 'passivo, percepção')
            ->set('chakra_cost', '0')
            ->call('save')
            ->assertHasNoErrors();

        $talent = Talent::firstWhere('name', 'Visão Aguçada');

        $this->assertNotNull($talent);
        $this->assertSame($user->id, $talent->user_id);
        $this->assertSame(['Passivo', 'Percepção'], $talent->tags);
        $this->assertTrue($character->talents()->where('talents.id', $talent->id)->exists());
    }

    public function test_nome_e_obrigatorio(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_jogador_atribui_talento_de_membro_da_campanha(): void
    {
        $master = User::factory()->create();
        $player = User::factory()->create();

        $campaign = Campaign::create(['owner_id' => $master->id, 'name' => 'Vila da Folha']);
        $campaign->members()->attach($master->id, ['role' => 'owner']);
        $campaign->members()->attach($player->id, ['role' => 'player']);

        $character = Character::factory()->create(['user_id' => $player->id]);
        $campaign->characters()->attach($character->id);

        $talentDoMestre = Talent::create(['user_id' => $master->id, 'name' => 'Reflexos']);

        $this->actingAs($player);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->call('assign', $talentDoMestre->id);

        $this->assertTrue($character->talents()->where('talents.id', $talentDoMestre->id)->exists());
    }

    public function test_nao_atribui_talento_fora_do_pool(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $estranho = User::factory()->create();
        $talentoAlheio = Talent::create(['user_id' => $estranho->id, 'name' => 'Secreto']);

        $this->actingAs($user);

        try {
            Livewire::test(TalentPanel::class, ['characterId' => $character->id])
                ->call('assign', $talentoAlheio->id);
            $this->fail('Deveria ter recusado um talento fora do pool.');
        } catch (ModelNotFoundException) {
            // esperado
        }

        $this->assertFalse($character->talents()->where('talents.id', $talentoAlheio->id)->exists());
    }

    public function test_criar_talento_registra_tags_no_dicionario(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Punho de Ferro')
            ->set('tagsInput', 'físico, ofensivo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tags', ['name' => 'Físico']);
        $this->assertDatabaseHas('tags', ['name' => 'Ofensivo']);
    }

    public function test_criar_talento_salva_dados_dano_e_volume(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Golpe Forte')
            ->set('test_dice', 'd20+[taijutsu]')
            ->set('damage_dice', '4d6+[forca]')
            ->set('volume', 55)
            ->call('save')
            ->assertHasNoErrors();

        $talent = Talent::firstWhere('name', 'Golpe Forte');

        $this->assertSame('d20+[taijutsu]', $talent->test_dice);
        $this->assertSame('4d6+[forca]', $talent->damage_dice);
        $this->assertSame(55, $talent->volume);
    }

    public function test_upload_de_midia_e_armazenado(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $audio = UploadedFile::fake()->create('talento.mp3', 200, 'audio/mpeg');

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->call('startCreate')
            ->set('name', 'Grito de Guerra')
            ->set('media', $audio)
            ->call('save')
            ->assertHasNoErrors();

        $talent = Talent::firstWhere('name', 'Grito de Guerra');

        $this->assertNotNull($talent->media);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($talent->media);
    }

    public function test_editar_talento_carrega_campos_de_rolagem(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $talent = Talent::create([
            'user_id'     => $user->id,
            'name'        => 'Esquiva',
            'test_dice'   => 'd20+[agilidade]',
            'damage_dice' => '0',
            'volume'      => 30,
        ]);

        $this->actingAs($user);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->call('startEdit', $talent->id)
            ->assertSet('test_dice', 'd20+[agilidade]')
            ->assertSet('damage_dice', '0')
            ->assertSet('volume', 30);
    }

    public function test_exporta_talentos_da_biblioteca_em_json(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Talent::create(['user_id' => $user->id, 'name' => 'Foco', 'tags' => ['mental']]);

        $this->actingAs($user);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->call('exportTalents')
            ->assertFileDownloaded();
    }

    public function test_importa_talentos_de_json(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $json = json_encode([
            'talents' => [
                ['name' => 'Resistência', 'tags' => ['físico'], 'chakra_cost' => '0', 'description' => 'Aguenta mais.'],
                ['name' => 'Furtividade', 'tags' => ['suporte']],
            ],
            'tags' => [
                ['name' => 'físico', 'description' => 'Talentos corporais.'],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('talentos.json', $json);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importTalents')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('talents', ['user_id' => $user->id, 'name' => 'Resistência']);
        $this->assertDatabaseHas('talents', ['user_id' => $user->id, 'name' => 'Furtividade']);
        $this->assertDatabaseHas('tags', ['name' => 'Físico', 'description' => 'Talentos corporais.']);
    }

    public function test_import_nao_duplica_talento_existente(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Talent::create(['user_id' => $user->id, 'name' => 'Resistência']);
        $this->actingAs($user);

        $json = json_encode(['talents' => [['name' => 'Resistência'], ['name' => 'Novo']]]);
        $file = UploadedFile::fake()->createWithContent('talentos.json', $json);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importTalents')
            ->assertHasNoErrors();

        $this->assertSame(1, Talent::where('user_id', $user->id)->where('name', 'Resistência')->count());
        $this->assertDatabaseHas('talents', ['user_id' => $user->id, 'name' => 'Novo']);
    }

    public function test_import_rejeita_arquivo_invalido(): void
    {
        [$user, $character] = $this->userWithCharacter();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent('lixo.json', 'isto não é json');

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->set('importFile', $file)
            ->call('importTalents')
            ->assertHasErrors('importFile');
    }

    public function test_filtro_por_tag(): void
    {
        [$user, $character] = $this->userWithCharacter();
        Talent::create(['user_id' => $user->id, 'name' => 'Mente Calma', 'tags' => ['mental']]);
        Talent::create(['user_id' => $user->id, 'name' => 'Força Bruta', 'tags' => ['físico']]);

        $this->actingAs($user);

        Livewire::test(TalentPanel::class, ['characterId' => $character->id])
            ->set('view', 'browse')
            ->call('toggleFilter', 'mental')
            ->assertSee('Mente Calma')
            ->assertDontSee('Força Bruta');
    }
}
