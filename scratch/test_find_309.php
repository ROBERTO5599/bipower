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

echo "--- BUSCANDO SUCURSAL QUE DA 309 EN SQL ---\n";

foreach (['2026-01-01 00:00:00' => '2026-01-10 23:59:59', '2026-09-01 00:00:00' => '2026-09-10 23:59:59'] as $fIni => $fFin) {
    echo "\n=== RANGO: $fIni a $fFin ===\n";
    foreach ($sucursales as $suc) {
        try {
            $dbName = 'sistema_prendario_' . $suc->id_valora_mas;
            $connectionName = 'clientes_dynamic';

            $config = $baseConfig;
            $config['database'] = $dbName;
            Config::set("database.connections.$connectionName", $config);
            DB::purge($connectionName);

            // Consulta idéntica a SSMS (agrupando por cod_cliente, nombre, a_paterno, a_materno)
            $res = DB::connection($connectionName)->select("
                SELECT 
                    COUNT(*) as total_clientes_activos,
                    SUM(CASE WHEN primer_movimiento BETWEEN ? AND ? THEN 1 ELSE 0 END) as clientes_nuevos,
                    ROUND(SUM(CASE WHEN primer_movimiento BETWEEN ? AND ? THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as pct_nuevos,
                    SUM(CASE WHEN primer_movimiento < ? THEN 1 ELSE 0 END) as clientes_recurrentes,
                    ROUND(SUM(CASE WHEN primer_movimiento < ? THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as pct_recurrentes
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
                    GROUP BY c.cod_cliente, c.nombre, c.a_paterno, c.a_materno
                ) resumen
            ", [
                $fIni, $fFin,
                $fIni, $fFin,
                $fIni,
                $fIni,
                $fIni, $fFin,
                $fFin, $fFin, $fFin, $fFin
            ]);

            if (!empty($res)) {
                $r = $res[0];
                echo "Sucursal {$suc->nombre} (ID: {$suc->id_valora_mas}): Total={$r->total_clientes_activos} | Nuevos={$r->clientes_nuevos} | Recurrentes={$r->clientes_recurrentes}\n";
            }
        } catch (\Exception $e) {
            // ignore offline
        }
    }
}
