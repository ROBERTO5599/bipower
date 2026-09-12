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
        Schema::table('sucursals', function (Blueprint $table) {
            if (!Schema::hasColumn('sucursals', 'regional_user_id')) {
                $table->unsignedBigInteger('regional_user_id')
                      ->nullable()
                      ->default(null)
                      ->after('id_valora_mas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sucursals', function (Blueprint $table) {
            if (Schema::hasColumn('sucursals', 'regional_user_id')) {
                $table->dropColumn('regional_user_id');
            }
        });
    }
};
