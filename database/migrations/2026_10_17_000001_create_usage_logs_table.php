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
        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->default('stt'); // 'stt' | 'llm'
            $table->string('provider'); // 'groq'
            $table->string('model'); // 'whisper-large-v3-turbo'
            $table->decimal('audio_sec', 8, 2)->default(0);
            $table->enum('status', ['sukses', 'gagal', 'ditahan'])->default('sukses');
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedInteger('http_status')->nullable();
            $table->decimal('est_cost', 10, 4)->nullable()->default(0); // filled in P12
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['kind', 'status', 'created_at']);
            $table->index(['meeting_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_logs');
    }
};
