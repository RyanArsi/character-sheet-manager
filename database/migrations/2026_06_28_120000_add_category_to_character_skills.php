<?php

use App\Models\Character;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_skills', function (Blueprint $table) {
            $table->string('category')->default('pericia')->after('attribute');
        });

        // Reclassifica as perícias que passaram a viver na nova seção.
        DB::table('character_skills')->where('name', 'Reflexos')->update(['category' => 'resistencia']);
        DB::table('character_skills')->whereIn('name', ['Bukijutsu', 'Shurikenjutsu'])->update(['category' => 'combate']);

        // Cria, para cada ficha existente, os itens novos que ainda não existem.
        $novos = [
            ['name' => 'Fortitude',       'attribute' => 'con', 'category' => 'resistencia'],
            ['name' => 'Vontade',         'attribute' => 'sab', 'category' => 'resistencia'],
            ['name' => 'Daken-jutsu',     'attribute' => 'tai', 'category' => 'combate'],
            ['name' => 'Ataque-ninjutsu', 'attribute' => 'nin', 'category' => 'combate'],
        ];

        Character::query()->chunkById(200, function ($characters) use ($novos) {
            foreach ($characters as $character) {
                $existentes = $character->skills()->pluck('name')->all();
                foreach ($novos as $def) {
                    if (! in_array($def['name'], $existentes, true)) {
                        $character->skills()->create($def);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('character_skills', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
