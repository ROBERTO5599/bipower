<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$fechaInicio = '2024-01-01 00:00:00';
$fechaFin = '2025-12-31 23:59:59';
$sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
$baseConfig = Config::get('database.connections.mysql');

$nominaPorSucursal = [];
$utilidadPorSucursal = [];
$valuadoresList = [];

foreach ($sucursales as $sucursal) {
    try {
        $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
        $conn = 'dyn_' . $sucursal->id_valora_mas;
        $config = $baseConfig;
        $config['database'] = $dbName;
        Config::set("database.connections.$conn", $config);
        DB::purge($conn);

        // 1. Nomina
        $nom = DB::connection($conn)->selectOne("
            SELECT COALESCE(SUM(COALESCE(g.autorizado, g.solicitado, 0)), 0) as nomina
            FROM gastos g
            LEFT JOIN conceptos c ON g.cod_concepto = c.cod_concepto
            WHERE g.f_cancelacion IS NULL AND g.activo = 1 
              AND COALESCE(g.f_aplicacion, g.f_autorizado, g.f_solicitado) BETWEEN ? AND ?
              AND (LOWER(c.concepto) LIKE '%nomina%' OR LOWER(c.concepto) LIKE '%nómina%' OR LOWER(c.concepto) LIKE '%sueldo%')
        ", [$fechaInicio, $fechaFin]);
        $nominaSucursal = (float)$nom->nomina;

        // 2. Ventas y Utilidad
        $ventas = DB::connection($conn)->selectOne("
             SELECT 
                COALESCE(SUM(dv.venta10), 0) as venta,
                COALESCE(SUM(COALESCE((
                    CASE 
                        WHEN v.cod_tipo_prenda = 1 THEN a.prestamo
                        WHEN v.cod_tipo_prenda = 2 THEN au.prestamo
                        WHEN v.cod_tipo_prenda = 3 THEN va.prestamo
                        ELSE 0
                    END
                ), 0)), 0) as costo
             FROM ventas v
             INNER JOIN detalle_venta dv ON dv.cod_venta = v.cod_venta
             LEFT JOIN alhajas a ON v.cod_tipo_prenda = 1 AND dv.cod_prenda = a.cod_alhaja
             LEFT JOIN autos au ON v.cod_tipo_prenda = 2 AND dv.cod_prenda = au.cod_auto
             LEFT JOIN varios va ON v.cod_tipo_prenda = 3 AND dv.cod_prenda = va.cod_varios
             WHERE v.f_venta BETWEEN ? AND ? AND v.f_cancela IS NULL
        ", [$fechaInicio, $fechaFin]);
        $ventaSucursal = (float)$ventas->venta;
        $costoSucursal = (float)$ventas->costo;
        $utilidadSucursal = $ventaSucursal - $costoSucursal;

        // Ratios por sucursal
        $ratioNominaUtilidad = $nominaSucursal > 0 ? ($utilidadSucursal / $nominaSucursal) : 0;
        $porcentajeNominaVsUtilidad = $utilidadSucursal > 0 ? ($nominaSucursal / $utilidadSucursal * 100) : 0;

        $nominaPorSucursal[] = [
            'sucursal' => $sucursal->nombre,
            'nomina' => $nominaSucursal,
            'venta' => $ventaSucursal,
            'utilidad' => $utilidadSucursal,
            'ratio_utilidad_nomina' => round($ratioNominaUtilidad, 2),
            'pct_nomina_utilidad' => round($porcentajeNominaVsUtilidad, 1)
        ];

        // 3. Valuadores y Calidad (precio remate / monto prestado)
        // Promedio de sucursal para detectar fuera de rango
        $sucursalAvg = DB::connection($conn)->selectOne("
            SELECT 
                AVG(prestamo / NULLIF(avaluo, 0)) as avg_ratio_prestamo_avaluo,
                AVG(avaluo) as avg_avaluo
            FROM contratos
            WHERE f_contrato BETWEEN ? AND ? AND f_cancelacion IS NULL AND activo = 1
        ", [$fechaInicio, $fechaFin]);
        $avgRatioSucursal = (float)($sucursalAvg->avg_ratio_prestamo_avaluo ?? 0.70);

        $valQ = DB::connection($conn)->select("
            SELECT 
                c.cod_usuario,
                u.nombre as valuador,
                COUNT(c.cod_contrato) as total_avaluos,
                SUM(c.prestamo) as total_prestado,
                AVG(c.avaluo) as prom_avaluo,
                AVG(c.prestamo) as prom_prestamo,
                AVG(c.prestamo / NULLIF(c.avaluo, 0)) as ratio_prestamo_avaluo,
                SUM(CASE WHEN c.venta > 0 THEN c.venta ELSE 0 END) as venta_remate_total,
                SUM(CASE WHEN c.venta > 0 THEN c.prestamo ELSE 0 END) as prestamo_remate_total,
                AVG(CASE WHEN c.venta > 0 THEN c.venta / NULLIF(c.prestamo, 0) ELSE NULL END) as ratio_calidad
            FROM contratos c
            INNER JOIN usuarios u ON c.cod_usuario = u.cod_usuario
            WHERE c.f_contrato BETWEEN ? AND ? AND c.f_cancelacion IS NULL AND c.activo = 1
            GROUP BY c.cod_usuario, u.nombre
            HAVING total_avaluos >= 5
        ", [$fechaInicio, $fechaFin]);

        foreach ($valQ as $v) {
            $ratioValuador = (float)($v->ratio_prestamo_avaluo ?? 0);
            $desviacion = $avgRatioSucursal > 0 ? (($ratioValuador - $avgRatioSucursal) / $avgRatioSucursal * 100) : 0;
            $ratioCalidad = (float)($v->ratio_calidad ?? 0);
            if ($ratioCalidad == 0 && $v->prestamo_remate_total > 0) {
                $ratioCalidad = (float)($v->venta_remate_total / $v->prestamo_remate_total);
            }

            $alerta = 'Normal';
            if (abs($desviacion) >= 15) {
                $alerta = $desviacion > 0 ? 'Sobrevalúa (Riesgo Alto)' : 'Subvalúa (Riesgo Alto)';
            } elseif (abs($desviacion) >= 8) {
                $alerta = $desviacion > 0 ? 'Ligeramente Alto' : 'Ligeramente Bajo';
            }

            $valuadoresList[] = [
                'sucursal' => $sucursal->nombre,
                'valuador' => $v->valuador,
                'total_avaluos' => (int)$v->total_avaluos,
                'total_prestado' => (float)$v->total_prestado,
                'prom_avaluo' => (float)$v->prom_avaluo,
                'prom_prestamo' => (float)$v->prom_prestamo,
                'ratio_prestamo_avaluo' => round($ratioValuador * 100, 1),
                'ratio_calidad' => round($ratioCalidad, 2), // Precio remate / prestado
                'desviacion_sucursal' => round($desviacion, 1),
                'alerta' => $alerta
            ];
        }

    } catch (\Exception $e) {
        echo "Error in {$sucursal->nombre}: " . $e->getMessage() . "\n";
    }
}

echo "Sucursales analizadas: " . count($nominaPorSucursal) . "\n";
echo "Total valuadores: " . count($valuadoresList) . "\n";
echo "Top 3 sucursales por ratio utilidad/nomina:\n";
usort($nominaPorSucursal, function($a, $b) { return $b['ratio_utilidad_nomina'] <=> $a['ratio_utilidad_nomina']; });
print_r(array_slice($nominaPorSucursal, 0, 3));

echo "\nTop 3 valuadores por calidad:\n";
usort($valuadoresList, function($a, $b) { return $b['ratio_calidad'] <=> $a['ratio_calidad']; });
print_r(array_slice($valuadoresList, 0, 3));

echo "\nValuadores fuera de rango (Alerta):\n";
$fueraDeRango = array_filter($valuadoresList, function($v) { return strpos($v['alerta'], 'Riesgo Alto') !== false; });
print_r(array_slice($fueraDeRango, 0, 3));
