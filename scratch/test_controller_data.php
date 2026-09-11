<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\ClientesController;
use Illuminate\Http\Request;

$controller = new ClientesController();
$req = new Request(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-10']);
$res = $controller->data($req);
$data = $res->getData();

echo "=== RESULTADOS CONSOLIDADOS (TODAS LAS SUCURSALES) ===\n";
echo "Total Clientes Activos: " . $data->totalClientes . "\n";
echo "Nuevos: " . $data->nuevosCount . " (" . round($data->nuevosPorcentaje, 1) . "%)\n";
echo "Recurrentes: " . $data->recurrentesCount . " (" . round($data->recurrentesPorcentaje, 1) . "%)\n";
