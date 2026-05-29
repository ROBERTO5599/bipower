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
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
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

                // Gráfico por sucursal
                $chartSucursales['labels'][] = $sucursal->nombre;
                $chartSucursales['valores'][] = $inventarioSucursal;
                $chartSucursales['antiguedad'][] = 0;

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
            'totalArticulosN' => $totalArticulos,
            'antiguedadPromedioDias' => 0,
            'porcentajeMas30' => 0,
            'porcentajeMas60' => 0,
            'porcentajeMas90' => 0,
            'rotacionInventario' => 0,
            'topArticulosAnejos' => $topArticulosAnejos,
            'chartDistribucionAntiguedad' => [
                'labels' => ['0-30 días', '31-60 días', '61-90 días', '>90 días'],
                'data_varios' => [0, 0, 0, 0]
            ],
            'chartValorAntiguedadSucursal' => $chartSucursales
        ]);
    }
}
