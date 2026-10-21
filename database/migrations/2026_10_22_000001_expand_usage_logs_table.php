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
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->change();
            $table->string('unit')->default('detik_audio')->after('model');
            $table->decimal('qty', 14, 4)->default(0)->after('unit');
            $table->decimal('rate_snapshot', 16, 8)->default(0)->after('qty');
            $table->decimal('est_cost_usd', 14, 6)->default(0)->after('rate_snapshot');
            $table->decimal('est_cost_idr', 16, 2)->default(0)->after('est_cost_usd');
            $table->decimal('fx_rate_used', 12, 2)->default(16000.00)->after('est_cost_idr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropColumn([
                'unit',
                'qty',
                'rate_snapshot',
                'est_cost_usd',
                'est_cost_idr',
                'fx_rate_used',
            ]);
        });
    }
};
