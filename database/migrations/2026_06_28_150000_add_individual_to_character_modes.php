<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_modes', function (Blueprint $table) {
            // Modificadores individuais: { attrs:{forca:..}, spec:{ninjutsu:..}, skills:{<id>:..} }
            $table->json('individual')->nullable()->after('mod_combate');
        });
    }

    public function down(): void
    {
        Schema::table('character_modes', function (Blueprint $table) {
            $table->dropColumn('individual');
        });
    }
};
