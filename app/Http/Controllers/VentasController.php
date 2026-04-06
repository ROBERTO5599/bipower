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
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        // Incluimos explícitamente horas para asegurar las búsquedas entre fecha_venta y f_alta
        $fechaFinQuery = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
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
        
        $descuentoTotal = 0;
        $precioListaSuma = 0; // Para calcular correctamente el total descontado vs real
        $ticketsConDescuento = 0;
        
        $totalEfectivo = 0;
        $totalTarjeta = 0;
        
        // Comision estimada TPV
        $comisionTPV = 0.035; // 3.5%

        // Ventas por familia según el requerimiento (Oro, remate, varios, mayoreo/redes - asumiendo códigos estándar temporalmente)
        $ventasFamilia = [
            'Oro' => ['ventas' => 0, 'utilidad' => 0, 'descuento' => 0],
            'Varios' => ['ventas' => 0, 'utilidad' => 0, 'descuento' => 0],
            'Autos' => ['ventas' => 0, 'utilidad' => 0, 'descuento' => 0]
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

                // 1. Obtener Ventas, Utilidad, Descuentos cruzando con ticket para top items
                // Nota: Volvemos a ve.f_venta como en ResumenEjecutivo para no perder transacciones desconectadas
                $rows = DB::connection($connectionName)->select("
                    SELECT 
                        ve.cod_venta,
                        ve.cod_tipo_prenda,
                        COALESCE(pre.prenda, 'Item no registrado') as prenda,
                        COALESCE(dv.precio, dv.venta10 + dv.descuento) as precio_lista,
                        dv.venta10 as venta_final,
                        dv.descuento,
                        CASE 
                            WHEN ve.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                            WHEN ve.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                            WHEN ve.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                            ELSE 0
                        END as prestamo_base
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    LEFT JOIN alhajas al ON ve.cod_tipo_prenda = 1 AND al.cod_alhaja = dv.cod_prenda
                    LEFT JOIN autos au ON ve.cod_tipo_prenda = 2 AND au.cod_auto = dv.cod_prenda
                    LEFT JOIN varios va ON ve.cod_tipo_prenda = 3 AND va.cod_varios = dv.cod_prenda
                    LEFT JOIN prendas pre ON pre.cod_prenda = 
                        CASE 
                            WHEN ve.cod_tipo_prenda = 1 THEN al.cod_prenda
                            WHEN ve.cod_tipo_prenda = 2 THEN au.cod_prenda
                            WHEN ve.cod_tipo_prenda = 3 THEN va.cod_prenda
                        END
                    WHERE ve.f_cancela IS NULL
                    AND ve.f_venta BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFinQuery]);

                $ticketsProcesados = []; // Para no contar tickets dobles
                $sucursalTicketsConDescuento = []; 

                foreach ($rows as $r) {
                    
                    $vtaReal = (float) $r->venta_final;
                    $pLista = (float) $r->precio_lista;
                    $descuento = (float) $r->descuento;
                    $prestamo = (float) $r->prestamo_base;
                    $utilidadItem = $vtaReal - $prestamo;
                    $margenPrc = $vtaReal > 0 ? ($utilidadItem / $vtaReal) * 100 : 0;

                    $ventasTotales += $vtaReal;
                    $utilidadBruta += $utilidadItem;
                    
                    $descuentoTotal += $descuento;
                    $precioListaSuma += $pLista;

                    if (!isset($ticketsProcesados[$r->cod_venta])) {
                        $ticketsProcesados[$r->cod_venta] = true;
                        $totalTickets++;
                    }

                    if ($descuento > 0 && !isset($sucursalTicketsConDescuento[$r->cod_venta])) {
                        $sucursalTicketsConDescuento[$r->cod_venta] = true;
                        $ticketsConDescuento++;
                    }

                    // Clasificación de familias
                    $familiaStr = 'Varios';
                    if ($r->cod_tipo_prenda == 1) { $familiaStr = 'Oro'; $sucVentasTempOro += $vtaReal; }
                    elseif ($r->cod_tipo_prenda == 2) { $familiaStr = 'Autos'; $sucVentasTempAutos += $vtaReal; }
                    else { $sucVentasTempVarios += $vtaReal; }

                    $ventasFamilia[$familiaStr]['ventas'] += $vtaReal;
                    $ventasFamilia[$familiaStr]['utilidad'] += $utilidadItem;
                    $ventasFamilia[$familiaStr]['descuento'] += $descuento;

                    // Acumular para Top Artículos
                    $nombrePrenda = $r->prenda;
                    if (!isset($articulosData[$nombrePrenda])) {
                        $articulosData[$nombrePrenda] = [
                            'nombre' => $nombrePrenda,
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

                // 2. Obtener Métodos de Pago a través de movimientos
                // Aquí solo consultaremos lo pagado en el rango de fechas indicado vinculado a ventas.
                $pagosQuery = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(mo.monto_efectivo), 0) as efectivo,
                        COALESCE(SUM(mo.monto_tarjeta), 0) as tarjeta
                    FROM ventas ve
                    INNER JOIN movimientos mo ON mo.cod_movimiento = ve.cod_movimiento
                    WHERE ve.f_cancela IS NULL AND mo.f_cancela IS NULL 
                      AND ve.f_venta BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFinQuery]);

                $totalEfectivo += (float) $pagosQuery->efectivo;
                $totalTarjeta += (float) $pagosQuery->tarjeta;

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
            'totalTickets' => $totalTickets,
            'ticketPromedio' => $ticketPromedio,
            'utilidadBruta' => $utilidadBruta,
            'margenVentaPorcentaje' => $margenVenta,

            // Descuentos
            'montoDescuentoTotal' => $descuentoTotal,
            'porcentajeDescuentoTotal' => $porcentajeDescuentoTotal,
            'ticketsConDescuentoPorcentaje' => $ticketsConDescuentoPrc,

            // Pagos
            'pagosEfectivo' => $totalEfectivo,
            'pagosTarjeta' => $totalTarjeta,
            'pagosTarjetaPorcentaje' => $porcentajeTarjeta,
            'comisionTPVEst' => $montoComisionTPV,

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
}