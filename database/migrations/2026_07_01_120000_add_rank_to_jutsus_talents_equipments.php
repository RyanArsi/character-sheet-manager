<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jutsus', function (Blueprint $table) {
            $table->string('rank')->nullable()->after('name');   // Rank/Nível
        });

        Schema::table('talents', function (Blueprint $table) {
            $table->string('rank')->nullable()->after('name');   // Rank/Nível
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->string('rank')->nullable()->after('name');   // Rank/Nível
        });
    }

    public function down(): void
    {
        Schema::table('jutsus', function (Blueprint $table) {
            $table->dropColumn('rank');
        });

        Schema::table('talents', function (Blueprint $table) {
            $table->dropColumn('rank');
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn('rank');
        });
    }
};
