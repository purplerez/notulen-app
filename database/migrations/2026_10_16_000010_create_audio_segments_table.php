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
        Schema::create('audio_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size_bytes');
            $table->decimal('duration_sec', 8, 2)->nullable();
            $table->string('checksum', 64);
            $table->string('status')->default('uploaded');
            $table->timestamps();

            $table->unique(['meeting_id', 'seq']);
            $table->index(['organization_id', 'meeting_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_segments');
    }
};
