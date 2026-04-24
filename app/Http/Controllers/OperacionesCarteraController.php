<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class OperacionesCarteraController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('operaciones-cartera.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        ini_set('max_execution_time', 120);
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFinQuery = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        
        $sucursalId = $request->input('sucursal_id');
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');

        // Variables globales para Respuesta
        $data = [
            'empenos' => [
                'total_contratos' => 0,
                'monto_total' => 0,
                'oro' => ['contratos' => 0, 'monto' => 0],
                'varios' => ['contratos' => 0, 'monto' => 0],
                'auto' => ['contratos' => 0, 'monto' => 0],
                'avaluo_total' => 0
            ],
            'refrendos' => ['total' => 0, 'monto' => 0],
            'desempenos' => ['total' => 0, 'monto' => 0],
            'cartera' => [
                'vigente' => 0,
                'vencida' => 0,
                'oro' => 0,
                'varios' => 0,
                'auto' => 0,
            ],
            'tiempos' => [
                'dias_empeno_desempeno' => 0,
                'total_desempenos_con_dias' => 0
            ],
            'intereses' => [
                'cobrados' => 0
            ],
            'mora' => [
                '0_30' => 0,
                '31_60' => 0,
                '61_90' => 0,
                'mas_90' => 0
            ],
            'rankings' => [
                'articulos_empenados' => [],
                'articulos_desempenados' => []
            ]
        ];

        // Recolectores para promedios y rankings globales
        $rankingsEmpenados = [];
        $rankingsDesempenados = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi_' . $sucursal->id_valora_mas; // Nombre único por sucursal

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                }

                // 1. Empeños por tipo (Movimiento 1)
                $empenosQ = DB::connection($connectionName)->select("
                    SELECT 
                        con.cod_tipo_prenda,
                        COUNT(DISTINCT mo.cod_movimiento) as total,
                        COALESCE(SUM(mo.monto10), 0) as prestamo,
                        COALESCE(SUM(al.precio), 0) as avaluo_alhajas,
                        COALESCE(SUM(au.precio), 0) as avaluo_autos,
                        COALESCE(SUM(va.precio), 0) as avaluo_varios
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                    LEFT JOIN autos au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                    LEFT JOIN varios va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                    WHERE mo.cod_tipo_movimiento = 1 
                      AND mo.f_cancela IS NULL
                      AND con.f_cancelacion IS NULL
                      AND mo.f_alta BETWEEN :fIni AND :fFin
                    GROUP BY con.cod_tipo_prenda
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);

                foreach ($empenosQ as $row) {
                    $data['empenos']['total_contratos'] += $row->total;
                    $data['empenos']['monto_total'] += $row->prestamo;
                    
                    if ($row->cod_tipo_prenda == 1) { // Oro/Alhajas
                        $data['empenos']['oro']['contratos'] += $row->total;
                        $data['empenos']['oro']['monto'] += $row->prestamo;
                        $data['empenos']['avaluo_total'] += $row->avaluo_alhajas;
                    } elseif ($row->cod_tipo_prenda == 2) { // Auto
                        $data['empenos']['auto']['contratos'] += $row->total;
                        $data['empenos']['auto']['monto'] += $row->prestamo;
                        $data['empenos']['avaluo_total'] += $row->avaluo_autos;
                    } elseif ($row->cod_tipo_prenda == 3) { // Varios
                        $data['empenos']['varios']['contratos'] += $row->total;
                        $data['empenos']['varios']['monto'] += $row->prestamo;
                        $data['empenos']['avaluo_total'] += $row->avaluo_varios;
                    }
                }

                // 2. Refrendos (Movimientos 2 y 3)
                $refrendosRes = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COUNT(DISTINCT mo.cod_movimiento) as total,
                        COALESCE(SUM(mo.monto10), 0) as monto
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento IN (2, 3) 
                      AND mo.f_cancela IS NULL
                      AND mo.f_alta BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);
                
                $data['refrendos']['total'] += (int)($refrendosRes->total ?? 0);
                $data['refrendos']['monto'] += (float)($refrendosRes->monto ?? 0);

                // 3. Desempeños (Movimiento 4)
                $desempenosRes = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COUNT(DISTINCT mo.cod_movimiento) as total,
                        COALESCE(SUM(mo.monto10), 0) as monto
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 4 
                      AND mo.f_cancela IS NULL
                      AND mo.f_alta BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);

                $data['desempenos']['total'] += (int)($desempenosRes->total ?? 0);
                $data['desempenos']['monto'] += (float)($desempenosRes->monto ?? 0);

                // 4. Inventario y Cartera Vigente/Vencida
                $inventarioResult = DB::connection($connectionName)->select("
                    SELECT
                        'Alhaja' AS Tipo,
                        cod_estatus_prenda,
                        COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM alhajas WHERE cod_estatus_prenda IN (1,9) GROUP BY cod_estatus_prenda
                    UNION ALL
                    SELECT 
                        'Varios' AS Tipo, cod_estatus_prenda, COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM varios WHERE cod_estatus_prenda IN (1,9) GROUP BY cod_estatus_prenda
                    UNION ALL
                    SELECT 
                        'Auto' AS Tipo, cod_estatus_prenda, COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM autos WHERE cod_estatus_prenda IN (1,9) GROUP BY cod_estatus_prenda
                ");

                foreach ($inventarioResult as $inv) {
                    $monto = (float)$inv->total_prestamo;
                    if ($inv->cod_estatus_prenda == 1) { // Vigente
                        $data['cartera']['vigente'] += $monto;
                    } elseif ($inv->cod_estatus_prenda == 9) { // Vencida/Piso de venta
                        $data['cartera']['vencida'] += $monto;
                    }

                    if ($inv->Tipo == 'Alhaja') $data['cartera']['oro'] += $monto;
                    if ($inv->Tipo == 'Varios') $data['cartera']['varios'] += $monto;
                    if ($inv->Tipo == 'Auto') $data['cartera']['auto'] += $monto;
                }

                // 5. Intereses Cobrados
                $interesesQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN mo.cod_tipo_movimiento = 4 THEN mo.monto10 - con.prestamo
                            WHEN mo.cod_tipo_movimiento IN (2, 3) THEN mo.monto10 - COALESCE(ca.abono, 0)
                            ELSE 0
                        END
                    ), 0) AS total_intereses
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    LEFT JOIN contratos ca ON ca.cod_contrato = con.cod_anterior
                    WHERE mo.cod_tipo_movimiento IN (2,3,4) 
                      AND mo.f_cancela IS NULL 
                      AND con.f_cancelacion IS NULL
                      AND mo.f_alta BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);
                $data['intereses']['cobrados'] += (float)($interesesQ->total_intereses ?? 0);

                // 5.1 Días de Mora (distribución)
                $moraQ = DB::connection($connectionName)->select("
                    SELECT 
                        CASE 
                            WHEN DATEDIFF(NOW(), DATE_ADD(con.f_contrato, INTERVAL 30 DAY)) <= 30 THEN '0_30'
                            WHEN DATEDIFF(NOW(), DATE_ADD(con.f_contrato, INTERVAL 30 DAY)) BETWEEN 31 AND 60 THEN '31_60'
                            WHEN DATEDIFF(NOW(), DATE_ADD(con.f_contrato, INTERVAL 30 DAY)) BETWEEN 61 AND 90 THEN '61_90'
                            ELSE 'mas_90'
                        END as rango_mora,
                        COALESCE(SUM(con.prestamo), 0) as monto
                    FROM contratos con
                    WHERE con.f_cancelacion IS NULL
                    GROUP BY rango_mora
                ");
                foreach ($moraQ as $mora) {
                    if (isset($data['mora'][$mora->rango_mora])) {
                        $data['mora'][$mora->rango_mora] += (float)$mora->monto;
                    }
                }

                // 6. Tiempo Promedio de Empeño a Desempeño
                $tiempoQ = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COUNT(*) as count_dias,
                        COALESCE(SUM(DATEDIFF(mo.f_alta, con.f_contrato)), 0) as sum_dias
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE mo.cod_tipo_movimiento = 4
                      AND mo.f_cancela IS NULL
                      AND mo.f_alta BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);
                $data['tiempos']['dias_empeno_desempeno'] += (int)$tiempoQ->sum_dias;
                $data['tiempos']['total_desempenos_con_dias'] += (int)$tiempoQ->count_dias;

                // 7. Rankings de Artículos Más Empeñados (VERSIÓN OPTIMIZADA)
                $topEmpQ = DB::connection($connectionName)->select("
                    SELECT articulo, SUM(total_movs) as total_movs, SUM(monto) as monto
                    FROM (
                        SELECT pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda AND pre.cod_tipo_prenda = 1
                        WHERE mo.cod_tipo_movimiento = 1 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 1
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1
                        GROUP BY pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                        WHERE mo.cod_tipo_movimiento = 1 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 2
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2
                        GROUP BY pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda AND pre.cod_tipo_prenda = 3
                        WHERE mo.cod_tipo_movimiento = 1 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 3
                          AND mo.f_alta BETWEEN :fIni3 AND :fFin3
                        GROUP BY pre.prenda
                    ) as t
                    GROUP BY articulo
                    ORDER BY total_movs DESC
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFinQuery
                ]);

                foreach ($topEmpQ as $emp) {
                    $key = $emp->articulo;
                    if (!isset($rankingsEmpenados[$key])) {
                        $rankingsEmpenados[$key] = ['articulo' => $key, 'total' => 0, 'monto' => 0];
                    }
                    $rankingsEmpenados[$key]['total'] += (int)$emp->total_movs;
                    $rankingsEmpenados[$key]['monto'] += (float)$emp->monto;
                }

                // 8. Rankings de Artículos Más Desempeñados (VERSIÓN OPTIMIZADA)
                $topDesQ = DB::connection($connectionName)->select("
                    SELECT articulo, SUM(total_movs) as total_movs, SUM(monto) as monto
                    FROM (
                        SELECT pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda AND pre.cod_tipo_prenda = 1
                        WHERE mo.cod_tipo_movimiento = 4 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 1
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1
                        GROUP BY pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                        WHERE mo.cod_tipo_movimiento = 4 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 2
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2
                        GROUP BY pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda AND pre.cod_tipo_prenda = 3
                        WHERE mo.cod_tipo_movimiento = 4 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 3
                          AND mo.f_alta BETWEEN :fIni3 AND :fFin3
                        GROUP BY pre.prenda
                    ) as t
                    GROUP BY articulo
                    ORDER BY total_movs DESC
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFinQuery
                ]);

                foreach ($topDesQ as $des) {
                    $key = $des->articulo;
                    if (!isset($rankingsDesempenados[$key])) {
                        $rankingsDesempenados[$key] = ['articulo' => $key, 'total' => 0, 'monto' => 0];
                    }
                    $rankingsDesempenados[$key]['total'] += (int)$des->total_movs;
                    $rankingsDesempenados[$key]['monto'] += (float)$des->monto;
                }

            } catch (\Exception $e) {
                Log::error("Error procesando sucursal {$sucursal->nombre} ({$dbName}) en OperacionesCartera: " . $e->getMessage());
                continue;
            }
        }

        // Ordenar y limitar rankings a los top 5
        usort($rankingsEmpenados, function($a, $b) { return $b['total'] <=> $a['total']; });
        usort($rankingsDesempenados, function($a, $b) { return $b['total'] <=> $a['total']; });
        
        $data['rankings']['articulos_empenados'] = array_slice($rankingsEmpenados, 0, 5);
        $data['rankings']['articulos_desempenados'] = array_slice($rankingsDesempenados, 0, 5);

        // Promedios derivados
        $data['empenos']['prestamo_promedio'] = $data['empenos']['total_contratos'] > 0 ? 
            $data['empenos']['monto_total'] / $data['empenos']['total_contratos'] : 0;
            
        $data['empenos']['sobreavaluo_pct'] = $data['empenos']['avaluo_total'] > 0 ? 
            ($data['empenos']['monto_total'] / $data['empenos']['avaluo_total']) * 100 : 0;

        $data['tiempos']['promedio_dias'] = $data['tiempos']['total_desempenos_con_dias'] > 0 ? 
            $data['tiempos']['dias_empeno_desempeno'] / $data['tiempos']['total_desempenos_con_dias'] : 0;

        $tasaRealMensual = ($data['cartera']['vigente'] + $data['cartera']['vencida']) > 0 ? 
            ($data['intereses']['cobrados'] / ($data['cartera']['vigente'] + $data['cartera']['vencida'])) * 100 : 0;
        $data['intereses']['tasa_real_mensual_pct'] = $tasaRealMensual;
        $data['intereses']['tasa_real_anual_pct'] = $tasaRealMensual * 12;

        return response()->json($data);
    }
}
