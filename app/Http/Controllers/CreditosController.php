<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

use App\Services\PowerBIPermissionService;

class CreditosController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $regionales = PowerBIPermissionService::getRegionales();
        $regionalUserId = $request->input('regional_user_id');
        $sucursales = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);

        return view('creditos.index', compact('fechaInicio', 'fechaFin', 'sucursales', 'regionales', 'regionalUserId'));
    }

    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        
        $regionalUserId = $request->input('regional_user_id');
        $sucursales = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);
        $sucursalId = $request->input('sucursal_id');
        $sucursalesSeleccionadas = $sucursalId
            ? $sucursales->where('id_valora_mas', $sucursalId)
            : $sucursales;

        $baseConfig = Config::get('database.connections.mysql');

        $saldoCartera = 0;
        $creditosNuevosMonto = 0;
        $creditosNuevosCantidad = 0;
        $saldoVencido = 0;
        $capitalCobrado = 0;
        $interesesGenerados = 0;

        $rangosMora = [
            '0-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '90+' => 0
        ];

        $heatmapMorosidad = []; 
        $topCreditos = [];
        $timelineData = [];
        $vintageData = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'creditos_dynamic';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // 1. Cartera General y Colocación
                $cartera = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(adeudo), 0) as saldo,
                        COALESCE(SUM(CASE WHEN f_autorizado BETWEEN ? AND ? THEN monto_credito ELSE 0 END), 0) as nuevos_monto,
                        SUM(CASE WHEN f_autorizado BETWEEN ? AND ? THEN 1 ELSE 0 END) as nuevos_cantidad,
                        COALESCE(SUM(CASE WHEN f_autorizado BETWEEN ? AND ? THEN interes ELSE 0 END), 0) as intereses_generados
                    FROM creditos
                    WHERE cod_estatus <> 3 AND adeudo > 0
                ", [$fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin]);

                $saldoCartera += (float) $cartera->saldo;
                $creditosNuevosMonto += (float) $cartera->nuevos_monto;
                $creditosNuevosCantidad += (int) $cartera->nuevos_cantidad;
                $interesesGenerados += (float) $cartera->intereses_generados;

                // 2. Pagos / Recuperación
                $pagos = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(pagado), 0) as capital_cobrado
                    FROM detalle_credito
                    WHERE f_pago BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);

                $capitalCobrado += (float) $pagos->capital_cobrado;

                // 3. Morosidad por contrato y rangos
                $moraQuery = DB::connection($connectionName)->select("
                    SELECT 
                        dc.cod_credito,
                        SUM(dc.adeudo) as adeudo_vencido,
                        MAX(DATEDIFF(NOW(), dc.f_pago_requerida)) as dias_atraso
                    FROM detalle_credito dc
                    INNER JOIN creditos c ON c.cod_credito = dc.cod_credito
                    WHERE dc.f_pago_requerida < NOW() AND dc.adeudo > 0 AND c.cod_estatus <> 3
                    GROUP BY dc.cod_credito
                ");

                $sucMora = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];

                foreach ($moraQuery as $mq) {
                    $dias = (int) $mq->dias_atraso;
                    $adeudo = (float) $mq->adeudo_vencido;
                    $saldoVencido += $adeudo;

                    if ($dias <= 30) {
                        $rangosMora['0-30'] += $adeudo;
                        $sucMora['0-30'] += $adeudo;
                    } elseif ($dias <= 60) {
                        $rangosMora['31-60'] += $adeudo;
                        $sucMora['31-60'] += $adeudo;
                    } elseif ($dias <= 90) {
                        $rangosMora['61-90'] += $adeudo;
                        $sucMora['61-90'] += $adeudo;
                    } else {
                        $rangosMora['90+'] += $adeudo;
                        $sucMora['90+'] += $adeudo;
                    }
                }

                $sucTotalVencido = array_sum($sucMora);
                $sucSaldoCartera = (float) $cartera->saldo;
                $sucIndiceMora = $sucSaldoCartera > 0 ? ($sucTotalVencido / $sucSaldoCartera) * 100 : 0;

                $heatmapMorosidad[] = [
                    'sucursal' => $sucursal->nombre,
                    'mora' => $sucMora,
                    'total_vencido' => $sucTotalVencido,
                    'saldo_cartera' => $sucSaldoCartera,
                    'indice_mora' => $sucIndiceMora
                ];

                // 4. Timeline mensual (Saldo vs Colocación últimos 12 meses)
                $monthly = DB::connection($connectionName)->select("
                    SELECT 
                        DATE_FORMAT(f_autorizado, '%Y-%m') as mes,
                        COALESCE(SUM(monto_credito), 0) as colocacion,
                        COALESCE(SUM(adeudo), 0) as saldo
                    FROM creditos
                    WHERE cod_estatus <> 3 AND f_autorizado >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(f_autorizado, '%Y-%m')
                    ORDER BY mes ASC
                ");

                foreach ($monthly as $m) {
                    if (!isset($timelineData[$m->mes])) {
                        $timelineData[$m->mes] = ['colocacion' => 0, 'saldo' => 0];
                    }
                    $timelineData[$m->mes]['colocacion'] += (float)$m->colocacion;
                    $timelineData[$m->mes]['saldo'] += (float)$m->saldo;
                }

                // 5. Curva de Cosecha / Vintage (Cohortes de colocación vs morosidad a 30, 60, 90 días)
                $vintages = DB::connection($connectionName)->select("
                    SELECT 
                        DATE_FORMAT(c.f_autorizado, '%Y-%m') as mes,
                        COALESCE(SUM(c.monto_credito), 0) as monto_colocado,
                        COALESCE(SUM(CASE WHEN m.dias > 30 THEN c.adeudo ELSE 0 END), 0) as mora_30,
                        COALESCE(SUM(CASE WHEN m.dias > 60 THEN c.adeudo ELSE 0 END), 0) as mora_60,
                        COALESCE(SUM(CASE WHEN m.dias > 90 THEN c.adeudo ELSE 0 END), 0) as mora_90
                    FROM creditos c
                    LEFT JOIN (
                        SELECT cod_credito, MAX(DATEDIFF(NOW(), f_pago_requerida)) as dias
                        FROM detalle_credito
                        WHERE adeudo > 0 AND f_pago_requerida < NOW()
                        GROUP BY cod_credito
                    ) m ON m.cod_credito = c.cod_credito
                    WHERE c.cod_estatus <> 3 AND c.f_autorizado >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(c.f_autorizado, '%Y-%m')
                    ORDER BY mes ASC
                ");

                foreach ($vintages as $v) {
                    if (!isset($vintageData[$v->mes])) {
                        $vintageData[$v->mes] = ['colocado' => 0, 'mora30' => 0, 'mora60' => 0, 'mora90' => 0];
                    }
                    $vintageData[$v->mes]['colocado'] += (float)$v->monto_colocado;
                    $vintageData[$v->mes]['mora30'] += (float)$v->mora_30;
                    $vintageData[$v->mes]['mora60'] += (float)$v->mora_60;
                    $vintageData[$v->mes]['mora90'] += (float)$v->mora_90;
                }

                // 6. Detalle Top Créditos
                $lista = DB::connection($connectionName)->select("
                    SELECT 
                        c.cod_credito,
                        UPPER(TRIM(CONCAT(cl.nombre, ' ', cl.a_paterno, ' ', cl.a_materno))) as nombre_cliente,
                        c.monto_credito,
                        c.adeudo,
                        c.interes,
                        c.cod_estatus
                    FROM creditos c
                    LEFT JOIN clientes cl ON cl.cod_cliente = c.cod_cliente
                    WHERE c.adeudo > 0 AND c.cod_estatus <> 3
                    ORDER BY c.adeudo DESC
                    LIMIT 15
                ");

                foreach ($lista as $l) {
                    $topCreditos[] = [
                        'sucursal' => $sucursal->nombre,
                        'cliente' => $l->nombre_cliente,
                        'monto_original' => $l->monto_credito,
                        'saldo_actual' => $l->adeudo,
                        'intereses' => $l->interes,
                        'estatus' => $l->cod_estatus
                    ];
                }

            } catch (\Exception $e) {
                Log::error("Error creditos {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // ================= KPIs Finales =================
        $indiceMorosidad = $saldoCartera > 0 ? ($saldoVencido / $saldoCartera) * 100 : 0;
        $capitalOtorgado = $creditosNuevosMonto; 
        $recuperacionPorcentaje = $capitalOtorgado > 0 ? ($capitalCobrado / $capitalOtorgado) * 100 : 0;
        $interesesCobrados = $capitalCobrado * 0.20;
        $tasaEfectivaRendimiento = $saldoCartera > 0 ? ($interesesGenerados / $saldoCartera) * 100 : 0;

        $chartCarteraMora = [
            'labels' => ['0-30 días', '31-60 días', '61-90 días', 'Más de 90 días'],
            'data' => [
                $rangosMora['0-30'],
                $rangosMora['31-60'],
                $rangosMora['61-90'],
                $rangosMora['90+']
            ]
        ];

        // Ordenar top creditos
        usort($topCreditos, fn($a, $b) => $b['saldo_actual'] <=> $a['saldo_actual']);
        $topCreditos = array_slice($topCreditos, 0, 25);

        // Formatear Timeline mensual para gráfico de líneas
        ksort($timelineData);
        $timelineLabels = array_keys($timelineData);
        $timelineColocacion = [];
        $timelineSaldo = [];
        foreach ($timelineLabels as $mes) {
            $timelineColocacion[] = round($timelineData[$mes]['colocacion'], 2);
            $timelineSaldo[] = round($timelineData[$mes]['saldo'], 2);
        }

        $chartSaldoColocacion = [
            'labels' => $timelineLabels,
            'colocacion' => $timelineColocacion,
            'saldo' => $timelineSaldo
        ];

        // Formatear Curva de Cosecha / Vintage
        ksort($vintageData);
        $vintageLabels = array_keys($vintageData);
        $vintageMora30 = [];
        $vintageMora60 = [];
        $vintageMora90 = [];
        foreach ($vintageLabels as $mes) {
            $col = $vintageData[$mes]['colocado'];
            $vintageMora30[] = $col > 0 ? round(($vintageData[$mes]['mora30'] / $col) * 100, 2) : 0;
            $vintageMora60[] = $col > 0 ? round(($vintageData[$mes]['mora60'] / $col) * 100, 2) : 0;
            $vintageMora90[] = $col > 0 ? round(($vintageData[$mes]['mora90'] / $col) * 100, 2) : 0;
        }

        $chartVintage = [
            'labels' => $vintageLabels,
            'mora30' => $vintageMora30,
            'mora60' => $vintageMora60,
            'mora90' => $vintageMora90
        ];

        // Ordenar heatmap por mayor saldo de cartera
        usort($heatmapMorosidad, fn($a, $b) => $b['saldo_cartera'] <=> $a['saldo_cartera']);

        return response()->json([
            'saldoCartera' => $saldoCartera,
            'creditosNuevosMonto' => $creditosNuevosMonto,
            'creditosNuevosCantidad' => $creditosNuevosCantidad,
            'indiceMorosidad' => $indiceMorosidad,
            'saldoVencido' => $saldoVencido,
            'capitalCobrado' => $capitalCobrado,
            'capitalOtorgado' => $capitalOtorgado,
            'recuperacionPorcentaje' => $recuperacionPorcentaje,
            'interesesGenerados' => $interesesGenerados,
            'interesesCobrados' => $interesesCobrados,
            'tasaEfectivaRendimiento' => $tasaEfectivaRendimiento,
            'chartCarteraMora' => $chartCarteraMora,
            'chartSaldoColocacion' => $chartSaldoColocacion,
            'chartVintage' => $chartVintage,
            'heatmapMorosidad' => $heatmapMorosidad,
            'topCreditos' => $topCreditos
        ]);
    }
}
