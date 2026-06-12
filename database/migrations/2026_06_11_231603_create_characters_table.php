<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Novo Personagem');
            $table->string('race')->nullable();
            $table->string('village')->nullable();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('xp')->default(0);

            $table->unsignedSmallInteger('hp_current')->default(20);
            $table->unsignedSmallInteger('hp_max')->default(20);
            $table->unsignedSmallInteger('chakra_current')->default(20);
            $table->unsignedSmallInteger('chakra_max')->default(20);

            $table->tinyInteger('forca')->default(0);
            $table->tinyInteger('agilidade')->default(0);
            $table->tinyInteger('constituicao')->default(0);
            $table->tinyInteger('inteligencia')->default(0);
            $table->tinyInteger('sabedoria')->default(0);
            $table->tinyInteger('carisma')->default(0);

            $table->tinyInteger('ninjutsu')->default(0);
            $table->tinyInteger('genjutsu')->default(0);
            $table->tinyInteger('taijutsu')->default(0);

            $table->text('notes')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
