<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = new \Illuminate\Http\Request([
    'fecha_inicio' => '2026-09-01',
    'fecha_fin' => '2026-09-10'
]);

$controller = new \App\Http\Controllers\BancosController();
$response = $controller->data($request);
$data = $response->getData();

echo "=== DETALLE CUENTAS ===\n";
print_r($data->detalleCuentas);
