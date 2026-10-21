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
        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7); // 'YYYY-MM'
            $table->string('provider'); // 'groq', 'openai', 'local_disk', 's3'
            $table->decimal('actual_cost_usd', 14, 4)->nullable();
            $table->decimal('actual_cost_idr', 16, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['month', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_records');
    }
};
