<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class GastosFinanzasController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('gastos-finanzas.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
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
        
        // Estado de Resultados Resumen
        $ingresosTotales = 0;
        $costoVentas = 0;
        $gastosOperativos = 0;
        $gastosFinancieros = 0;
        $impuestos = 0;
        
        // Composición Gastos
        $gastoNomina = 0;
        $gastoRenta = 0;
        $gastoServicios = 0;
        $gastoPublicidad = 0;
        $gastoMantenimiento = 0;
        $gastoOtros = 0;

        // Balance General
        $totalActivos = 0;
        $totalPasivos = 0;
        $capitalContable = 0;

        // Flujo de Efectivo
        $flujoOperacion = 0;
        $flujoInversion = 0;
        $flujoFinanciamiento = 0;

        $chartEvolucionIngresosUtilidad = [
            'labels' => [], // Sucursales
            'ingresos' => [],
            'utilidadBruta' => [],
            'utilidadNeta' => []
        ];

        $chartComposicionGastos = [
            'labels' => ['Nómina', 'Renta', 'Servicios', 'Publicidad', 'Mantenimiento', 'Otros'],
            'data' => [0, 0, 0, 0, 0, 0]
        ];

        $detalleGastosSucursal = []; 
        $estadoResultados = [];
        $balanceGeneral = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'finanzas_dynamic';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // 1. Ingresos y Costos de Ventas
                $ventasQuery = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(dv.venta10), 0) as ingresos_ventas,
                        COALESCE(SUM(
                            CASE 
                                WHEN ve.cod_tipo_prenda = 1 THEN al.prestamo
                                WHEN ve.cod_tipo_prenda = 2 THEN au.prestamo
                                WHEN ve.cod_tipo_prenda = 3 THEN va.prestamo
                                ELSE 0
                            END
                        ), 0) as costo_ventas
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    LEFT JOIN alhajas al ON ve.cod_tipo_prenda = 1 AND al.cod_alhaja = dv.cod_prenda
                    LEFT JOIN autos au ON ve.cod_tipo_prenda = 2 AND au.cod_auto = dv.cod_prenda
                    LEFT JOIN varios va ON ve.cod_tipo_prenda = 3 AND va.cod_varios = dv.cod_prenda
                    WHERE ve.f_cancela IS NULL AND ve.f_venta BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);

                $sucIngresos = (float) $ventasQuery->ingresos_ventas;
                $sucCostoVentas = (float) $ventasQuery->costo_ventas;
                
                // 2. Ingresos Financieros (Ej. Refrendos=2, Desempeño=4 estimado)
                $interesesQuery = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(
                            CASE 
                                WHEN mo.cod_tipo_movimiento = 2 THEN mo.monto10 
                                WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - COALESCE(c.prestamo, 0))
                                ELSE 0
                            END
                        ), 0) as interes_generado
                    FROM movimientos mo
                    LEFT JOIN contratos c ON mo.cod_contrato = c.cod_contrato
                    WHERE mo.f_cancela IS NULL 
                      AND mo.cod_tipo_movimiento IN (2, 4)
                      AND mo.f_alta BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);

                $sucIngresos += (float) $interesesQuery->interes_generado;
                
                $ingresosTotales += $sucIngresos;
                $costoVentas += $sucCostoVentas;

                // 3. Gastos Operativos
                $gastosQuery = DB::connection($connectionName)->select("
                    SELECT 
                        g.cod_concepto,
                        c.concepto,
                        SUM(COALESCE(g.autorizado, g.solicitado, 0)) as total_gasto
                    FROM gastos g
                    LEFT JOIN conceptos c ON c.cod_concepto = g.cod_concepto
                    WHERE g.f_cancelacion IS NULL 
                      AND g.activo = 1 
                      AND COALESCE(g.f_aplicacion, g.f_autorizado, g.f_solicitado) BETWEEN ? AND ?
                    GROUP BY g.cod_concepto, c.concepto
                ", [$fechaInicio, $fechaFin]);

                $sucGastoNomina = 0;
                $sucGastoRenta = 0;
                $sucGastoServicios = 0;
                $sucGastoPublicidad = 0;
                $sucGastoMantenimiento = 0;
                $sucGastoOtros = 0;
                $sucGastoTotal = 0;

                foreach ($gastosQuery as $g) {
                    $monto = (float) $g->total_gasto;
                    $sucGastoTotal += $monto;
                    
                    $conceptoStr = strtolower($g->concepto ?? '');

                    if (str_contains($conceptoStr, 'nómina') || str_contains($conceptoStr, 'nomina') || str_contains($conceptoStr, 'sueldo') || str_contains($conceptoStr, 'imss')) {
                        $gastoNomina += $monto; $sucGastoNomina += $monto;
                    } elseif (str_contains($conceptoStr, 'renta') || str_contains($conceptoStr, 'arrendamiento')) {
                        $gastoRenta += $monto; $sucGastoRenta += $monto;
                    } elseif (str_contains($conceptoStr, 'servicio') || str_contains($conceptoStr, 'luz') || str_contains($conceptoStr, 'agua') || str_contains($conceptoStr, 'internet')) {
                        $gastoServicios += $monto; $sucGastoServicios += $monto;
                    } elseif (str_contains($conceptoStr, 'publicidad') || str_contains($conceptoStr, 'marketing')) {
                        $gastoPublicidad += $monto; $sucGastoPublicidad += $monto;
                    } elseif (str_contains($conceptoStr, 'mantenimiento') || str_contains($conceptoStr, 'limpieza')) {
                        $gastoMantenimiento += $monto; $sucGastoMantenimiento += $monto;
                    } else {
                        $gastoOtros += $monto; $sucGastoOtros += $monto;
                    }
                }

                $gastosOperativos += $sucGastoTotal;
                
                // Balance Operativo Básico (Cartera y Piso)
                $activosQuery = DB::connection($connectionName)->selectOne("
                    SELECT 
                       (SELECT COALESCE(SUM(prestamo), 0) FROM contratos WHERE cod_estatus = 1 AND activo = 1) as cartera_empeno,
                       (
                         (SELECT COALESCE(SUM(prestamo), 0) FROM alhajas WHERE cod_estatus_prenda = 1 AND p_venta = 1) +
                         (SELECT COALESCE(SUM(prestamo), 0) FROM varios WHERE cod_estatus_prenda = 1 AND p_venta = 1)
                       ) as inventario_piso
                ");
                $totalActivos += (float)$activosQuery->cartera_empeno + (float)$activosQuery->inventario_piso;

                // Charts & Arrays
                $chartEvolucionIngresosUtilidad['labels'][] = $sucursal->nombre;
                
                $sucUtilidadBruta = $sucIngresos - $sucCostoVentas;
                $sucUtilidadNeta = $sucUtilidadBruta - $sucGastoTotal;

                $chartEvolucionIngresosUtilidad['ingresos'][] = $sucIngresos;
                $chartEvolucionIngresosUtilidad['utilidadBruta'][] = $sucUtilidadBruta;
                $chartEvolucionIngresosUtilidad['utilidadNeta'][] = $sucUtilidadNeta;

                $detalleGastosSucursal[] = [
                    'sucursal' => $sucursal->nombre,
                    'nomina' => $sucGastoNomina,
                    'renta' => $sucGastoRenta,
                    'servicios' => $sucGastoServicios,
                    'publicidad' => $sucGastoPublicidad,
                    'mantenimiento' => $sucGastoMantenimiento,
                    'otros' => $sucGastoOtros,
                    'total' => $sucGastoTotal
                ];

            } catch (\Exception $e) {
                Log::error("Error Finanzas en {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // Fórmulas Consolidadas
        $utilidadBruta = $ingresosTotales - $costoVentas;
        $utilidadOperativa = $utilidadBruta - $gastosOperativos;
        $utilidadNeta = $utilidadOperativa - $gastosFinancieros - $impuestos;

        $margenBrutoPct = $ingresosTotales > 0 ? ($utilidadBruta / $ingresosTotales) * 100 : 0;
        $margenOperativoPct = $ingresosTotales > 0 ? ($utilidadOperativa / $ingresosTotales) * 100 : 0;
        $margenNetoPct = $ingresosTotales > 0 ? ($utilidadNeta / $ingresosTotales) * 100 : 0;

        $nominaSobreUtilidadBrutaPct = $utilidadBruta > 0 ? ($gastoNomina / $utilidadBruta) * 100 : 0;
        $rentaSobreUtilidadBrutaPct = $utilidadBruta > 0 ? ($gastoRenta / $utilidadBruta) * 100 : 0;
        $gastosTotalesSobreUBPct = $utilidadBruta > 0 ? ($gastosOperativos / $utilidadBruta) * 100 : 0;

        $flujoOperacion = $utilidadOperativa;
        $flujoNeto = $flujoOperacion + $flujoInversion + $flujoFinanciamiento;

        $chartComposicionGastos['data'] = [$gastoNomina, $gastoRenta, $gastoServicios, $gastoPublicidad, $gastoMantenimiento, $gastoOtros];

        return response()->json([
            // Estado de Resultados
            'ingresosTotales' => $ingresosTotales,
            'costoVentas' => $costoVentas,
            'utilidadBruta' => $utilidadBruta,
            'gastosOperativos' => $gastosOperativos,
            'utilidadOperativa' => $utilidadOperativa,
            'gastosFinancieros' => $gastosFinancieros,
            'impuestos' => $impuestos,
            'utilidadNeta' => $utilidadNeta,
            
            // Ratios
            'margenBrutoPct' => $margenBrutoPct,
            'margenOperativoPct' => $margenOperativoPct,
            'margenNetoPct' => $margenNetoPct,
            'nominaSobreUtilidadBrutaPct' => $nominaSobreUtilidadBrutaPct,
            'rentaSobreUtilidadBrutaPct' => $rentaSobreUtilidadBrutaPct,
            'gastosTotalesSobreUBPct' => $gastosTotalesSobreUBPct,
            
            // Composición
            'gastoNomina' => $gastoNomina,
            'gastoRenta' => $gastoRenta,
            'gastoServicios' => $gastoServicios,
            'gastoPublicidad' => $gastoPublicidad,
            'gastoMantenimiento' => $gastoMantenimiento,
            'gastoOtros' => $gastoOtros,
            
            // Balance General
            'totalActivos' => $totalActivos,
            'totalPasivos' => $totalPasivos,
            'capitalContable' => $capitalContable,
            
            // Flujo
            'flujoOperacion' => $flujoOperacion,
            'flujoInversion' => $flujoInversion,
            'flujoFinanciamiento' => $flujoFinanciamiento,
            'flujoNeto' => $flujoNeto,
            
            // Gráficos y Tablas
            'chartEvolucionIngresosUtilidad' => $chartEvolucionIngresosUtilidad,
            'chartComposicionGastos' => $chartComposicionGastos,
            'detalleGastosSucursal' => $detalleGastosSucursal,
            'estadoResultados' => $estadoResultados,
            'balanceGeneral' => $balanceGeneral
        ]);
    }
}
