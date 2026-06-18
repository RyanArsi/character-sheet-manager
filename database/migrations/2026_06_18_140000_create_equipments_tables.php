<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stub antigo nunca usado (sem model/seed/refs) — dá lugar ao novo schema
        Schema::dropIfExists('character_equipments');

        // Biblioteca de equipamentos — cada item pertence ao usuário que o criou.
        // Mesma arquitetura dos jutsus/talentos, porém biblioteca exclusiva e sem
        // chakra/ações/área/alvo; em troca tem "space" (espaço ocupado).
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('media')->nullable();                  // Áudio/vídeo tocado ao usar
            $table->unsignedSmallInteger('volume')->default(100); // Volume 0–100
            $table->json('tags')->nullable();
            $table->string('test_dice')->nullable();      // Dados (teste) — ex.: d20+[forca]
            $table->string('damage_dice')->nullable();    // Dano — ex.: 2d6+[forca]
            $table->unsignedInteger('space')->nullable();  // Espaço ocupado pelo item
            $table->text('description')->nullable();
            $table->text('infos')->nullable();
            $table->timestamps();
        });

        // Equipamentos atribuídos a uma ficha (M2M) — com o local onde o item é carregado
        Schema::create('character_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->string('location')->default('mochila'); // mochila | carregando | pergaminhos
            $table->timestamps();

            $table->unique(['character_id', 'equipment_id']);
        });

        // Limites de espaço por local, definidos pelo jogador em cada ficha
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('space_mochila')->default(0)->after('taijutsu');
            $table->unsignedInteger('space_carregando')->default(0)->after('space_mochila');
            $table->unsignedInteger('space_pergaminhos')->default(0)->after('space_carregando');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['space_mochila', 'space_carregando', 'space_pergaminhos']);
        });

        Schema::dropIfExists('character_equipment');
        Schema::dropIfExists('equipments');

        // Recria o stub original
        Schema::create('character_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('weight')->nullable();
            $table->timestamps();
        });
    }
};
