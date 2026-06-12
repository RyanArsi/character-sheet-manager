<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_jutsus');
    }
};
