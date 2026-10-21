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
        Schema::create('price_rates', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // 'groq', 'openai', 'local_disk', 's3'
            $table->string('model');    // 'whisper-large-v3-turbo', 'llama-3.3-70b-versatile', 'audio_storage'
            $table->string('unit');     // 'detik_audio', 'token', 'gb_bulan', 'byte_hari'
            $table->decimal('price_usd', 16, 8)->default(0);
            $table->timestamp('effective_from');
            $table->timestamps();

            $table->index(['provider', 'model', 'unit', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_rates');
    }
};
