<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grupos de fichas do mestre dentro de uma campanha (ex.: Vilões, NPCs).
        Schema::create('campaign_sheet_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('characters', function (Blueprint $table) {
            // Ficha de NPC/vilão pertencente a uma campanha (só o mestre vê/edita).
            // NULL = ficha normal de jogador.
            $table->foreignId('campaign_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('sheet_group_id')->nullable()->after('campaign_id')
                ->constrained('campaign_sheet_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sheet_group_id');
            $table->dropConstrainedForeignId('campaign_id');
        });

        Schema::dropIfExists('campaign_sheet_groups');
    }
};
