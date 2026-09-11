<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$suc = Sucursal::where('nombre', 'LIKE', '%SAN MATEO%')->first();
echo "Testing sucursal: {$suc->nombre} (id_valora_mas: {$suc->id_valora_mas})\n";

$dbName = 'sistema_prendario_' . $suc->id_valora_mas;
$baseConfig = Config::get('database.connections.mysql');
$baseConfig['database'] = $dbName;
Config::set('database.connections.sm_test', $baseConfig);
DB::purge('sm_test');

$fechaInicio = '2026-09-01 00:00:00';
$fechaFin = '2026-09-10 23:59:59';

// Query A: por cod_cliente (como SSMS)
$queryA = DB::connection('sm_test')->select("
    SELECT 
        COUNT(*) as total_cod_cliente,
        SUM(CASE WHEN primer_movimiento BETWEEN ? AND ? THEN 1 ELSE 0 END) as nuevos,
        SUM(CASE WHEN primer_movimiento < ? THEN 1 ELSE 0 END) as recurrentes
    FROM (
        SELECT 
            c.cod_cliente,
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
        GROUP BY c.cod_cliente
    ) t
", [$fechaInicio, $fechaFin, $fechaInicio, $fechaInicio, $fechaFin, $fechaFin, $fechaFin, $fechaFin, $fechaFin]);

echo "Query A (por cod_cliente): Total=" . $queryA[0]->total_cod_cliente . " | Nuevos=" . $queryA[0]->nuevos . " | Recurrentes=" . $queryA[0]->recurrentes . "\n";

// Query B: agrupado por NOMBRES ÚNICOS (como el Controller PHP)
$queryB = DB::connection('sm_test')->select("
    SELECT 
        COUNT(*) as total_nombres_unicos,
        SUM(CASE WHEN primer_movimiento BETWEEN ? AND ? THEN 1 ELSE 0 END) as nuevos,
        SUM(CASE WHEN primer_movimiento < ? THEN 1 ELSE 0 END) as recurrentes
    FROM (
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
    ) t
", [$fechaInicio, $fechaFin, $fechaInicio, $fechaInicio, $fechaFin, $fechaFin, $fechaFin, $fechaFin, $fechaFin]);

echo "Query B (agrupado por Nombre Completo): Total=" . $queryB[0]->total_nombres_unicos . " | Nuevos=" . $queryB[0]->nuevos . " | Recurrentes=" . $queryB[0]->recurrentes . "\n";
