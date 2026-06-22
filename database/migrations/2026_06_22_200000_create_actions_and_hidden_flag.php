<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nova mecânica: Ações (de perícia) — item de biblioteca como jutsus/talentos.
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('tags')->nullable();
            $table->string('test_dice')->nullable();
            $table->string('damage_dice')->nullable();
            $table->text('description')->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamps();
        });

        // Ocultar item da biblioteca: visível só para o criador e o mestre.
        foreach (['jutsus', 'talents', 'equipments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('hidden')->default(false)->after('tags');
            });
        }
    }

    public function down(): void
    {
        foreach (['jutsus', 'talents', 'equipments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('hidden');
            });
        }

        Schema::dropIfExists('actions');
    }
};
