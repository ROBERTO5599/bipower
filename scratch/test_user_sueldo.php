<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$usersActivos = User::where('status', 0)->get(['id', 'nombre', 'primer_apellido', 'sucursal_id', 'sueldo', 'periodo_pago']);

echo "Total usuarios activos (status = 0): " . $usersActivos->count() . PHP_EOL;
echo "Suma de sueldo de activos: $" . number_format($usersActivos->sum('sueldo'), 2) . PHP_EOL;

echo "\nMuestra de 10 usuarios con sueldo y periodo_pago:\n";
foreach ($usersActivos->take(10) as $u) {
    echo "ID {$u->id} | {$u->nombre} {$u->primer_apellido} | sucursal_id: {$u->sucursal_id} | sueldo: {$u->sueldo} | periodo_pago: {$u->periodo_pago}\n";
}

$suc16 = Sucursal::find(16);
echo "Sucursal 16: {$suc16->nombre} (id_valora_mas: {$suc16->id_valora_mas})\n";

$assignedUsers = User::where('status', 0)->where('sucursal_id', 16)->get(['id', 'nombre', 'primer_apellido', 'sueldo']);
echo "Assigned to sucursal 16 in users table:\n";
foreach ($assignedUsers as $u) {
    echo "  {$u->nombre} {$u->primer_apellido} - Sueldo: \${$u->sueldo}\n";
}
echo "Total sueldo asignado a sucursal 16: $" . number_format($assignedUsers->sum('sueldo'), 2) . "\n";

$conn = 'test_conn_16';
$config = Config::get('database.connections.mysql');
$config['database'] = 'sistema_prendario_' . $suc16->id_valora_mas;
Config::set("database.connections.$conn", $config);
DB::purge($conn);

$movs5 = DB::connection($conn)->select("
    SELECT u.cod_usuario, u.nombre, COUNT(mo.cod_movimiento) as total_movs
    FROM movimientos mo
    INNER JOIN usuarios u ON mo.cod_usuario = u.cod_usuario
    WHERE mo.f_cancela IS NULL AND mo.f_alta BETWEEN ? AND ?
    GROUP BY u.cod_usuario, u.nombre
    HAVING total_movs >= 5
", ['2026-09-01 00:00:00', '2026-09-09 23:59:59']);

echo "\nOperating users with >= 5 movs in branch 16:\n";
$operatingSueldoTotal = 0;
foreach ($movs5 as $m) {
    $match = User::where('status', 0)
        ->whereRaw("CONCAT(nombre, ' ', primer_apellido) LIKE ?", ['%' . trim($m->nombre) . '%'])
        ->orWhereRaw("? LIKE CONCAT('%', nombre, '%')", [trim($m->nombre)])
        ->first();
    $sueldo = $match ? $match->sueldo : 0;
    $operatingSueldoTotal += $sueldo;
    echo "  {$m->nombre} ({$m->total_movs} movs) => Sueldo: \${$sueldo} (Intranet sucursal_id: " . ($match ? $match->sucursal_id : 'N/A') . ")\n";
}
echo "Total sueldo de operativos (>= 5 movs): $" . number_format($operatingSueldoTotal, 2) . "\n";
exit;

$branchUsers = DB::connection($conn)->select("SELECT cod_usuario, nombre FROM usuarios WHERE activo = 1 LIMIT 10");
echo "Branch 16 (Pilares) sample users:\n";
foreach ($branchUsers as $bu) {
    $match = User::where('status', 0)
        ->whereRaw("CONCAT(nombre, ' ', primer_apellido) LIKE ?", ['%' . trim($bu->nombre) . '%'])
        ->orWhereRaw("? LIKE CONCAT('%', nombre, '%')", [trim($bu->nombre)])
        ->first();
    echo "  Branch: '{$bu->nombre}' => Intranet match: " . ($match ? "'{$match->nombre_completo}' (Sueldo: \${$match->sueldo})" : "NO MATCH") . "\n";
}
exit;

$sueldoPorSucursal = User::where('status', 0)
    ->select('sucursal_id', DB::raw('SUM(sueldo) as nomina_sucursal'), DB::raw('COUNT(*) as total_empleados'))
    ->groupBy('sucursal_id')
    ->get();

echo "\nSueldo total agrupado por sucursal_id en users:\n";
foreach ($sueldoPorSucursal as $s) {
    echo "Sucursal ID: {$s->sucursal_id} | Empleados: {$s->total_empleados} | Suma Sueldo: $" . number_format($s->nomina_sucursal, 2) . PHP_EOL;
}
