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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->date('meeting_date');
            $table->time('start_time')->nullable();
            $table->string('location')->nullable();
            $table->enum('style', ['ringkas', 'lengkap'])->default('ringkas');
            $table->boolean('use_followup')->default(true);
            $table->boolean('use_kop')->default(true);
            $table->boolean('use_signature')->default(true);
            $table->boolean('show_attendance')->default(true);
            $table->boolean('show_recorded_notice')->default(false);
            $table->enum('status', [
                'draft',
                'recording',
                'uploaded',
                'transcribing',
                'waiting_quota',
                'ready',
                'failed',
            ])->default('draft');
            $table->integer('late_after_minutes')->default(15);
            $table->timestamp('absensi_closed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('expected_segments')->default(0);
            $table->timestamp('audio_saved_at')->nullable();
            $table->timestamp('audio_expires_at')->nullable();
            $table->timestamp('audio_deleted_at')->nullable();
            $table->string('merged_audio_path')->nullable();
            $table->timestamp('share_expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
