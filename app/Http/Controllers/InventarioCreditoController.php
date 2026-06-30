<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class InventarioCreditoController extends Controller
{
    public function index()
    {
        $fechaInicio = now()->startOfMonth()->toDateString() . ' 00:00:00';
        $fechaFin = now()->toDateString() . ' 23:59:59';
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('inventario-credito.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        $fechaFinSiguiente = $fechaFin;

        $sucursalId = $request->input('sucursal_id');
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        
        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->filter(fn($s) => (string)$s->id_valora_mas === (string)$sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');
        
        // Variables acumuladoras
        $totalInventarioInicialGlobal = 0;
        $totalEngancheGlobal = 0;
        $totalPagoCreditoGlobal = 0;
        $totalLiquidacionGlobal = 0;
        $totalDevolucionGlobal = 0;
        $totalArticulos = 0;
        $chartSucursales = ['labels' => [], 'valores' => [], 'antiguedad' => []];
        $topArticulosAnejos = [];

        $totalDias = 0;
        $count30 = 0;
        $count60 = 0;
        $count90 = 0;
        $r0_30 = 0; $r31_60 = 0; $r61_90 = 0; $r90_plus = 0;

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi_credito_' . $sucursal->id_valora_mas;

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                // ========== 1. INVENTARIO INICIAL (Artículos en crédito estatus 12) ==========
                $inventarioActual = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(prestamo), 0) AS total_prestamo,
                        COUNT(*) AS cantidad
                    FROM (
                        SELECT prestamo FROM alhajas WHERE cod_estatus_prenda = 12
                        UNION ALL
                        SELECT prestamo FROM varios WHERE cod_estatus_prenda = 12
                        UNION ALL
                        SELECT prestamo FROM autos WHERE cod_estatus_prenda = 12
                    ) AS inventario
                ");
                
                $inventarioSucursal = (float) ($inventarioActual->total_prestamo ?? 0);
                $totalInventarioInicialGlobal += $inventarioSucursal;
                $totalArticulos += (int) ($inventarioActual->cantidad ?? 0);
                Log::info("INVENTARIO CREDITO - {$sucursal->nombre}: " . $inventarioSucursal);

                // ========== 2. ENGANCHE DE CRÉDITO (tipo 19) - INGRESO ==========
                $enganche = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_enganche
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 19
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);
                
                $totalEngancheGlobal += (float) ($enganche->total_enganche ?? 0);
                Log::info("ENGANCHE tipo19 - {$sucursal->nombre}: " . ($enganche->total_enganche ?? 0));

                // ========== 3. PAGO CRÉDITO (tipo 20) - INGRESO ==========
                $pagoCredito = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_pago_credito
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 20
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);
                
                $totalPagoCreditoGlobal += (float) ($pagoCredito->total_pago_credito ?? 0);
                Log::info("PAGO CREDITO tipo20 - {$sucursal->nombre}: " . ($pagoCredito->total_pago_credito ?? 0));

                // ========== 4. LIQUIDACIÓN DE CRÉDITO (tipo 21) - EGRESO ==========
                $liquidacion = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_liquidacion
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 21
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);
                
                $totalLiquidacionGlobal += (float) ($liquidacion->total_liquidacion ?? 0);
                Log::info("LIQUIDACION tipo21 - {$sucursal->nombre}: " . ($liquidacion->total_liquidacion ?? 0));

                // ========== 5. DEVOLUCIÓN DE CRÉDITO (tipo 23) - EGRESO ==========
                $devolucion = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_devolucion
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 23
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);
                
                $totalDevolucionGlobal += (float) ($devolucion->total_devolucion ?? 0);
                Log::info("DEVOLUCION tipo23 - {$sucursal->nombre}: " . ($devolucion->total_devolucion ?? 0));

                // Query individual credit items to populate topArticulosAnejos
                $items = DB::connection($connectionName)->select("
                    SELECT 
                        'alhajas' as tipo,
                        pre.prenda as id,
                        a.prestamo,
                        con.f_contrato as fecha,
                        CASE 
                            WHEN a.kilataje BETWEEN 8 AND 26 THEN 'Oro'
                            ELSE 'Varios'
                        END as categoria,
                        pre.cod_prenda
                    FROM alhajas a
                    INNER JOIN prendas pre ON pre.cod_prenda = a.cod_prenda AND pre.cod_tipo_prenda = 1
                    LEFT JOIN contratos con ON con.cod_contrato = a.cod_contrato
                    WHERE a.cod_estatus_prenda = 12

                    UNION ALL

                    SELECT 
                        'varios' as tipo,
                        pre.prenda as id,
                        v.prestamo,
                        con.f_contrato as fecha,
                        'Varios' as categoria,
                        pre.cod_prenda
                    FROM varios v
                    INNER JOIN prendas pre ON pre.cod_prenda = v.cod_prenda AND pre.cod_tipo_prenda = 3
                    LEFT JOIN contratos con ON con.cod_contrato = v.cod_contrato
                    WHERE v.cod_estatus_prenda = 12

                    UNION ALL

                    SELECT 
                        'autos' as tipo,
                        pre.prenda as id,
                        au.prestamo,
                        con.f_contrato as fecha,
                        'Varios' as categoria,
                        pre.cod_prenda
                    FROM autos au
                    INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                    LEFT JOIN contratos con ON con.cod_contrato = au.cod_contrato
                    WHERE au.cod_estatus_prenda = 12
                ");

                $sucCount = 0;
                $sucDias = 0;

                foreach ($items as $item) {
                    $dias = 0;
                    if ($item->fecha) {
                        $dias = (int) now()->diffInDays(\Carbon\Carbon::parse($item->fecha), true);
                    }
                    $valor = (float)$item->prestamo;
                    
                    $totalDias += $dias;
                    $sucDias += $dias;
                    $sucCount++;

                    if ($dias > 90) { $count90++; $count60++; $count30++; }
                    elseif ($dias > 60) { $count60++; $count30++; }
                    elseif ($dias > 30) { $count30++; }

                    if ($dias <= 30) $r0_30++;
                    elseif ($dias <= 60) $r31_60++;
                    elseif ($dias <= 90) $r61_90++;
                    else $r90_plus++;

                    $topArticulosAnejos[] = [
                        'articulo' => $item->id,
                        'cod_prenda' => $item->cod_prenda,
                        'sucursal' => $sucursal->nombre,
                        'familia' => $item->categoria,
                        'dias' => $dias,
                        'valor' => $valor
                    ];
                }

                // Gráfico por sucursal
                $chartSucursales['labels'][] = $sucursal->nombre;
                $chartSucursales['valores'][] = $inventarioSucursal;
                $chartSucursales['antiguedad'][] = $sucCount > 0 ? $sucDias / $sucCount : 0;

            } catch (\Exception $e) {
                Log::error("Error en InventarioCredito para sucursal {$sucursal->nombre}: " . $e->getMessage());
                continue;
            }
        }

        // Cálculos finales
        // INGRESOS = Inventario Inicial + Enganche
        $totalIngresos = $totalInventarioInicialGlobal + $totalEngancheGlobal;
        
        // EGRESOS = Liquidación + Devolución
        $totalEgresos = $totalLiquidacionGlobal + $totalDevolucionGlobal;
        
        // INVENTARIO TOTAL EN CRÉDITOS = Ingresos - Egresos
        $saldoPorCobrarFinal = $totalIngresos - $totalEgresos;

        Log::info("========== RESULTADOS FINALES CREDITO ==========");
        Log::info("Inventario Inicial: " . $totalInventarioInicialGlobal);
        Log::info("Enganche (tipo19): " . $totalEngancheGlobal);
        Log::info("TOTAL INGRESOS: " . $totalIngresos);
        Log::info("Liquidacion (tipo21): " . $totalLiquidacionGlobal);
        Log::info("Devolucion (tipo23): " . $totalDevolucionGlobal);
        Log::info("TOTAL EGRESOS: " . $totalEgresos);
        Log::info("INVENTARIO TOTAL EN CRÉDITOS: " . $saldoPorCobrarFinal);

        // Calcular métricas basadas en items de crédito reales
        $totalArticulosN = count($topArticulosAnejos);
        $antiguedadPromedio = $totalArticulosN > 0 ? $totalDias / $totalArticulosN : 0;
        $porcentajeMas30 = $totalArticulosN > 0 ? ($count30 / $totalArticulosN) * 100 : 0;
        $porcentajeMas60 = $totalArticulosN > 0 ? ($count60 / $totalArticulosN) * 100 : 0;
        $porcentajeMas90 = $totalArticulosN > 0 ? ($count90 / $totalArticulosN) * 100 : 0;

        $rotacionInventario = $saldoPorCobrarFinal > 0 ? ($totalPagoCreditoGlobal / $saldoPorCobrarFinal) : 0;

        // Ordenar y limitar al top 10
        usort($topArticulosAnejos, fn($a, $b) => $b['dias'] <=> $a['dias']);
        $topArticulosAnejos = array_slice($topArticulosAnejos, 0, 10);

        return response()->json([
            'ingresosTotales' => $totalIngresos,
            'egresosTotales' => $totalEgresos,
            'saldoPorCobrar' => $saldoPorCobrarFinal,
            'inventarioInicial' => $totalInventarioInicialGlobal,
            'enganche' => $totalEngancheGlobal,
            'pagoCredito' => $totalPagoCreditoGlobal,
            'liquidacion' => $totalLiquidacionGlobal,
            'devolucion' => $totalDevolucionGlobal,
            'valorTotalInventario' => $totalInventarioInicialGlobal,
            'valorVentaTotal' => $totalInventarioInicialGlobal,
            'totalArticulosN' => $totalArticulosN,
            'antiguedadPromedioDias' => round($antiguedadPromedio, 1),
            'porcentajeMas30' => round($porcentajeMas30, 1),
            'porcentajeMas60' => round($porcentajeMas60, 1),
            'porcentajeMas90' => round($porcentajeMas90, 1),
            'rotacionInventario' => round($rotacionInventario, 2),
            'topArticulosAnejos' => $topArticulosAnejos,
            'chartDistribucionAntiguedad' => [
                'labels' => ['0-30 días', '31-60 días', '61-90 días', '>90 días'],
                'data_varios' => [$r0_30, $r31_60, $r61_90, $r90_plus]
            ],
            'chartValorAntiguedadSucursal' => $chartSucursales
        ]);
    }

    public function topMarcas(Request $request)
    {
        $codPrenda = $request->input('cod_prenda');
        $sucursalId = $request->input('sucursal_id');

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->filter(fn($s) => (string)$s->id_valora_mas === (string)$sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');
        $rankingsMarcas = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi_marcas_credito_' . $sucursal->id_valora_mas;

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                // Query for varios and autos in status 12 (Credito)
                $topMarcasQ = DB::connection($connectionName)->select("
                    SELECT mar.marca, SUM(t.total_movs) as total_movs, SUM(t.monto) as monto
                    FROM (
                        SELECT va.cod_marca, COUNT(va.cod_varios) as total_movs, SUM(va.prestamo) as monto
                        FROM varios va
                        WHERE va.cod_estatus_prenda = 12
                          AND va.cod_prenda = :codPrenda1
                        GROUP BY va.cod_marca
                        
                        UNION ALL
                        
                        SELECT au.cod_marca, COUNT(au.cod_auto) as total_movs, SUM(au.prestamo) as monto
                        FROM autos au
                        WHERE au.cod_estatus_prenda = 12
                          AND au.cod_prenda = :codPrenda2
                        GROUP BY au.cod_marca
                    ) as t
                    INNER JOIN marcas mar ON mar.cod_marca = t.cod_marca
                    GROUP BY mar.marca
                    ORDER BY total_movs DESC
                ", [
                    ':codPrenda1' => $codPrenda,
                    ':codPrenda2' => $codPrenda
                ]);

                foreach ($topMarcasQ as $row) {
                    $key = $row->marca;
                    if (!isset($rankingsMarcas[$key])) {
                        $rankingsMarcas[$key] = ['marca' => $key, 'total' => 0, 'monto' => 0];
                    }
                    $rankingsMarcas[$key]['total'] += (int)$row->total_movs;
                    $rankingsMarcas[$key]['monto'] += (float)$row->monto;
                }

            } catch (\Exception $e) {
                Log::error("Error top marcas credito sucursal {$sucursal->nombre}: " . $e->getMessage());
                continue;
            }
        }

        // Ordenar y limitar al top 10
        usort($rankingsMarcas, function($a, $b) { return $b['total'] <=> $a['total']; });
        $rankingsMarcas = array_slice($rankingsMarcas, 0, 10);

        return response()->json($rankingsMarcas);
    }
}
