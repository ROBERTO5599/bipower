<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$sucursales = Sucursal::svm()->get();
$baseConfig = Config::get('database.connections.mysql');

$fechaInicio = '2026-09-01 00:00:00';
$fechaFin = '2026-09-10 23:59:59';

$start = microtime(true);
$clientesUnicos = [];
$primerosMovimientosMap = [];

foreach ($sucursales as $sucursal) {
    try {
        $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
        $connectionName = 'clientes_dynamic';

        $config = $baseConfig;
        $config['database'] = $dbName;
        Config::set("database.connections.$connectionName", $config);
        DB::purge($connectionName);

        // 1. Obtener empeños en el periodo
        $empenos = DB::connection($connectionName)->select("
            SELECT 
                UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                MIN(c.f_alta) as f_alta,
                COUNT(con.cod_contrato) as num_empenos,
                SUM(con.prestamo) as prestamo,
                SUM(CASE WHEN con.cod_estatus = 4 THEN 1 ELSE 0 END) as desempenadas,
                SUM(CASE WHEN con.cod_estatus IN (3,5,6) THEN 1 ELSE 0 END) as perdidas
            FROM clientes c
            INNER JOIN contratos con ON con.cod_cliente = c.cod_cliente
            WHERE con.f_contrato BETWEEN ? AND ?
            GROUP BY c.nombre, c.a_paterno, c.a_materno
        ", [$fechaInicio, $fechaFin]);

        // 2. Obtener primer movimiento histórico por cliente
        $primerosMov = DB::connection($connectionName)->select("
            SELECT 
                UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                MIN(act.first_date) as primer_movimiento
            FROM clientes c
            INNER JOIN (
                SELECT cod_cliente, MIN(f_contrato) as first_date FROM contratos WHERE f_contrato <= ? GROUP BY cod_cliente
                UNION ALL
                SELECT ve.cod_cliente, MIN(ve.f_venta) as first_date FROM ventas ve WHERE ve.f_cancela IS NULL AND ve.f_venta <= ? GROUP BY ve.cod_cliente
                UNION ALL
                SELECT cod_cliente, MIN(f_alta) as first_date FROM garantias WHERE f_cancelacion IS NULL AND cod_estatus <> 3 AND f_alta <= ? GROUP BY cod_cliente
                UNION ALL
                SELECT cod_cliente, MIN(f_autorizado) as first_date FROM creditos WHERE f_autorizado <= ? GROUP BY cod_cliente
            ) act ON act.cod_cliente = c.cod_cliente
            GROUP BY c.nombre, c.a_paterno, c.a_materno
        ", [$fechaFin, $fechaFin, $fechaFin, $fechaFin]);

        foreach ($primerosMov as $pm) {
            $nombre = $pm->nombre_completo;
            if ($pm->primer_movimiento) {
                if (!isset($primerosMovimientosMap[$nombre]) || $pm->primer_movimiento < $primerosMovimientosMap[$nombre]) {
                    $primerosMovimientosMap[$nombre] = $pm->primer_movimiento;
                }
            }
        }

        foreach ($empenos as $emp) {
            $nombre = $emp->nombre_completo;
            if (!isset($clientesUnicos[$nombre])) {
                $clientesUnicos[$nombre] = [
                    'nombre' => $nombre,
                    'f_alta' => $emp->f_alta,
                    'num_empenos' => 0
                ];
            }
            $clientesUnicos[$nombre]['num_empenos'] += $emp->num_empenos;
        }

    } catch (\Exception $e) {
        echo "Error en {$sucursal->nombre}: " . $e->getMessage() . "\n";
    }
}

$nuevos = 0;
$recurrentes = 0;

foreach ($clientesUnicos as $nombre => $cliente) {
    $primerMov = $primerosMovimientosMap[$nombre] ?? $cliente['f_alta'];
    if ($primerMov >= $fechaInicio && $primerMov <= $fechaFin) {
        $nuevos++;
    } else {
        $recurrentes++;
    }
}

$total = count($clientesUnicos);
echo "Proceso completado en " . round(microtime(true) - $start, 3) . "s\n";
echo "Total Clientes Activos: $total | Nuevos (primer movimiento en rango): $nuevos (" . round($nuevos/$total*100, 1) . "%) | Recurrentes: $recurrentes (" . round($recurrentes/$total*100, 1) . "%)\n";
