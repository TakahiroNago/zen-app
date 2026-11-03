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
        Schema::table('zen_consultations', function (Blueprint $table) {
            $table->string('zengo')->nullable();
            $table->string('zengo_reading')->nullable();
            $table->string('zengo_meaning')->nullable();
            $table->string('daily_action', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zen_consultations', function (Blueprint $table) {
            $table->dropColumn(['zengo', 'zengo_reading', 'zengo_meaning', 'daily_action']);
        });
    }
};
