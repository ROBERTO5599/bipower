<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Controllers\TableroControlController;

$user = User::find(138);
Auth::login($user);

$controller = new TableroControlController();

// Test 2: Filter specifically by Tizayuca with sistema=mysonda
$request = Request::create('/tablero-control/data', 'GET', [
    'fecha_inicio' => '2026-09-01',
    'fecha_fin' => '2026-09-15',
    'sistema' => 'mysonda',
    'sucursal_id' => '7'
]);

try {
    $response = $controller->data($request);
    $data = json_decode($response->getContent(), true);

    echo "--- TIZAYUCA SPECIFIC BRANCH TEST (SISTEMA=MYSONDA) ---\n";
    foreach (['INVENTARIO', 'Empeño', 'Refrendos', 'Desempeño', 'Ventas', 'Intereses', 'Remate', 'Bazar', 'Utilidad del Mes Por venta', 'Interés + Util Vta', 'Gastos del Mes', 'Utilidad Neta del Mes'] as $ind) {
        if (isset($data[$ind])) {
            $totalMeta = $data[$ind]['_total']['meta'] ?? 0;
            $totalAvance = $data[$ind]['_total']['avance'] ?? 0;
            echo sprintf("%-30s | Meta Total: %12.2f | Avance: %12.2f\n", $ind, $totalMeta, $totalAvance);
        }
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
