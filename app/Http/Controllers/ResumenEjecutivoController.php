<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class ResumenEjecutivoController extends Controller
{
    // Carga la vista base sin datos (renderizado rápido)
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('resumen-ejecutivo.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    // Endpoint AJAX para obtener los datos agregados
    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        // Agregamos tiempo completo al final del día para la consulta
        $fechaFinQuery = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        $sucursalId = $request->input('sucursal_id');

        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');

        // Variables Globales
        $totalIngresos = 0;
        $totalEgresos = 0;

        $inventarioPisoVentaTotal = 0;
        $inventarioDepositariaTotal = 0;

        $invOro = 0;
        $invPlata = 0;
        $invVarios = 0;
        $invAutos = 0;

        $empenosData = ['contratos' => 0, 'prestamo' => 0];

        $branchKPIs = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi';

            $b_ingresos = 0;
            $b_egresos = 0;
            $b_utilidad = 0;
            $b_invTotal = 0;
            $b_ventasTotales = 0;

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                // 1. GASTOS (Egresos)
                $gastosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(gas.solicitado), 0) AS TotalGastos
                    FROM gastos gas
                    INNER JOIN movimientos mov ON gas.cod_movimiento = mov.cod_movimiento
                    WHERE gas.activo = 1
                      AND gas.cod_estatus = 2
                      AND gas.f_solicitado >= :fechaDel
                      AND gas.f_solicitado <= :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_egresos = (float)($gastosResult->TotalGastos ?? 0);

                // 2. VENTAS Y APARTADOS (Ingresos)
                // Obtenemos sumatorias de Ventas directas y Liquidación de Apartados.
                $ventasResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        COALESCE(SUM(dv.venta10), 0) AS total_ventas,
                        COALESCE(SUM(
                            CASE
                                WHEN ve.cod_tipo_prenda = 1 THEN (SELECT prestamo FROM alhajas WHERE cod_alhaja = dv.cod_prenda)
                                WHEN ve.cod_tipo_prenda = 2 THEN (SELECT prestamo FROM autos WHERE cod_auto = dv.cod_prenda)
                                WHEN ve.cod_tipo_prenda = 3 THEN (SELECT prestamo FROM varios WHERE cod_varios = dv.cod_prenda)
                                ELSE 0
                            END
                        ), 0) as prestamo_ventas
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    WHERE ve.f_cancela IS NULL AND CAST(ve.f_venta AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $apartadosResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        COALESCE(SUM(mo.monto10), 0) AS total_apartados,
                        COALESCE(SUM(
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 THEN (SELECT prestamo FROM alhajas WHERE cod_alhaja = da.cod_prenda)
                                WHEN ap.cod_tipo_prenda = 2 THEN (SELECT prestamo FROM autos WHERE cod_auto = da.cod_prenda)
                                WHEN ap.cod_tipo_prenda = 3 THEN (SELECT prestamo FROM varios WHERE cod_varios = da.cod_prenda)
                                ELSE 0
                            END
                        ), 0) as prestamo_apartados
                    FROM apartado_pagos apg
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
                    INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                    WHERE apg.f_cancela IS NULL AND mo.cod_tipo_movimiento = 7 AND CAST(apg.f_pago AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $utilidadVentas = ($ventasResult->total_ventas ?? 0) - ($ventasResult->prestamo_ventas ?? 0);
                $utilidadApartados = ($apartadosResult->total_apartados ?? 0) - ($apartadosResult->prestamo_apartados ?? 0);

                $b_ingresos = $utilidadVentas + $utilidadApartados;
                $b_utilidad = $b_ingresos - $b_egresos;
                $b_ventasTotales = ($ventasResult->total_ventas ?? 0) + ($apartadosResult->total_apartados ?? 0);

                // 3. EMPEÑOS
                $empenosResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        COUNT(DISTINCT con.contrato) as contratos,
                        COALESCE(SUM(mo.monto10), 0) as prestamo
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE mo.cod_tipo_movimiento = 1 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL
                    AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                // 4. INVENTARIO (Simplificado y agregado)
                $inventarioResult = DB::connection($connectionName)->select("
                    SELECT
                        'Alhaja' AS Tipo,
                        CASE WHEN kilataje BETWEEN 500 AND 999 THEN 'Plata' WHEN kilataje BETWEEN 8 AND 26 THEN 'Oro' ELSE 'Varios' END AS CategoriaMetal,
                        cod_estatus_prenda,
                        COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM alhajas WHERE cod_estatus_prenda IN (1,9) GROUP BY CategoriaMetal, cod_estatus_prenda
                    UNION ALL
                    SELECT 'Varios' AS Tipo, 'Varios' AS CategoriaMetal, cod_estatus_prenda, COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM varios WHERE cod_estatus_prenda IN (1,9) GROUP BY cod_estatus_prenda
                    UNION ALL
                    SELECT 'Auto' AS Tipo, 'Auto' AS CategoriaMetal, cod_estatus_prenda, COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM autos WHERE cod_estatus_prenda IN (1,9) GROUP BY cod_estatus_prenda
                ");

                foreach ($inventarioResult as $invRow) {
                    $monto = (float)$invRow->total_prestamo;
                    $b_invTotal += $monto;

                    if ($invRow->cod_estatus_prenda == 9) $inventarioPisoVentaTotal += $monto;
                    if ($invRow->cod_estatus_prenda == 1) $inventarioDepositariaTotal += $monto;

                    if ($invRow->CategoriaMetal === 'Oro') $invOro += $monto;
                    if ($invRow->CategoriaMetal === 'Plata') $invPlata += $monto;
                    if ($invRow->Tipo === 'Varios') $invVarios += $monto;
                    if ($invRow->Tipo === 'Auto') $invAutos += $monto;
                }

                // Sumar a Globales
                $totalIngresos += $b_ingresos;
                $totalEgresos += $b_egresos;
                $totalGastosGlobal += $b_egresos;

                $empenosData['contratos'] += (int)($empenosResult->contratos ?? 0);
                $empenosData['prestamo'] += (float)($empenosResult->prestamo ?? 0);

                // Armar KPI de Sucursal
                $margenBruto = $b_ventasTotales > 0 ? ($b_ingresos / $b_ventasTotales) * 100 : 0;

                $branchKPIs[$sucursal->nombre] = [
                    'ingresos' => $b_ingresos,
                    'gastos' => $b_egresos,
                    'utilidad_neta' => $b_utilidad,
                    'margen_bruto_pct' => $margenBruto,
                    'inventario_total' => $b_invTotal,
                ];

            } catch (\Exception $e) {
                Log::error("Error procesando sucursal {$sucursal->nombre} ({$dbName}): " . $e->getMessage());
                continue;
            }
        }

        $utilidadNeta = $totalIngresos - $totalEgresos;

        $chartFinanciero = [
            'labels' => ['Ingresos', 'Gastos', 'Utilidad'],
            'data' => [$totalIngresos, $totalEgresos, $utilidadNeta]
        ];

        $chartInventario = [
            'labels' => ['Oro', 'Plata', 'Varios', 'Autos'],
            'data' => [$invOro, $invPlata, $invVarios, $invAutos]
        ];

        $chartSucursales = $this->prepareBranchChartData($branchKPIs);

        return response()->json([
            'totalIngresos' => $totalIngresos,
            'totalEgresos' => $totalEgresos,
            'utilidadNeta' => $utilidadNeta,
            'inventarioPisoVentaTotal' => $inventarioPisoVentaTotal,
            'empenosData' => $empenosData,
            'chartFinanciero' => $chartFinanciero,
            'chartInventario' => $chartInventario,
            'branchKPIs' => $branchKPIs,
            'chartSucursales' => $chartSucursales
        ]);
    }

    private function prepareBranchChartData($branchKPIs)
    {
        $labels = array_keys($branchKPIs);
        $ingresos = [];
        $utilidades = [];

        foreach ($branchKPIs as $kpi) {
            $ingresos[] = $kpi['ingresos'];
            $utilidades[] = $kpi['utilidad_neta'];
        }

        return [
            'labels' => $labels,
            'ingresos' => $ingresos,
            'utilidades' => $utilidades
        ];
    }
}
