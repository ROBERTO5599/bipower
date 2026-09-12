<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roles = [
            [
                'nombre' => 'PBI Super Administrador',
                'descripcion' => 'Acceso total e ilimitado a todas las sucursales en tableros y reportes de Power BI',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'PBI Regional',
                'descripcion' => 'Acceso filtrado únicamente a las sucursales donde el usuario está asignado como regional',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'PBI Gerente',
                'descripcion' => 'Acceso restringido únicamente a su sucursal asignada en Power BI',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($roles as $role) {
            $exists = DB::table('roles')->where('nombre', $role['nombre'])->exists();
            if (!$exists) {
                DB::table('roles')->insert($role);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->whereIn('nombre', [
            'PBI Super Administrador',
            'PBI Regional',
            'PBI Gerente'
        ])->delete();
    }
};
