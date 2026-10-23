<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Http\Controllers\HomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

$user = User::first();
Auth::login($user);

$controller = new HomeController();

// Test 1: Default request
echo "=== Test 1: Default Request ===\n";
$req1 = Request::create('/home/movimientos-data', 'GET', []);
try {
    $res1 = $controller->getMovimientosData($req1);
    echo "Status: " . $res1->getStatusCode() . "\n";
    $d1 = json_decode($res1->getContent(), true);
    echo "Success: " . ($d1['success'] ? 'true' : 'false') . "\n";
    echo "Total movs: " . ($d1['total_movimientos'] ?? 'null') . "\n";
    echo "Movimientos list count: " . count($d1['movimientos'] ?? []) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 2: Request with sucursal_id = all, periodo = mes
echo "\n=== Test 2: Periodo mes, all branches ===\n";
$req2 = Request::create('/home/movimientos-data', 'GET', [
    'sucursal_id' => 'all',
    'periodo' => 'mes',
    'fecha' => '2026-10-22'
]);
try {
    $res2 = $controller->getMovimientosData($req2);
    echo "Status: " . $res2->getStatusCode() . "\n";
    $d2 = json_decode($res2->getContent(), true);
    echo "Success: " . ($d2['success'] ? 'true' : 'false') . "\n";
    echo "Total movs: " . ($d2['total_movimientos'] ?? 'null') . "\n";
    echo "Movimientos list count: " . count($d2['movimientos'] ?? []) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 3: Request for inventario-rematado as well
echo "\n=== Test 3: Inventario Rematado ===\n";
use App\Http\Controllers\InventarioRematadoController;
$remController = new InventarioRematadoController();
$req3 = Request::create('/inventario-rematado/data', 'GET', [
    'fecha_inicio' => '2026-10-01',
    'fecha_fin' => '2026-10-22'
]);
try {
    $res3 = $remController->data($req3);
    echo "Status: " . $res3->getStatusCode() . "\n";
    $d3 = json_decode($res3->getContent(), true);
    echo "Success: " . ($d3['success'] ? 'true' : 'false') . "\n";
    echo "Total arts: " . ($d3['kpis']['total_articulos'] ?? 'null') . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
