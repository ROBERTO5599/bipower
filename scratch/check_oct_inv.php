<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\InventarioRematadoController;

$user = User::find(1);
Auth::login($user);

$req = Request::create('/inventario-rematado/data', 'GET', [
    'fecha_inicio' => '2026-10-01',
    'fecha_fin' => '2026-10-22',
    'categoria' => 'todas'
]);

$ctrl = app(InventarioRematadoController::class);
$resp = $ctrl->data($req);
$data = json_decode($resp->getContent(), true);

echo "Total articulos: " . ($data['kpis']['total_articulos'] ?? 'null') . "\n";
echo "Total contratos: " . ($data['kpis']['total_contratos'] ?? 'null') . "\n";
echo "Por sucursal: \n";
foreach ($data['por_sucursal'] ?? [] as $s) {
    echo "  {$s['nombre']} ({$s['id_valora_mas']}): {$s['articulos']} arts, \$" . number_format($s['prestamo'], 2) . "\n";
}
