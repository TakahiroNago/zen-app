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
            $table->unsignedTinyInteger('image_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zen_consultations', function (Blueprint $table) {
            $table->dropColumn('image_number');
        });
    }
};
