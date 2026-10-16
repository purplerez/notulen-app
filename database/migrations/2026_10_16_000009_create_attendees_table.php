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
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('org_contacts')->nullOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone_e164');
            $table->enum('status', [
                'hadir',
                'terlambat',
                'izin',
                'sakit',
                'dinas_luar',
                'tanpa_keterangan',
            ])->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->enum('method', ['qr', 'manual'])->nullable();
            $table->boolean('is_guest')->default(false);
            $table->enum('guest_status', ['pending', 'approved', 'rejected'])->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'phone_e164']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
