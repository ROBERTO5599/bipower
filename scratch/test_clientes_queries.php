<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Models\Sucursal;

$sucursales = Sucursal::svm()->get();

echo "Sucursales SVM encontradas: " . count($sucursales) . "\n\n";

$request = new \Illuminate\Http\Request([
    'fecha_inicio' => '2026-08-01',
    'fecha_fin' => '2026-08-31'
]);

$controller = new \App\Http\Controllers\ClientesController();
$response = $controller->data($request);
$data = json_decode($response->getContent(), true);

echo "Total Clientes Activos en periodo: " . ($data['totalClientes'] ?? 0) . "\n";
echo "Nuevos %: " . ($data['nuevosPorcentaje'] ?? 0) . "%\n";
echo "Recurrentes %: " . ($data['recurrentesPorcentaje'] ?? 0) . "%\n";
echo "Frecuencia Promedio: " . ($data['frecuenciaPromedio'] ?? 0) . "\n";
echo "LTV Promedio: $" . number_format($data['ltvPromedio'] ?? 0, 2) . "\n";
echo "Churn Rate (Deserción): " . ($data['churnRate'] ?? 0) . "%\n";
echo "Top Clientes encontrados: " . count($data['topClientes'] ?? []) . "\n";
echo "Tabla RFM registros: " . count($data['rfmTable'] ?? []) . "\n";
echo "Clientes en Riesgo: " . count($data['clientesRiesgo'] ?? []) . "\n";

if (!empty($data['topClientes'])) {
    echo "\nSample Top Cliente:\n";
    print_r($data['topClientes'][0]);
}

if (!empty($data['rfmTable'])) {
    echo "\nSample RFM Cliente:\n";
    print_r($data['rfmTable'][0]);
}
