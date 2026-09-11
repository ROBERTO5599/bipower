<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sucursales = App\Models\Sucursal::whereNotNull('id_valora_mas')->get();
$allPerfiles = [];

foreach ($sucursales as $suc) {
    $dbName = 'sistema_prendario_' . $suc->id_valora_mas;
    try {
        $rows = Illuminate\Support\Facades\DB::select("SELECT DISTINCT perfil FROM {$dbName}.perfiles");
        foreach ($rows as $r) {
            $allPerfiles[$r->perfil] = true;
        }
    } catch (\Throwable $e) {
        echo "Error en {$suc->nombre}: " . $e->getMessage() . "\n";
    }
}

echo "=== PERFILES / PUESTOS EN BASE DE DATOS ===\n";
print_r(array_keys($allPerfiles));
