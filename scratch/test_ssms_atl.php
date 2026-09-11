<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$suc = Sucursal::where('nombre', 'LIKE', '%ATLACOMULCO%')->where('nombre', 'NOT LIKE', '%2%')->first();
echo "Testing sucursal: {$suc->nombre} (id_valora_mas: {$suc->id_valora_mas})\n";

$dbName = 'sistema_prendario_' . $suc->id_valora_mas;
$baseConfig = Config::get('database.connections.mysql');
$baseConfig['database'] = $dbName;
Config::set('database.connections.atl_test', $baseConfig);
DB::purge('atl_test');

$fechaInicio = '2026-09-01 00:00:00';
$fechaFin = '2026-09-10 23:59:59';

// Consulta idéntica a SSMS en Atlacomulco
$ssmsQuery = DB::connection('atl_test')->select("
    SELECT 
        COUNT(*) AS total_clientes_activos,
        SUM(CASE WHEN primer_movimiento BETWEEN ? AND ? THEN 1 ELSE 0 END) AS clientes_nuevos,
        ROUND(SUM(CASE WHEN primer_movimiento BETWEEN ? AND ? THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS pct_nuevos,
        SUM(CASE WHEN primer_movimiento < ? THEN 1 ELSE 0 END) AS clientes_recurrentes,
        ROUND(SUM(CASE WHEN primer_movimiento < ? THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS pct_recurrentes
    FROM (
        SELECT 
            c.cod_cliente,
            UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) AS nombre_completo,
            MIN(act.first_date) AS primer_movimiento
        FROM clientes c
        INNER JOIN contratos con_act ON con_act.cod_cliente = c.cod_cliente AND con_act.f_contrato BETWEEN ? AND ?
        LEFT JOIN (
            SELECT cod_cliente, MIN(f_contrato) AS first_date FROM contratos WHERE f_contrato <= ? GROUP BY cod_cliente
            UNION ALL
            SELECT ve.cod_cliente, MIN(ve.f_venta) AS first_date FROM ventas ve WHERE ve.f_cancela IS NULL AND ve.f_venta <= ? GROUP BY ve.cod_cliente
            UNION ALL
            SELECT cod_cliente, MIN(f_alta) AS first_date FROM garantias WHERE f_cancelacion IS NULL AND cod_estatus <> 3 AND f_alta <= ? GROUP BY cod_cliente
            UNION ALL
            SELECT cod_cliente, MIN(f_autorizado) AS first_date FROM creditos WHERE f_autorizado <= ? GROUP BY cod_cliente
        ) act ON act.cod_cliente = c.cod_cliente
        GROUP BY c.cod_cliente, c.nombre, c.a_paterno, c.a_materno
    ) resumen
", [
    $fechaInicio, $fechaFin,
    $fechaInicio, $fechaFin,
    $fechaInicio,
    $fechaInicio,
    $fechaInicio, $fechaFin,
    $fechaFin, $fechaFin, $fechaFin, $fechaFin
]);

echo "Resultado directo SSMS en Atlacomulco:\n";
print_r($ssmsQuery);
