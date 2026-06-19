<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // quem disparou
            $table->foreignId('character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('actor');        // nome exibido (ficha) no momento do evento
            $table->string('message');      // ação, ex.: "rolou Ninjutsu → 17"
            $table->json('detail')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_events');
    }
};
