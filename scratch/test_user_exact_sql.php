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

$fechaInicio = '2026-01-01 00:00:00';
$fechaFin = '2026-01-10 23:59:59';

// 1. Obtener empeños en el periodo
$empenos = DB::connection('sm_test')->select("
    SELECT 
        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
        MIN(c.f_alta) as f_alta,
        COUNT(con.cod_contrato) as num_empenos,
        SUM(con.prestamo) as prestamo
    FROM clientes c
    INNER JOIN contratos con ON con.cod_cliente = c.cod_cliente
    WHERE con.f_contrato BETWEEN ? AND ?
    GROUP BY c.nombre, c.a_paterno, c.a_materno
", [$fechaInicio, $fechaFin]);

// 2. Obtener primer movimiento historico de la subconsulta UNION
$primerosMov = DB::connection('sm_test')->select("
    SELECT 
        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
        MIN(act.first_date) as primer_movimiento
    FROM clientes c
    INNER JOIN contratos con_act ON con_act.cod_cliente = c.cod_cliente AND con_act.f_contrato BETWEEN ? AND ?
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
", [$fechaInicio, $fechaFin, $fechaFin, $fechaFin, $fechaFin, $fechaFin]);

$pmMap = [];
foreach ($primerosMov as $pm) {
    $pmMap[$pm->nombre_completo] = $pm->primer_movimiento;
}

$clientesUnicos = [];
foreach ($empenos as $emp) {
    $nombre = $emp->nombre_completo;
    $clientesUnicos[$nombre] = [
        'nombre' => $nombre,
        'f_alta' => $emp->f_alta,
        'primer_movimiento' => $pmMap[$nombre] ?? $emp->f_alta,
    ];
}

$total = count($clientesUnicos);
$nuevos = 0;
$recurrentes = 0;

foreach ($clientesUnicos as $c) {
    if ($c['primer_movimiento'] >= $fechaInicio && $c['primer_movimiento'] <= $fechaFin) {
        $nuevos++;
    } else {
        $recurrentes++;
    }
}

echo "TOTAL CLIENTES ACTIVOS (CONTRATOS EN EL PERIODO): $total\n";
echo "Nuevos: $nuevos (" . round($nuevos/$total*100, 1) . "%)\n";
echo "Recurrentes: $recurrentes (" . round($recurrentes/$total*100, 1) . "%)\n";
