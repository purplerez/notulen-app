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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('address_text')->nullable();
            $table->string('kop_path')->nullable();
            $table->enum('plan', ['internal', 'free', 'paid'])->default('internal');
            $table->integer('quota_minutes')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
