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
        Schema::create('minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->json('content_json');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->text('revision_reason')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'version']);
            $table->index(['organization_id', 'meeting_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minutes');
    }
};
