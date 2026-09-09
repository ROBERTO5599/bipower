<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

echo "Users with status = 0: " . User::where('status', 0)->count() . PHP_EOL;
echo "Users with status = 0 and fecha_baja is null: " . User::where('status', 0)->whereNull('fecha_baja')->count() . PHP_EOL;
echo "Users with status = 0 and fecha_baja is not null: " . User::where('status', 0)->whereNotNull('fecha_baja')->count() . PHP_EOL;
exit;

$conn = 'test_conn_16';
$config = $baseConfig;
$config['database'] = 'sistema_prendario_16';
Config::set("database.connections.$conn", $config);
DB::purge($conn);

$movsUnder5 = DB::connection($conn)->select("
    SELECT u.cod_usuario, u.nombre, COUNT(mo.cod_movimiento) as total_movs
    FROM movimientos mo
    INNER JOIN usuarios u ON mo.cod_usuario = u.cod_usuario
    WHERE mo.f_cancela IS NULL AND mo.f_alta BETWEEN ? AND ?
    GROUP BY u.cod_usuario, u.nombre
    HAVING total_movs < 5
", [$fechaInicio, $fechaFin]);

echo "Pilares users with < 5 movs:\n";
print_r($movsUnder5);

$conn17 = 'test_conn_17';
$config['database'] = 'sistema_prendario_17';
Config::set("database.connections.$conn17", $config);
DB::purge($conn17);

$movsUnder5_17 = DB::connection($conn17)->select("
    SELECT u.cod_usuario, u.nombre, COUNT(mo.cod_movimiento) as total_movs
    FROM movimientos mo
    INNER JOIN usuarios u ON mo.cod_usuario = u.cod_usuario
    WHERE mo.f_cancela IS NULL AND mo.f_alta BETWEEN ? AND ?
    GROUP BY u.cod_usuario, u.nombre
    HAVING total_movs < 5
", [$fechaInicio, $fechaFin]);

echo "Toluca users with < 5 movs:\n";
print_r($movsUnder5_17);
exit;

$sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
$baseConfig = Config::get('database.connections.mysql');

$fechaInicio = now()->startOfMonth()->toDateString() . ' 00:00:00';
$fechaFin = now()->toDateString() . ' 23:59:59';

echo "\n--- Per branch analysis ---\n";
foreach ($sucursales as $suc) {
    $dbName = 'sistema_prendario_' . $suc->id_valora_mas;
    $conn = 'test_conn_' . $suc->id_valora_mas;
    $config = $baseConfig;
    $config['database'] = $dbName;
    Config::set("database.connections.$conn", $config);
    DB::purge($conn);

    try {
        $rawActivos = DB::connection($conn)->selectOne("SELECT COUNT(cod_usuario) as activos FROM usuarios WHERE activo = 1")->activos;
        
        // Movimientos >= 5 en el periodo
        $movs5 = DB::connection($conn)->select("
            SELECT u.cod_usuario, u.nombre, COUNT(mo.cod_movimiento) as total_movs
            FROM movimientos mo
            INNER JOIN usuarios u ON mo.cod_usuario = u.cod_usuario
            WHERE mo.f_cancela IS NULL AND mo.f_alta BETWEEN ? AND ?
            GROUP BY u.cod_usuario, u.nombre
            HAVING total_movs >= 5
        ", [$fechaInicio, $fechaFin]);

        $intranetUsersSuc = User::where('sucursal_id', $suc->id)->whereNull('fecha_baja')->count();

        echo "Sucursal: {$suc->nombre} (id_valora_mas: {$suc->id_valora_mas})\n";
        echo "  - Raw 'usuarios WHERE activo=1': {$rawActivos}\n";
        echo "  - Intranet active users (sucursal_id = {$suc->id}): {$intranetUsersSuc}\n";
        echo "  - Users with >= 5 movs in period: " . count($movs5) . " (Users: " . implode(', ', array_map(fn($m) => $m->nombre . " [{$m->total_movs}]", $movs5)) . ")\n";
    } catch (\Exception $e) {
        echo "Sucursal {$suc->nombre} error: " . $e->getMessage() . "\n";
    }
}
