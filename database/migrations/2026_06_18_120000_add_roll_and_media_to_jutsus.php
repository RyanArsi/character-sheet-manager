<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jutsus', function (Blueprint $table) {
            $table->string('test_dice')->nullable()->after('chakra_cost');   // Dados (teste) — ex.: d20+[ninjutsu]
            $table->string('damage_dice')->nullable()->after('test_dice');   // Dano — ex.: 4d6+[forca]
            $table->string('media')->nullable()->after('image');             // Áudio/vídeo tocado ao usar o jutsu
            $table->unsignedSmallInteger('volume')->default(100)->after('media'); // Volume 0–100
        });
    }

    public function down(): void
    {
        Schema::table('jutsus', function (Blueprint $table) {
            $table->dropColumn(['test_dice', 'damage_dice', 'media', 'volume']);
        });
    }
};
