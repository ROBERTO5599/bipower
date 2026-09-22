<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$sucursal = Sucursal::where('id_valora_mas', 2)->first();
echo "Testing Depositaria Query on " . $sucursal->nombre . "...\n\n";

$fechaInicio = '2026-09-01 00:00:00';
$fechaFin = '2026-09-18 23:59:59';
$corteMesAnterior = '2026-08-31 23:59:59';

$baseConfig = Config::get('database.connections.mysql');
$dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
$connectionName = 'depo_test';

$config = $baseConfig;
$config['database'] = $dbName;
Config::set("database.connections.$connectionName", $config);
DB::purge($connectionName);

// 1. Query Actual en OperacionesCarteraController (para Cartera Vigente/Vencida)
$carteraActual = DB::connection($connectionName)->selectOne("
    SELECT SUM(prestamo) as total_monto, COUNT(*) as total_items
    FROM (
        SELECT (h.prestamo - COALESCE(c.abono, 0)) AS prestamo
        FROM (
            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda, h.cod_contrato,
                   ROW_NUMBER() OVER (PARTITION BY h.cod_prenda ORDER BY h.f_movimiento DESC, h.id_historico DESC) AS rn
            FROM historico_articulos h
            WHERE h.cod_tipo_prenda = 1 AND h.f_movimiento <= :fc1
        ) h
        INNER JOIN alhajas a ON a.cod_alhaja = h.cod_prenda
        LEFT JOIN contratos c ON c.cod_contrato = h.cod_contrato AND c.f_cancelacion IS NULL
        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (1,2)

        UNION ALL

        SELECT (h.prestamo - COALESCE(c.abono, 0)) AS prestamo
        FROM (
            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda, h.cod_contrato,
                   ROW_NUMBER() OVER (PARTITION BY h.cod_prenda ORDER BY h.f_movimiento DESC, h.id_historico DESC) AS rn
            FROM historico_articulos h
            WHERE h.cod_tipo_prenda = 3 AND h.f_movimiento <= :fc2
        ) h
        LEFT JOIN contratos c ON c.cod_contrato = h.cod_contrato AND c.f_cancelacion IS NULL
        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (1,2)

        UNION ALL

        SELECT (h.prestamo - COALESCE(c.abono, 0)) AS prestamo
        FROM (
            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda, h.cod_contrato,
                   ROW_NUMBER() OVER (PARTITION BY h.cod_prenda ORDER BY h.f_movimiento DESC, h.id_historico DESC) AS rn
            FROM historico_articulos h
            WHERE h.cod_tipo_prenda = 2 AND h.f_movimiento <= :fc3
        ) h
        LEFT JOIN contratos c ON c.cod_contrato = h.cod_contrato AND c.f_cancelacion IS NULL
        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (1,2)
    ) t
", [':fc1' => $fechaFin, ':fc2' => $fechaFin, ':fc3' => $fechaFin]);

// 2. Query de Depositaria al cierre del mes anterior (Inventario Inicial Depositaria)
$depoMesAnt = DB::connection($connectionName)->selectOne("
    SELECT COALESCE(SUM(t.prestamo - COALESCE(c.abono, 0)), 0) AS total_depositaria
    FROM (
        SELECT h.prestamo, h.cod_estatus_prenda, h.cod_contrato,
               ROW_NUMBER() OVER (
                   PARTITION BY h.cod_tipo_prenda, h.cod_prenda 
                   ORDER BY h.f_movimiento DESC, h.id_historico DESC
               ) AS rn
        FROM historico_articulos h
        WHERE h.cod_tipo_prenda IN (1, 2, 3)
          AND h.f_movimiento <= :corteMesAnt
    ) t
    LEFT JOIN contratos c ON c.cod_contrato = t.cod_contrato AND c.f_cancelacion IS NULL
    WHERE t.rn = 1 AND t.cod_estatus_prenda IN (1, 2)
", [':corteMesAnt' => $corteMesAnterior]);

// 3. Empeños del periodo
$empenos = DB::connection($connectionName)->selectOne("
    SELECT COALESCE(SUM(
        CASE
            WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
            WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
            WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
        END
    ), 0) AS total
    FROM movimientos mo
    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
    LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
    LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
    LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
    WHERE con.f_cancelacion IS NULL                             
      AND mo.cod_tipo_movimiento = 1
      AND mo.f_cancela IS NULL
      AND mo.f_alta BETWEEN :fIni AND :fFin
", [':fIni' => $fechaInicio, ':fFin' => $fechaFin]);

// 4. Desempeños del periodo
$desempenos = DB::connection($connectionName)->selectOne("
    SELECT COALESCE(SUM(
        CASE
            WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
            WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
            WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
        END
    ), 0) AS total
    FROM movimientos mo
    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
    LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
    LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
    LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
    WHERE con.f_cancelacion IS NULL                             
      AND mo.cod_tipo_movimiento = 4
      AND mo.f_cancela IS NULL
      AND mo.f_alta BETWEEN :fIni AND :fFin
", [':fIni' => $fechaInicio, ':fFin' => $fechaFin]);

// 5. Abonos a capital del periodo
$abonos = DB::connection($connectionName)->selectOne("
    SELECT COALESCE(SUM(mo.monto10 - COALESCE((SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior), 0)), 0) AS total
    FROM movimientos mo
    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
    WHERE con.f_cancelacion IS NULL
      AND mo.cod_tipo_movimiento = 3
      AND mo.f_cancela IS NULL
      AND mo.f_alta BETWEEN :fIni AND :fFin
", [':fIni' => $fechaInicio, ':fFin' => $fechaFin]);

echo "=== RESULTADOS ATLACOMULCO ===\n";
echo "1. Inventario Depositaria Cierre Mes Anterior (Inicial): $" . number_format($depoMesAnt->total_depositaria, 2) . "\n";
echo "2. Empeños (Ingresos): $" . number_format($empenos->total, 2) . "\n";
echo "3. Desempeños (Egresos): $" . number_format($desempenos->total, 2) . "\n";
echo "4. Abonos a Capital (Egresos): $" . number_format($abonos->total, 2) . "\n";
echo "5. Flujo Neto del Periodo (Ingresos - Egresos): $" . number_format($empenos->total - ($desempenos->total + $abonos->total), 2) . "\n";
echo "6. Inventario Depositaria Actual (Cartera al final de periodo): $" . number_format($carteraActual->total_monto, 2) . "\n";
echo "7. Inventario Inicial + Flujo Neto: $" . number_format($depoMesAnt->total_depositaria + $empenos->total - ($desempenos->total + $abonos->total), 2) . "\n";
