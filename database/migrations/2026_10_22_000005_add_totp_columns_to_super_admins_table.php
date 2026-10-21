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
        Schema::table('super_admins', function (Blueprint $table) {
            $table->text('totp_secret')->nullable()->after('password');
            $table->boolean('totp_enabled')->default(false)->after('totp_secret');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('super_admins', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_enabled', 'totp_confirmed_at']);
        });
    }
};
