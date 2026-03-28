<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class CreditosController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('creditos.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
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

                // 3. Morosidad por contrato
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

                $sucMora = ['0-30'=>0, '31-60'=>0, '61-90'=>0, '90+'=>0];

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

                $heatmapMorosidad[] = [
                    'sucursal' => $sucursal->nombre,
                    'mora' => $sucMora
                ];

                // 4. Detalle Top Créditos
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
                    LIMIT 10
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
        
        // Asumimos que los cobrados son un % del capitalCobrado, o simplemente los listamos
        $interesesCobrados = $capitalCobrado * 0.20; // Estimación si no hay split exacto
        
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
        $topCreditos = array_slice($topCreditos, 0, 15);

        $chartSaldoColocacion = [
            'labels' => ['Colocación Periodo', 'Cobranza Periodo'],
            'colocacion' => [$creditosNuevosMonto, 0],
            'saldo' => [0, $capitalCobrado]
        ];

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
            'heatmapMorosidad' => $heatmapMorosidad,
            'topCreditos' => $topCreditos
        ]);
    }
}
