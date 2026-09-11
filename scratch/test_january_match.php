<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\ClientesController;
use Illuminate\Http\Request;

$controller = new ClientesController();

// Probando SAN MATEO ATENCO para ENERO 2026 (01/01/2026 al 10/01/2026)
$reqJan = new Request([
    'sucursal_id' => 4, // SAN MATEO ATENCO
    'fecha_inicio' => '2026-01-01',
    'fecha_fin' => '2026-01-10'
]);

$resJan = $controller->data($reqJan)->getData();

echo "=== RESULTADO WEB APP EN SAN MATEO ATENCO PARA ENERO (01/01/2026 A 10/01/2026) ===\n";
echo "Total Clientes Activos: " . $resJan->totalClientes . "\n";
echo "Nuevos: " . $resJan->nuevosCount . " (" . round($resJan->nuevosPorcentaje, 1) . "%)\n";
echo "Recurrentes: " . $resJan->recurrentesCount . " (" . round($resJan->recurrentesPorcentaje, 1) . "%)\n";
