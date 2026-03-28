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
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin') . ' 23:59:59';
        $sucursalId = $request->input('sucursal_id');

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        if ($sucursalId) {
            $sucursales = $sucursales->where('id_valora_mas', $sucursalId);
        }

        $baseConfig = Config::get('database.connections.mysql');

        $ventasTotales = 0;
        $totalTickets = 0;
        $utilidad = 0;
        $descuentoTotal = 0;
        $conDescuento = 0;
        
        $totalEfectivo = 0;
        $totalTarjeta = 0;

        $ventasTipo = [
            1 => 0,
            2 => 0,
            3 => 0
        ];

        // Para gráficos por sucursal
        $sucLabels = [];
        $sucVentas = [];
        $sucUtilidades = [];

        $topArticulos = [];

        foreach ($sucursales as $sucursal) {

            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'ventas_dynamic';

            try {

                $config = $baseConfig;
                $config['database'] = $dbName;

                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);


                $rows = DB::connection($connectionName)->select("
                    SELECT 
                        ve.cod_tipo_prenda,
                        pre.prenda,
                        dv.venta10 as total,
                        dv.descuento,
                        CASE 
                            WHEN ve.cod_tipo_prenda = 1 THEN al.prestamo
                            WHEN ve.cod_tipo_prenda = 2 THEN au.prestamo
                            WHEN ve.cod_tipo_prenda = 3 THEN va.prestamo
                            ELSE 0
                        END as prestamo
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    LEFT JOIN alhajas al ON ve.cod_tipo_prenda = 1 AND al.cod_alhaja = dv.cod_prenda
                    LEFT JOIN autos au ON ve.cod_tipo_prenda = 2 AND au.cod_auto = dv.cod_prenda
                    LEFT JOIN varios va ON ve.cod_tipo_prenda = 3 AND va.cod_varios = dv.cod_prenda
                    INNER JOIN prendas pre ON pre.cod_prenda = 
                        CASE 
                            WHEN ve.cod_tipo_prenda = 1 THEN al.cod_prenda
                            WHEN ve.cod_tipo_prenda = 2 THEN au.cod_prenda
                            WHEN ve.cod_tipo_prenda = 3 THEN va.cod_prenda
                        END
                    WHERE ve.f_cancela IS NULL
                    AND ve.f_venta BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);

                $sucVentasTemp = 0;
                $sucUtilidadTemp = 0;

                foreach ($rows as $r) {

                    $ventasTotales += $r->total;
                    $totalTickets++;
                    $utilidad += ($r->total - $r->prestamo);
                    $descuentoTotal += $r->descuento;
                    $sucVentasTemp += $r->total;
                    $sucUtilidadTemp += ($r->total - $r->prestamo);

                    if ($r->descuento > 0) {
                        $conDescuento++;
                    }

                    // ventas por tipo
                    if (isset($ventasTipo[$r->cod_tipo_prenda])) {
                        $ventasTipo[$r->cod_tipo_prenda] += $r->total;
                    }

                    // top artículos
                    if (!isset($topArticulos[$r->prenda])) {
                        $topArticulos[$r->prenda] = [
                            'nombre' => $r->prenda,
                            'ventas' => 0,
                            'importe' => 0
                        ];
                    }

                    $topArticulos[$r->prenda]['ventas']++;
                    $topArticulos[$r->prenda]['importe'] += $r->total;
                }

                $pagosQuery = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(mo.monto_efectivo), 0) as efectivo,
                        COALESCE(SUM(mo.monto_tarjeta), 0) as tarjeta
                    FROM ventas ve
                    INNER JOIN movimientos mo ON mo.cod_movimiento = ve.cod_movimiento
                    WHERE ve.f_cancela IS NULL AND ve.f_venta BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);

                $totalEfectivo += (float) $pagosQuery->efectivo;
                $totalTarjeta += (float) $pagosQuery->tarjeta;

                $sucLabels[] = $sucursal->nombre;
                $sucVentas[] = $sucVentasTemp;
                $sucUtilidades[] = $sucUtilidadTemp;

            } catch (\Exception $e) {
                Log::error("Error en ventas sucursal {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // =========================
        // KPIs
        // =========================
        $ticketPromedio = $totalTickets > 0 ? $ventasTotales / $totalTickets : 0;
        $margen = $ventasTotales > 0 ? ($utilidad / $ventasTotales) * 100 : 0;
        $porcentajeDescuento = $ventasTotales > 0 ? ($descuentoTotal / $ventasTotales) * 100 : 0;
        $ticketsConDescuento = $totalTickets > 0 ? ($conDescuento / $totalTickets) * 100 : 0;
        
        $ventasPagosTotales = $totalEfectivo + $totalTarjeta;
        $porcentajeTarjeta = $ventasPagosTotales > 0 ? ($totalTarjeta / $ventasPagosTotales) * 100 : 0;

        // =========================
        // CHART
        // =========================
        $labels = ['Oro', 'Autos', 'Varios'];
        $data = [
            $ventasTipo[1],
            $ventasTipo[2],
            $ventasTipo[3]
        ];

        // =========================
        // TOP 10
        // =========================
        usort($topArticulos, fn($a, $b) => $b['ventas'] <=> $a['ventas']);
        $topArticulos = array_slice($topArticulos, 0, 10);

        return response()->json([
            'ventasTotales' => $ventasTotales,
            'totalTickets' => $totalTickets,
            'ticketPromedio' => $ticketPromedio,
            'utilidadBruta' => $utilidad,
            'margenVentaPorcentaje' => $margen,

            'montoDescuentoTotal' => $descuentoTotal,
            'porcentajeDescuentoTotal' => $porcentajeDescuento,
            'ticketsConDescuentoPorcentaje' => $ticketsConDescuento,

            'pagosTarjeta' => $totalTarjeta,
            'pagosTarjetaPorcentaje' => $porcentajeTarjeta,

            'chartVentasFamilia' => [
                'labels' => $labels,
                'data' => $data
            ],

            'chartMetodosPago' => [
                'labels' => ['Efectivo', 'Tarjeta'],
                'data' => [$totalEfectivo, $totalTarjeta]
            ],

            'chartVentasSucursal' => [
                'labels' => $sucLabels,
                'ventas' => $sucVentas,
                'utilidad' => $sucUtilidades
            ],

            'topArticulos' => array_values($topArticulos)
        ]);
    }
}