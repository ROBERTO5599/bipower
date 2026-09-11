<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$suc = Sucursal::where('nombre', 'LIKE', '%SAN MATEO%')->first();
$dbName = 'sistema_prendario_' . $suc->id_valora_mas;

$baseConfig = Config::get('database.connections.mysql');
$baseConfig['database'] = $dbName;
Config::set('database.connections.sm_test', $baseConfig);
DB::purge('sm_test');

$fechaInicio = '2026-09-01 00:00:00';
$fechaFin = '2026-09-10 23:59:59';

// Consulta UNION que obtiene TODOS los clientes con CUALQUIER actividad en el periodo
$activeClients = DB::connection('sm_test')->select("
    SELECT 
        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
        MIN(c.f_alta) as f_alta,
        MIN(act.first_date) as primer_movimiento
    FROM clientes c
    INNER JOIN (
        SELECT cod_cliente FROM contratos WHERE f_contrato BETWEEN ? AND ?
        UNION
        SELECT cod_cliente FROM ventas WHERE f_cancela IS NULL AND f_venta BETWEEN ? AND ?
        UNION
        SELECT con.cod_cliente FROM movimientos mo INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato WHERE mo.f_alta BETWEEN ? AND ? AND mo.cod_tipo_movimiento IN (2,3,4)
        UNION
        SELECT cod_cliente FROM garantias WHERE f_cancelacion IS NULL AND cod_estatus <> 3 AND f_alta BETWEEN ? AND ?
        UNION
        SELECT cod_cliente FROM creditos WHERE f_autorizado BETWEEN ? AND ?
    ) p ON p.cod_cliente = c.cod_cliente
    LEFT JOIN (
        SELECT cod_cliente, MIN(f_contrato) as first_date FROM contratos WHERE f_contrato <= ? GROUP BY cod_cliente
        UNION ALL
        SELECT ve.cod_cliente, MIN(ve.f_venta) as first_date FROM ventas ve WHERE ve.f_cancela IS NULL AND ve.f_venta <= ? GROUP BY ve.cod_cliente
        UNION ALL
        SELECT cod_cliente, MIN(f_alta) as first_date FROM garantias WHERE f_cancelacion IS NULL AND cod_estatus <> 3 AND f_alta <= ? GROUP BY cod_cliente
        UNION ALL
        SELECT cod_cliente, MIN(f_autorizado) as first_date FROM creditos WHERE f_autorizado <= ? GROUP BY cod_cliente
    ) act ON act.cod_cliente = c.cod_cliente
    GROUP BY c.nombre, c.a_paterno, c.a_materno
", [
    $fechaInicio, $fechaFin,
    $fechaInicio, $fechaFin,
    $fechaInicio, $fechaFin,
    $fechaInicio, $fechaFin,
    $fechaInicio, $fechaFin,
    $fechaFin, $fechaFin, $fechaFin, $fechaFin
]);

$total = count($activeClients);
$nuevos = 0;
$recurrentes = 0;

foreach ($activeClients as $ac) {
    if ($ac->primer_movimiento >= $fechaInicio && $ac->primer_movimiento <= $fechaFin) {
        $nuevos++;
    } else {
        $recurrentes++;
    }
}

echo "TOTAL CLIENTES ACTIVOS (CON CUALQUIER ACTIVIDAD EN EL PERIODO): $total\n";
echo "Nuevos (primer movimiento en rango): $nuevos (" . round($nuevos/$total*100, 1) . "%)\n";
echo "Recurrentes: $recurrentes (" . round($recurrentes/$total*100, 1) . "%)\n";
