<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class VentasController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('ventas.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        $fechaInicioRaw = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        $fechaFinRaw = $request->input('fecha_fin', now()->toDateString());

        // Estandarizar al formato compatible para MySQL sin importar cómo llegue del front
        $fechaInicio = \Carbon\Carbon::parse($fechaInicioRaw)->startOfDay()->toDateTimeString();
        $fechaFinQuery = \Carbon\Carbon::parse($fechaFinRaw)->endOfDay()->toDateTimeString();
        $sucursalId = $request->input('sucursal_id');

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        if ($sucursalId) {
            $sucursales = $sucursales->where('id_valora_mas', $sucursalId);
        }

        $baseConfig = Config::get('database.connections.mysql');

        // Métricas de Ventas
        $ventasTotales = 0;
        $totalTickets = 0;
        $utilidadBruta = 0;
        $totalRegistrosProcesados = 0;
        
        $descuentoTotal = 0;
        $precioListaSuma = 0; // Para calcular correctamente el total descontado vs real
        $ticketsConDescuento = 0;
        
        $totalEfectivo = 0;
        $totalTarjeta = 0;
        $montoPrestamo = 0;
        $contratosEfectivo = 0;
        $contratosTarjeta = 0;
        
        // Comision estimada TPV
        $comisionTPV = 0.035; // 3.5%

        // Ventas por familia según el requerimiento (Oro, remate, varios, mayoreo/redes - asumiendo códigos estándar temporalmente)
        $ventasFamilia = [
            'Oro' => ['ventas' => 0, 'utilidad' => 0, 'descuento' => 0],
            'Varios' => ['ventas' => 0, 'utilidad' => 0, 'descuento' => 0],
            'Autos' => ['ventas' => 0, 'utilidad' => 0, 'descuento' => 0]
        ];

        // Desgloses detallados para KPIs
        $desgloseVentas = [
            'venta' => [
                'monto' => 0, 'utilidad' => 0, 'descuento' => 0, 'cantidad' => 0, 'efectivo' => 0, 'tarjeta' => 0,
                'detalles' => [
                    'Oro' => ['monto' => 0, 'cantidad' => 0],
                    'Plata' => ['monto' => 0, 'cantidad' => 0],
                    'Varios' => ['monto' => 0, 'cantidad' => 0],
                    'Autos' => ['monto' => 0, 'cantidad' => 0]
                ]
            ],
            'apartado' => [
                'monto' => 0, 'utilidad' => 0, 'descuento' => 0, 'cantidad' => 0, 'efectivo' => 0, 'tarjeta' => 0,
                'detalles' => [
                    'Oro' => ['monto' => 0, 'cantidad' => 0],
                    'Plata' => ['monto' => 0, 'cantidad' => 0],
                    'Varios' => ['monto' => 0, 'cantidad' => 0],
                    'Autos' => ['monto' => 0, 'cantidad' => 0]
                ]
            ],
            'credito' => [
                'monto' => 0, 'utilidad' => 0, 'descuento' => 0, 'cantidad' => 0, 'efectivo' => 0, 'tarjeta' => 0,
                'detalles' => [
                    'Oro' => ['monto' => 0, 'cantidad' => 0],
                    'Plata' => ['monto' => 0, 'cantidad' => 0],
                    'Varios' => ['monto' => 0, 'cantidad' => 0],
                    'Autos' => ['monto' => 0, 'cantidad' => 0]
                ]
            ]
        ];

        $desgloseFamilias = [
            'Oro' => ['monto' => 0, 'utilidad' => 0, 'descuento' => 0, 'cantidad' => 0, 'efectivo' => 0, 'tarjeta' => 0],
            'Varios' => ['monto' => 0, 'utilidad' => 0, 'descuento' => 0, 'cantidad' => 0, 'efectivo' => 0, 'tarjeta' => 0],
            'Autos' => ['monto' => 0, 'utilidad' => 0, 'descuento' => 0, 'cantidad' => 0, 'efectivo' => 0, 'tarjeta' => 0]
        ];

        // Para gráficos por sucursal
        $sucLabels = [];
        $sucVentasOro = [];
        $sucVentasVarios = [];
        $sucVentasAutos = [];

        // Tabla Top Artículos
        $articulosData = [];

        foreach ($sucursales as $sucursal) {

            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'ventas_dynamic_' . $sucursal->id_valora_mas;

            $sucVentasTempOro = 0;
            $sucVentasTempVarios = 0;
            $sucVentasTempAutos = 0;

            try {

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.{$connectionName}", $config);
                DB::purge($connectionName);

                // 1. Obtener Ventas, Utilidad, Descuentos, Efectivo y Tarjeta usando UNION ALL optimizado
                $rows = DB::connection($connectionName)->select("
                    SELECT ve.cod_tipo_prenda, pre.prenda, al.prestamo, dv.descuento, dv.venta10, 
                           mo.monto_efectivo, mo.monto_tarjeta, 'venta' as origen, al.kilataje, al.cod_prenda
                    FROM detalle_venta dv 
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    INNER JOIN alhajas al ON al.cod_alhaja = dv.cod_prenda
                    INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda
                    INNER JOIN movimientos mo ON mo.cod_movimiento = ve.cod_movimiento 
                    WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 1 AND ve.f_venta BETWEEN ? AND ?
                    UNION ALL
                    SELECT ve.cod_tipo_prenda, pre.prenda, va.prestamo, dv.descuento, dv.venta10, 
                           mo.monto_efectivo, mo.monto_tarjeta, 'venta' as origen, 0 as kilataje, va.cod_prenda
                    FROM detalle_venta dv 
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    INNER JOIN varios va ON va.cod_varios = dv.cod_prenda
                    INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda
                    INNER JOIN movimientos mo ON mo.cod_movimiento = ve.cod_movimiento 
                    WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 3 AND ve.f_venta BETWEEN ? AND ?
                    UNION ALL
                    SELECT ve.cod_tipo_prenda, pre.prenda, au.prestamo, dv.descuento, dv.venta10, 
                           mo.monto_efectivo, mo.monto_tarjeta, 'venta' as origen, 0 as kilataje, au.cod_prenda
                    FROM detalle_venta dv 
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    INNER JOIN autos au ON au.cod_auto = dv.cod_prenda
                    INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda
                    INNER JOIN movimientos mo ON mo.cod_movimiento = ve.cod_movimiento 
                    WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 2 AND ve.f_venta BETWEEN ? AND ?
                    UNION ALL   
                    SELECT ap.cod_tipo_prenda, pre.prenda, al.prestamo, da.descuento, al.precio as venta10, 
                           mo.monto_efectivo, mo.monto_tarjeta, 'apartado' as origen, al.kilataje, al.cod_prenda
                    FROM apartado_pagos apg 
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado 
                    INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                    INNER JOIN alhajas al ON al.cod_alhaja = da.cod_prenda
                    INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento 
                    WHERE mo.cod_tipo_movimiento=12 AND apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 1 AND apg.f_pago BETWEEN ? AND ?
                    UNION ALL
                    SELECT ap.cod_tipo_prenda, pre.prenda, au.prestamo, da.descuento, au.precio as venta10, 
                           mo.monto_efectivo, mo.monto_tarjeta, 'apartado' as origen, 0 as kilataje, au.cod_prenda
                    FROM apartado_pagos apg 
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado 
                    INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                    INNER JOIN autos au ON au.cod_auto = da.cod_prenda
                    INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento 
                    WHERE mo.cod_tipo_movimiento=12 AND apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 2 AND apg.f_pago BETWEEN ? AND ?
                    UNION ALL
                    SELECT ap.cod_tipo_prenda, pre.prenda, va.prestamo, da.descuento, va.precio as venta10, 
                           mo.monto_efectivo, mo.monto_tarjeta, 'apartado' as origen, 0 as kilataje, va.cod_prenda
                    FROM apartado_pagos apg 
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
                    INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                    INNER JOIN varios va ON va.cod_varios = da.cod_prenda
                    INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento 
                    WHERE mo.cod_tipo_movimiento=12 AND apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 3 AND apg.f_pago BETWEEN ? AND ?
                    UNION ALL
                    SELECT mo.cod_tipo_prenda, pre.prenda, art.prestamo, 0 as descuento, op.monto_total as venta10, 
                           mo.monto_efectivo, mo.monto_tarjeta, 'credito' as origen, 0 as kilataje, art.cod_prenda
                    FROM movimientos mo
                    INNER JOIN creditos op ON op.cod_credito = mo.cod_contrato
                    INNER JOIN varios art ON art.cod_varios = op.cod_varios
                    INNER JOIN prendas pre ON pre.cod_prenda = art.cod_prenda
                    WHERE mo.cod_estatus IN (1,2) AND mo.cod_tipo_movimiento = 21 AND mo.f_alta BETWEEN ? AND ?
                ", [
                    $fechaInicio, $fechaFinQuery, $fechaInicio, $fechaFinQuery,
                    $fechaInicio, $fechaFinQuery, $fechaInicio, $fechaFinQuery,
                    $fechaInicio, $fechaFinQuery, $fechaInicio, $fechaFinQuery,
                    $fechaInicio, $fechaFinQuery
                ]);

                $totalRegistrosProcesados += count($rows);

                foreach ($rows as $r) {
                    
                    $vtaReal = (float) $r->venta10;
                    $descuento = (float) $r->descuento;
                    $prestamo = (float) $r->prestamo;
                    $utilidadItem = $vtaReal - $prestamo;
                    $efectivoItem = (float) $r->monto_efectivo;
                    $tarjetaItem = (float) $r->monto_tarjeta;
                    $origen = $r->origen;
                    
                    $ventasTotales += $vtaReal;
                    $utilidadBruta += $utilidadItem;
                    
                    $descuentoTotal += $descuento;
                    if ($descuento > 0) {
                        $ticketsConDescuento++;
                    }
                    $precioListaSuma += ($vtaReal + $descuento);

                    $totalEfectivo += $efectivoItem;
                    $totalTarjeta += $tarjetaItem;
                    $montoPrestamo += $prestamo;

                    if ($efectivoItem > 0) {
                        $contratosEfectivo++;
                    }
                    if ($tarjetaItem > 0) {
                        $contratosTarjeta++;
                    }

                    // Clasificación de familias
                    $familiaStr = 'Varios';
                    if ($r->cod_tipo_prenda == 1) { $familiaStr = 'Oro'; $sucVentasTempOro += $vtaReal; }
                    elseif ($r->cod_tipo_prenda == 2) { $familiaStr = 'Autos'; $sucVentasTempAutos += $vtaReal; }
                    else { $sucVentasTempVarios += $vtaReal; }

                    $ventasFamilia[$familiaStr]['ventas'] += $vtaReal;
                    $ventasFamilia[$familiaStr]['utilidad'] += $utilidadItem;
                    $ventasFamilia[$familiaStr]['descuento'] += $descuento;

                    // Clasificación de familia específica para el desglose interno
                    $subFamiliaStr = 'Varios';
                    if ($r->cod_tipo_prenda == 1) {
                        $kilataje = (int) ($r->kilataje ?? 0);
                        if ($kilataje >= 500 && $kilataje <= 999) {
                            $subFamiliaStr = 'Plata';
                        } else {
                            $subFamiliaStr = 'Oro';
                        }
                    } elseif ($r->cod_tipo_prenda == 2) {
                        $subFamiliaStr = 'Autos';
                    }

                    // Acumular desglose por tipo
                    if (isset($desgloseVentas[$origen])) {
                        $desgloseVentas[$origen]['monto'] += $vtaReal;
                        $desgloseVentas[$origen]['utilidad'] += $utilidadItem;
                        $desgloseVentas[$origen]['descuento'] += $descuento;
                        $desgloseVentas[$origen]['cantidad']++;
                        $desgloseVentas[$origen]['efectivo'] += $efectivoItem;
                        $desgloseVentas[$origen]['tarjeta'] += $tarjetaItem;

                        // Acumular detalle por familia
                        if (isset($desgloseVentas[$origen]['detalles'][$subFamiliaStr])) {
                            $desgloseVentas[$origen]['detalles'][$subFamiliaStr]['monto'] += $vtaReal;
                            $desgloseVentas[$origen]['detalles'][$subFamiliaStr]['cantidad']++;
                        }
                    }

                    // Acumular desglose por familia
                    if (isset($desgloseFamilias[$familiaStr])) {
                        $desgloseFamilias[$familiaStr]['monto'] += $vtaReal;
                        $desgloseFamilias[$familiaStr]['utilidad'] += $utilidadItem;
                        $desgloseFamilias[$familiaStr]['descuento'] += $descuento;
                        $desgloseFamilias[$familiaStr]['cantidad']++;
                        $desgloseFamilias[$familiaStr]['efectivo'] += $efectivoItem;
                        $desgloseFamilias[$familiaStr]['tarjeta'] += $tarjetaItem;
                    }

                    // Acumular para Top Artículos
                    $nombrePrenda = $r->prenda ? $r->prenda : 'Item no registrado';
                    if (!isset($articulosData[$nombrePrenda])) {
                        $articulosData[$nombrePrenda] = [
                            'nombre' => $nombrePrenda,
                            'cod_prenda' => $r->cod_prenda,
                            'cantidad' => 0,
                            'ventas' => 0,
                            'utilidad' => 0,
                            'descuento' => 0
                        ];
                    }
                    $articulosData[$nombrePrenda]['cantidad']++;
                    $articulosData[$nombrePrenda]['ventas'] += $vtaReal;
                    $articulosData[$nombrePrenda]['utilidad'] += $utilidadItem;
                    $articulosData[$nombrePrenda]['descuento'] += $descuento;
                }

                // Información consolidada para la sucursal actual
                $sucLabels[] = $sucursal->nombre;
                $sucVentasOro[] = $sucVentasTempOro;
                $sucVentasVarios[] = $sucVentasTempVarios;
                $sucVentasAutos[] = $sucVentasTempAutos;

            } catch (\Exception $e) {
                Log::error("Error en modulo ventas sucursal {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // =========================
        // CALCULO DE KPIs GLOBALES
        // =========================
        $ticketPromedio = $totalTickets > 0 ? $ventasTotales / $totalTickets : 0;
        $margenVenta = $ventasTotales > 0 ? ($utilidadBruta / $ventasTotales) * 100 : 0;
        
        $porcentajeDescuentoTotal = $ventasTotales > 0 ? ($descuentoTotal / $ventasTotales) * 100 : 0;
        $ticketsConDescuentoPrc = $totalTickets > 0 ? ($ticketsConDescuento / $totalTickets) * 100 : 0;
        
        $ventasPagosTotales = $totalEfectivo + $totalTarjeta;
        $porcentajeTarjeta = $ventasPagosTotales > 0 ? ($totalTarjeta / $ventasPagosTotales) * 100 : 0;
        $porcentajeEfectivo = $ventasPagosTotales > 0 ? ($totalEfectivo / $ventasPagosTotales) * 100 : 0;
        $montoComisionTPV = $totalTarjeta * $comisionTPV;

        // Limpiar ranking para calcular margenes
        foreach ($articulosData as $k => $item) {
            $articulosData[$k]['margen_prc'] = $item['ventas'] > 0 ? ($item['utilidad'] / $item['ventas']) * 100 : 0;
        }

        // Top 10 por Ventas o Importe
        $topArticulosImporte = array_values($articulosData);
        usort($topArticulosImporte, fn($a, $b) => $b['ventas'] <=> $a['ventas']);
        $topArticulosImporte = array_slice($topArticulosImporte, 0, 10);

        // Top 10 por Margen %
        $topArticulosMargen = array_values($articulosData);
        usort($topArticulosMargen, fn($a, $b) => $b['margen_prc'] <=> $a['margen_prc']);
        $topArticulosMargen = array_slice($topArticulosMargen, 0, 10);

        return response()->json([
            // KPIs Principales
            'ventasTotales' => $ventasTotales,
            'totalRegistrosProcesados' => $totalRegistrosProcesados,
            'totalTickets' => $totalRegistrosProcesados,
            'montoPrestamo' => $montoPrestamo,
            'ticketPromedio' => $ticketPromedio,
            'utilidadBruta' => $utilidadBruta,
            'margenVentaPorcentaje' => $margenVenta,

            // Descuentos
            'montoDescuentoTotal' => $descuentoTotal,
            'ticketsConDescuento' => $ticketsConDescuento,
            'porcentajeDescuentoTotal' => $porcentajeDescuentoTotal,
            'ticketsConDescuentoPorcentaje' => $ticketsConDescuentoPrc,

            // Pagos
            'pagosEfectivo' => $totalEfectivo,
            'pagosEfectivoPorcentaje' => $porcentajeEfectivo,
            'contratosEfectivo' => $contratosEfectivo,
            'pagosTarjeta' => $totalTarjeta,
            'pagosTarjetaPorcentaje' => $porcentajeTarjeta,
            'contratosTarjeta' => $contratosTarjeta,
            'comisionTPVEst' => $montoComisionTPV,

            // Desgloses detallados
            'desgloseVentas' => $desgloseVentas,
            'desgloseFamilias' => $desgloseFamilias,

            // Tablas / Rankings
            'topArticulosImporte' => $topArticulosImporte,
            'topArticulosMargen' => $topArticulosMargen,

            // Chart Familia (Apilada vs Utilidad)
            'chartVentasFamilia' => [
                'labels' => ['Oro', 'Varios', 'Autos'],
                'ventas' => [
                    $ventasFamilia['Oro']['ventas'], 
                    $ventasFamilia['Varios']['ventas'], 
                    $ventasFamilia['Autos']['ventas']
                ],
                'utilidades' => [
                    $ventasFamilia['Oro']['utilidad'], 
                    $ventasFamilia['Varios']['utilidad'], 
                    $ventasFamilia['Autos']['utilidad']
                ]
            ],

            // Chart Sucursales Barra Apilada (Familia)
            'chartVentasSucursal' => [
                'labels' => $sucLabels,
                'ventasOro' => $sucVentasOro,
                'ventasVarios' => $sucVentasVarios,
                'ventasAutos' => $sucVentasAutos
            ],

            'chartMetodosPago' => [
                'labels' => ['Efectivo', 'Tarjeta'],
                'data' => [$totalEfectivo, $totalTarjeta]
            ]
        ]);
    }

    public function topMarcas(Request $request)
    {
        $codPrenda = $request->input('cod_prenda');
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFinQuery = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        $sucursalId = $request->input('sucursal_id');

        $idsQueFuncionan = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19];
        $sucursales = Sucursal::whereNotNull('id_valora_mas')
            ->whereIn('id_valora_mas', $idsQueFuncionan)
            ->get();

        if ($sucursalId && in_array((int)$sucursalId, $idsQueFuncionan)) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');
        $rankingsMarcas = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi_marcas_ventas_' . $sucursal->id_valora_mas;

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                $topMarcasQ = DB::connection($connectionName)->select("
                    SELECT mar.marca, SUM(t.total_movs) as total_movs, SUM(t.monto) as monto
                    FROM (
                        SELECT va.cod_marca, COUNT(DISTINCT ve.cod_venta) as total_movs, SUM(dv.venta10) as monto
                        FROM detalle_venta dv 
                        INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                        INNER JOIN varios va ON va.cod_varios = dv.cod_prenda
                        WHERE ve.f_cancela IS NULL 
                          AND ve.cod_tipo_prenda = 3 
                          AND va.cod_prenda = :codPrenda1
                          AND ve.f_venta BETWEEN :fIni1 AND :fFin1
                        GROUP BY va.cod_marca

                        UNION ALL

                        SELECT au.cod_marca, COUNT(DISTINCT ve.cod_venta) as total_movs, SUM(dv.venta10) as monto
                        FROM detalle_venta dv 
                        INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                        INNER JOIN autos au ON au.cod_auto = dv.cod_prenda
                        WHERE ve.f_cancela IS NULL 
                          AND ve.cod_tipo_prenda = 2 
                          AND au.cod_prenda = :codPrenda2
                          AND ve.f_venta BETWEEN :fIni2 AND :fFin2
                        GROUP BY au.cod_marca

                        UNION ALL

                        SELECT va.cod_marca, COUNT(DISTINCT ap.cod_apartado) as total_movs, SUM(da.precio) as monto
                        FROM apartado_pagos apg 
                        INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado 
                        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                        INNER JOIN varios va ON va.cod_varios = da.cod_prenda
                        INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                        WHERE mo.cod_tipo_movimiento = 12 
                          AND apg.f_cancela IS NULL 
                          AND ap.cod_tipo_prenda = 3 
                          AND va.cod_prenda = :codPrenda3
                          AND apg.f_pago BETWEEN :fIni3 AND :fFin3
                        GROUP BY va.cod_marca

                        UNION ALL

                        SELECT au.cod_marca, COUNT(DISTINCT ap.cod_apartado) as total_movs, SUM(da.precio) as monto
                        FROM apartado_pagos apg 
                        INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado 
                        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado 
                        INNER JOIN autos au ON au.cod_auto = da.cod_prenda
                        INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                        WHERE mo.cod_tipo_movimiento = 12 
                          AND apg.f_cancela IS NULL 
                          AND ap.cod_tipo_prenda = 2 
                          AND au.cod_prenda = :codPrenda4
                          AND apg.f_pago BETWEEN :fIni4 AND :fFin4
                        GROUP BY au.cod_marca

                        UNION ALL

                        SELECT va.cod_marca, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(op.monto_total) as monto
                        FROM movimientos mo
                        INNER JOIN creditos op ON op.cod_credito = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_varios = op.cod_varios
                        WHERE mo.cod_estatus IN (1,2) 
                          AND mo.cod_tipo_movimiento = 21 
                          AND va.cod_prenda = :codPrenda5
                          AND mo.f_alta BETWEEN :fIni5 AND :fFin5
                        GROUP BY va.cod_marca
                    ) as t
                    INNER JOIN marcas mar ON mar.cod_marca = t.cod_marca
                    GROUP BY mar.marca
                    ORDER BY total_movs DESC
                ", [
                    ':codPrenda1' => $codPrenda, ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':codPrenda2' => $codPrenda, ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery,
                    ':codPrenda3' => $codPrenda, ':fIni3' => $fechaInicio, ':fFin3' => $fechaFinQuery,
                    ':codPrenda4' => $codPrenda, ':fIni4' => $fechaInicio, ':fFin4' => $fechaFinQuery,
                    ':codPrenda5' => $codPrenda, ':fIni5' => $fechaInicio, ':fFin5' => $fechaFinQuery
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
                Log::error("Error top marcas ventas sucursal {$sucursal->nombre}: " . $e->getMessage());
                continue;
            }
        }

        // Ordenar y limitar al top 10
        usort($rankingsMarcas, function($a, $b) { return $b['total'] <=> $a['total']; });
        $rankingsMarcas = array_slice($rankingsMarcas, 0, 10);

        return response()->json($rankingsMarcas);
    }
}