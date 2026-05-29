<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class InventarioApartadosController extends Controller
{
    public function index()
    {
        $fechaInicio = now()->startOfMonth()->toDateString() . ' 00:00:00';
        $fechaFin = now()->toDateString() . ' 23:59:59';
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('inventario-apartados.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        $fechaFinSiguiente = $fechaFin;

        $sucursalId = $request->input('sucursal_id');
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        
        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');
        
        // Variables acumuladoras
        $totalInventario = 0;
        $inventarioInicialGlobal = 0;
        $apartadoMontoGlobal = 0;
        $abonoExtemporaneoGlobal = 0;
        $liquidacionMontoGlobal = 0;
        $remateGlobal = 0;
        $totalArticulos = 0;
        $chartSucursales = ['labels' => [], 'valores' => [], 'antiguedad' => []];
        $topArticulosAnejos = [];
        $chartDistribucionAntiguedadData = [
            'data_oro' => [0, 0, 0, 0],
            'data_varios' => [0, 0, 0, 0]
        ];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi_apartados_' . $sucursal->id_valora_mas;

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                // ========== 1. INVENTARIO EN APARTADOS (RECONSTRUCCIÓN HISTÓRICA) ==========
                $items = DB::connection($connectionName)->select("
                    SELECT 
                        'ALHAJA' as tipo,
                        pre.prenda as id,
                        a.prestamo,
                        a.precio,
                        ap.f_apartado as fecha,
                        a.kilataje,
                        CASE 
                            WHEN a.kilataje BETWEEN 500 AND 999 THEN 'Plata'
                            WHEN a.kilataje BETWEEN 8 AND 26 THEN 'Oro'
                            ELSE 'Varios'
                        END as categoria
                    FROM alhajas a
                    INNER JOIN prendas pre ON pre.cod_prenda = a.cod_prenda AND pre.cod_tipo_prenda = 1
                    INNER JOIN detalle_apartado da ON da.cod_prenda = a.cod_alhaja
                    INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado AND ap.cod_tipo_prenda = 1
                    WHERE ap.f_apartado <= :f2_1
                      AND (
                          a.cod_estatus_prenda = 4
                          OR (a.cod_estatus_prenda = 3
                              AND EXISTS (
                                  SELECT 1 FROM apartado_pagos apg
                                  INNER JOIN movimientos m ON m.cod_movimiento = apg.cod_movimiento
                                  WHERE apg.cod_apartado = ap.cod_apartado
                                    AND m.cod_tipo_movimiento = 12
                                    AND apg.f_cancela IS NULL
                                    AND CAST(apg.f_pago AS DATE) > :f2_2
                                ))
                      )

                    UNION ALL

                    SELECT 
                        'VARIOS' as tipo,
                        pre.prenda as id,
                        v.prestamo,
                        v.precio,
                        ap.f_apartado as fecha,
                        0 as kilataje,
                        'Varios' as categoria
                    FROM varios v
                    INNER JOIN prendas pre ON pre.cod_prenda = v.cod_prenda AND pre.cod_tipo_prenda = 3
                    INNER JOIN detalle_apartado da ON da.cod_prenda = v.cod_varios
                    INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado AND ap.cod_tipo_prenda = 3
                    WHERE ap.f_apartado <= :f2_3
                      AND (
                          v.cod_estatus_prenda = 4
                          OR (v.cod_estatus_prenda = 3
                              AND EXISTS (
                                  SELECT 1 FROM apartado_pagos apg
                                  INNER JOIN movimientos m ON m.cod_movimiento = apg.cod_movimiento
                                  WHERE apg.cod_apartado = ap.cod_apartado
                                    AND m.cod_tipo_movimiento = 12
                                    AND apg.f_cancela IS NULL
                                    AND CAST(apg.f_pago AS DATE) > :f2_4
                                ))
                      )

                    UNION ALL

                    SELECT 
                        'AUTO' as tipo,
                        pre.prenda as id,
                        au.prestamo,
                        au.precio,
                        ap.f_apartado as fecha,
                        0 as kilataje,
                        'Autos' as categoria
                    FROM autos au
                    INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                    INNER JOIN detalle_apartado da ON da.cod_prenda = au.cod_auto
                    INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado AND ap.cod_tipo_prenda = 2
                    WHERE ap.f_apartado <= :f2_5
                      AND (
                          au.cod_estatus_prenda = 4
                          OR (au.cod_estatus_prenda = 3
                              AND EXISTS (
                                  SELECT 1 FROM apartado_pagos apg
                                  INNER JOIN movimientos m ON m.cod_movimiento = apg.cod_movimiento
                                  WHERE apg.cod_apartado = ap.cod_apartado
                                    AND m.cod_tipo_movimiento = 12
                                    AND apg.f_cancela IS NULL
                                    AND CAST(apg.f_pago AS DATE) > :f2_6
                                ))
                      )
                ", [
                    ':f2_1' => $fechaFin, ':f2_2' => $fechaFin,
                    ':f2_3' => $fechaFin, ':f2_4' => $fechaFin,
                    ':f2_5' => $fechaFin, ':f2_6' => $fechaFin
                ]);

                $fechaFiltro = \Carbon\Carbon::parse($fechaFin);

                $inventarioSucursal = 0;
                $sucDias = 0;
                $sucCount = 0;

                foreach ($items as $item) {
                    $dias = $item->fecha ? $fechaFiltro->diffInDays($item->fecha) : 0;
                    $valor = (float) $item->prestamo;

                    $inventarioSucursal += $valor;
                    $totalArticulos++;
                    $sucDias += $dias;
                    $sucCount++;

                    // Gráfico de distribución de antigüedad por familia
                    if ($item->categoria === 'Oro') {
                        if ($dias <= 30) $chartDistribucionAntiguedadData['data_oro'][0]++;
                        elseif ($dias <= 60) $chartDistribucionAntiguedadData['data_oro'][1]++;
                        elseif ($dias <= 90) $chartDistribucionAntiguedadData['data_oro'][2]++;
                        else $chartDistribucionAntiguedadData['data_oro'][3]++;
                    } else {
                        if ($dias <= 30) $chartDistribucionAntiguedadData['data_varios'][0]++;
                        elseif ($dias <= 60) $chartDistribucionAntiguedadData['data_varios'][1]++;
                        elseif ($dias <= 90) $chartDistribucionAntiguedadData['data_varios'][2]++;
                        else $chartDistribucionAntiguedadData['data_varios'][3]++;
                    }

                    // Top Artículos
                    $topArticulosAnejos[] = [
                        'articulo' => $item->id,
                        'id' => $item->id,
                        'familia' => $item->categoria,
                        'tipo' => $item->tipo,
                        'sucursal' => $sucursal->nombre,
                        'valor' => $valor,
                        'dias' => $dias
                    ];
                }

                $totalInventario += $inventarioSucursal;
                Log::info("INVENTARIO APARTADOS - {$sucursal->nombre}: " . $inventarioSucursal);

                // ========== Inventario Inicial de Apartados (antes de la fecha de inicio) ==========
                $invInicialQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(prestamo), 0) AS total
                    FROM (
                        SELECT a.prestamo
                        FROM alhajas a
                        INNER JOIN detalle_apartado da ON da.cod_prenda = a.cod_alhaja
                        INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado AND ap.cod_tipo_prenda = 1
                        WHERE ap.f_apartado < :f_ini_1
                          AND (
                              a.cod_estatus_prenda = 4
                              OR (a.cod_estatus_prenda = 3
                                  AND EXISTS (
                                      SELECT 1 FROM apartado_pagos apg
                                      INNER JOIN movimientos m ON m.cod_movimiento = apg.cod_movimiento
                                      WHERE apg.cod_apartado = ap.cod_apartado
                                        AND m.cod_tipo_movimiento = 12
                                        AND apg.f_cancela IS NULL
                                        AND CAST(apg.f_pago AS DATE) >= :f_ini_2
                                    ))
                          )

                        UNION ALL

                        SELECT v.prestamo
                        FROM varios v
                        INNER JOIN prendas pre ON pre.cod_prenda = v.cod_prenda AND pre.cod_tipo_prenda = 3
                        INNER JOIN detalle_apartado da ON da.cod_prenda = v.cod_varios
                        INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado AND ap.cod_tipo_prenda = 3
                        WHERE ap.f_apartado < :f_ini_3
                          AND (
                              v.cod_estatus_prenda = 4
                              OR (v.cod_estatus_prenda = 3
                                  AND EXISTS (
                                      SELECT 1 FROM apartado_pagos apg
                                      INNER JOIN movimientos m ON m.cod_movimiento = apg.cod_movimiento
                                      WHERE apg.cod_apartado = ap.cod_apartado
                                        AND m.cod_tipo_movimiento = 12
                                        AND apg.f_cancela IS NULL
                                        AND CAST(apg.f_pago AS DATE) >= :f_ini_4
                                    ))
                          )

                        UNION ALL

                        SELECT au.prestamo
                        FROM autos au
                        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                        INNER JOIN detalle_apartado da ON da.cod_prenda = au.cod_auto
                        INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado AND ap.cod_tipo_prenda = 2
                        WHERE ap.f_apartado < :f_ini_5
                          AND (
                              au.cod_estatus_prenda = 4
                              OR (au.cod_estatus_prenda = 3
                                  AND EXISTS (
                                      SELECT 1 FROM apartado_pagos apg
                                      INNER JOIN movimientos m ON m.cod_movimiento = apg.cod_movimiento
                                      WHERE apg.cod_apartado = ap.cod_apartado
                                        AND m.cod_tipo_movimiento = 12
                                        AND apg.f_cancela IS NULL
                                        AND CAST(apg.f_pago AS DATE) >= :f_ini_6
                                    ))
                          )
                    ) as t
                ", [
                    ':f_ini_1' => $fechaInicio, ':f_ini_2' => $fechaInicio,
                    ':f_ini_3' => $fechaInicio, ':f_ini_4' => $fechaInicio,
                    ':f_ini_5' => $fechaInicio, ':f_ini_6' => $fechaInicio
                ]);
                $inventarioInicialGlobal += (float)($invInicialQ->total ?? 0);

                // ========== 2. APARTADOS (ANTICIPO DE APARTADO - tipo 7) ==========
                $apartados = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_apartado
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 7
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);
                
                $apartadoMontoGlobal += (float) ($apartados->total_apartado ?? 0);
                Log::info("APARTADO tipo7 - {$sucursal->nombre}: " . ($apartados->total_apartado ?? 0));

                // ========== 3. ABONOS (ABONO DE APARTADO - tipo 8) ==========
                $abonos = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_abonos
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 8
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);
                
                $abonoExtemporaneoGlobal += (float) ($abonos->total_abonos ?? 0);
                Log::info("ABONO tipo8 - {$sucursal->nombre}: " . ($abonos->total_abonos ?? 0));

                // ========== 4. LIQUIDACIONES DE APARTADOS (tipo 12) - EGRESOS ==========
                $liquidaciones = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_liquidacion
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 12
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);
                
                $liquidacionMontoGlobal += (float) ($liquidaciones->total_liquidacion ?? 0);
                Log::info("LIQUIDACION tipo12 - {$sucursal->nombre}: " . ($liquidaciones->total_liquidacion ?? 0));

                // ========== 5. Gráfico por sucursal ==========
                $chartSucursales['labels'][] = $sucursal->nombre;
                $chartSucursales['valores'][] = $inventarioSucursal;
                $chartSucursales['antiguedad'][] = $sucCount > 0 ? $sucDias / $sucCount : 0;

            } catch (\Exception $e) {
                Log::error("Error en InventarioApartados para sucursal {$sucursal->nombre}: " . $e->getMessage());
                continue;
            }
        }

        // Cálculos finales
        $totalIngresos = $inventarioInicialGlobal + $apartadoMontoGlobal + $abonoExtemporaneoGlobal;
        $totalEgresos = $liquidacionMontoGlobal + $remateGlobal;
        $inventarioApartadosNeto = $totalIngresos - $totalEgresos;

        // Ordenar y limitar artículos añejos
        usort($topArticulosAnejos, function($a, $b) { return $b['dias'] <=> $a['dias']; });
        $topArticulosAnejos = array_slice($topArticulosAnejos, 0, 10);

        Log::info("========== RESULTADOS FINALES APARTADOS ==========");
        Log::info("Inventario Inicial: " . $inventarioInicialGlobal);
        Log::info("Apartados (tipo7): " . $apartadoMontoGlobal);
        Log::info("Abonos (tipo8): " . $abonoExtemporaneoGlobal);
        Log::info("TOTAL INGRESOS: " . $totalIngresos);
        Log::info("Liquidaciones (tipo12): " . $liquidacionMontoGlobal);
        Log::info("Remate: " . $remateGlobal);
        Log::info("TOTAL EGRESOS: " . $totalEgresos);
        Log::info("NETO APARTADOS: " . $inventarioApartadosNeto);

        return response()->json([
            'ingresosTotales' => $totalIngresos,
            'egresosTotales' => $totalEgresos,
            'inventarioApartadosNeto' => $inventarioApartadosNeto,
            'valorTotalInventario' => $totalInventario,
            'inventarioInicial' => $inventarioInicialGlobal,
            'apartado' => $apartadoMontoGlobal,
            'abonoExtemporaneo' => $abonoExtemporaneoGlobal,
            'liquidacion' => $liquidacionMontoGlobal,
            'remate' => $remateGlobal,
            'valorVentaTotal' => $totalInventario,
            'cantidadTotal' => $totalArticulos,
            'topArticulosAnejos' => $topArticulosAnejos,
            'chartDistribucionAntiguedad' => [
                'labels' => ['0-30 días', '31-60 días', '61-90 días', '>90 días'],
                'data_oro' => $chartDistribucionAntiguedadData['data_oro'],
                'data_varios' => $chartDistribucionAntiguedadData['data_varios']
            ],
            'chartValorAntiguedadSucursal' => $chartSucursales
        ]);
    }
}
