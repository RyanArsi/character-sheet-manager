<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ações atribuídas a uma ficha (M2M) — mesmo molde de jutsus/talentos.
        Schema::create('character_action', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('action_id')->constrained('actions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['character_id', 'action_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_action');
    }
};
