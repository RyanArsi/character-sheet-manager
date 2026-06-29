<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('title', 60)->default('Novo modo');
            $table->boolean('active')->default(false);

            // Modificadores (podem ser negativos) por categoria.
            $table->integer('mod_dados')->default(0);
            $table->integer('mod_pericias')->default(0);
            $table->integer('mod_especializacao')->default(0);
            $table->integer('mod_atributos')->default(0);
            $table->integer('mod_chakra_atual')->default(0);
            $table->integer('mod_chakra_max')->default(0);
            $table->integer('mod_vida_atual')->default(0);
            $table->integer('mod_vida_max')->default(0);
            $table->integer('mod_ca')->default(0);
            $table->integer('mod_resistencias')->default(0);
            $table->integer('mod_combate')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_modes');
    }
};
