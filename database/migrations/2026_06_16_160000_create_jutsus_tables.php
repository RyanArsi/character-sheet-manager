<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stubs antigos nunca usados (sem model/seed/refs) — dão lugar ao novo schema
        Schema::dropIfExists('character_jutsus');
        Schema::dropIfExists('system_jutsus');

        // Biblioteca de jutsus — cada jutsu pertence ao usuário que o criou.
        // Pode existir sem campanha; a "lista da campanha" é a união dos jutsus dos membros.
        Schema::create('jutsus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('image')->nullable();
            $table->json('tags')->nullable();
            $table->string('chakra_cost')->nullable();   // Custo de chakra
            $table->string('actions')->nullable();        // Ações
            $table->string('area_range')->nullable();      // Área/alcance
            $table->string('target')->nullable();          // Alvo
            $table->text('description')->nullable();       // Descrição com dano ou efeito
            $table->text('infos')->nullable();             // Infos
            $table->timestamps();
        });

        // Jutsus atribuídos a uma ficha (M2M)
        Schema::create('character_jutsu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('jutsu_id')->constrained('jutsus')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['character_id', 'jutsu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_jutsu');
        Schema::dropIfExists('jutsus');

        // Recria os stubs originais
        Schema::create('system_jutsus', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('character_jutsus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_jutsu_id')->nullable()->constrained('system_jutsus')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('rank')->nullable();
            $table->string('type')->nullable();
            $table->unsignedInteger('chakra_cost')->default(0);
            $table->timestamps();
        });
    }
};
