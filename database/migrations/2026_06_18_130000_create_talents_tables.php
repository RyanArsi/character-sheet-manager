<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stubs antigos nunca usados (sem model/seed/refs) — dão lugar ao novo schema
        Schema::dropIfExists('character_talents');
        Schema::dropIfExists('system_talents');

        // Biblioteca de talentos — cada talento pertence ao usuário que o criou.
        // Mesma arquitetura dos jutsus, porém biblioteca exclusiva de talentos.
        Schema::create('talents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('media')->nullable();                  // Áudio/vídeo tocado ao usar
            $table->unsignedSmallInteger('volume')->default(100); // Volume 0–100
            $table->json('tags')->nullable();
            $table->string('chakra_cost')->nullable();   // Custo de chakra
            $table->string('test_dice')->nullable();      // Dados (teste) — ex.: d20+[ninjutsu]
            $table->string('damage_dice')->nullable();    // Dano — ex.: 4d6+[forca]
            $table->string('actions')->nullable();        // Ações
            $table->string('area_range')->nullable();      // Área/alcance
            $table->string('target')->nullable();          // Alvo
            $table->text('description')->nullable();       // Descrição com dano ou efeito
            $table->text('infos')->nullable();             // Infos
            $table->timestamps();
        });

        // Talentos atribuídos a uma ficha (M2M)
        Schema::create('character_talent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['character_id', 'talent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_talent');
        Schema::dropIfExists('talents');

        // Recria os stubs originais
        Schema::create('system_talents', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('character_talents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_talent_id')->nullable()->constrained('system_talents')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
};
