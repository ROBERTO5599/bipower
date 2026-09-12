<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop partial table if exists from failed attempt
        Schema::dropIfExists('sucursal_configuracion_tarjetas');

        // 1. Create the new sucursal_configuracion_tarjetas table
        Schema::create('sucursal_configuracion_tarjetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->unique()->constrained('sucursals')->onDelete('cascade');
            $table->string('id_sucursal_tarjeta')->nullable();
            $table->decimal('comision_clip', 5, 2)->default(2.99);
            $table->decimal('iva_comision', 5, 2)->default(16.00);
            $table->decimal('comision_meses_3', 5, 2)->default(4.57);
            $table->decimal('comision_meses_6', 5, 2)->default(5.57);
            $table->decimal('comision_meses_9', 5, 2)->default(11.07);
            $table->decimal('comision_meses_12', 5, 2)->default(12.77);
            $table->decimal('comision_meses_18', 5, 2)->default(19.27);
            $table->decimal('comision_meses_24', 5, 2)->default(27.17);
            $table->timestamps();
        });

        // 2. Migrate existing data from sucursals to sucursal_configuracion_tarjetas
        $sucursales = DB::table('sucursals')->get();
        foreach ($sucursales as $s) {
            DB::table('sucursal_configuracion_tarjetas')->insert([
                'sucursal_id' => $s->id,
                'id_sucursal_tarjeta' => $s->id_sucursal_tarjeta ?? null,
                'comision_clip' => $s->comision_clip ?? 2.99,
                'iva_comision' => $s->iva_comision ?? 16.00,
                'comision_meses_3' => $s->comision_meses_3 ?? 4.57,
                'comision_meses_6' => $s->comision_meses_6 ?? 5.57,
                'comision_meses_9' => $s->comision_meses_9 ?? 11.07,
                'comision_meses_12' => $s->comision_meses_12 ?? 12.77,
                'comision_meses_18' => $s->comision_meses_18 ?? 19.27,
                'comision_meses_24' => $s->comision_meses_24 ?? 27.17,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Drop columns from sucursals
        Schema::table('sucursals', function (Blueprint $table) {
            $columnsToDrop = [];
            $targetCols = [
                'id_sucursal_tarjeta',
                'comision_clip',
                'iva_comision',
                'comision_meses_3',
                'comision_meses_6',
                'comision_meses_9',
                'comision_meses_12',
                'comision_meses_18',
                'comision_meses_24'
            ];
            foreach ($targetCols as $col) {
                if (Schema::hasColumn('sucursals', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Re-add columns to sucursals
        Schema::table('sucursals', function (Blueprint $table) {
            $table->string('id_sucursal_tarjeta')->nullable();
            $table->decimal('comision_clip', 5, 2)->default(2.99);
            $table->decimal('iva_comision', 5, 2)->default(16.00);
            $table->decimal('comision_meses_3', 5, 2)->default(4.57);
            $table->decimal('comision_meses_6', 5, 2)->default(5.57);
            $table->decimal('comision_meses_9', 5, 2)->default(11.07);
            $table->decimal('comision_meses_12', 5, 2)->default(12.77);
            $table->decimal('comision_meses_18', 5, 2)->default(19.27);
            $table->decimal('comision_meses_24', 5, 2)->default(27.17);
        });

        // 2. Restore data back to sucursals
        $configs = DB::table('sucursal_configuracion_tarjetas')->get();
        foreach ($configs as $cfg) {
            DB::table('sucursals')->where('id', $cfg->sucursal_id)->update([
                'id_sucursal_tarjeta' => $cfg->id_sucursal_tarjeta,
                'comision_clip' => $cfg->comision_clip,
                'iva_comision' => $cfg->iva_comision,
                'comision_meses_3' => $cfg->comision_meses_3,
                'comision_meses_6' => $cfg->comision_meses_6,
                'comision_meses_9' => $cfg->comision_meses_9,
                'comision_meses_12' => $cfg->comision_meses_12,
                'comision_meses_18' => $cfg->comision_meses_18,
                'comision_meses_24' => $cfg->comision_meses_24,
            ]);
        }

        // 3. Drop table
        Schema::dropIfExists('sucursal_configuracion_tarjetas');
    }
};
