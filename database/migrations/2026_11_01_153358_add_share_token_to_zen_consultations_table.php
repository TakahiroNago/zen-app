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
            $table->text('share_token')->nullable();
            $table->timestamp('share_expires_at')->nullable();
            $table->index('share_token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zen_consultations', function (Blueprint $table) {
            $table->dropIndex(['share_token_hash']);
            $table->dropColumn(['share_token', 'share_expires_at']);
        });
    }
};
