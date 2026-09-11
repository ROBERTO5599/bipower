<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\ClientesController;
use Illuminate\Http\Request;

$controller = new ClientesController();

// ATLACOMULCO (id_valora_mas = 2) del 01 al 10 de Septiembre 2026
$req = new Request([
    'sucursal_id' => 2,
    'fecha_inicio' => '2026-09-01',
    'fecha_fin' => '2026-09-10'
]);

$res = $controller->data($req)->getData();

echo "=== VERIFICACIÓN ATLACOMULCO (ID: 2) SEPTIEMBRE 2026 ===\n";
echo "Total Clientes Activos: " . $res->totalClientes . "\n";
echo "Nuevos: " . $res->nuevosCount . " (" . round($res->nuevosPorcentaje, 1) . "%)\n";
echo "Recurrentes: " . $res->recurrentesCount . " (" . round($res->recurrentesPorcentaje, 1) . "%)\n";
