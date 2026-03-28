<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ColaboradoresController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('colaboradores.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
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
        
        $nominaTotal = 0;
        $numEmpleados = 0;
        $ventaTotalMonto = 0;
        $ventaTotalCosto = 0;
        $ventaTotalTickets = 0;
        
        $empenosTotalMonto = 0;
        $empenosTotalTickets = 0;

        $globalRank = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $conn = 'colab_dynamic';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$conn", $config);
                DB::purge($conn);

                // Nomina
                $nomQ = DB::connection($conn)->selectOne("
                    SELECT COALESCE(SUM(COALESCE(g.autorizado, g.solicitado, 0)), 0) as nomina
                    FROM gastos g
                    LEFT JOIN conceptos c ON g.cod_concepto = c.cod_concepto
                    WHERE g.f_cancelacion IS NULL AND g.activo = 1 
                      AND COALESCE(g.f_aplicacion, g.f_autorizado, g.f_solicitado) BETWEEN ? AND ?
                      AND (LOWER(c.concepto) LIKE '%nomina%' OR LOWER(c.concepto) LIKE '%nómina%' OR LOWER(c.concepto) LIKE '%sueldo%')
                ", [$fechaInicio, $fechaFin]);
                
                $nominaTotal += (float) $nomQ->nomina;

                // Empleados de planta (Activos)
                $empQ = DB::connection($conn)->selectOne("SELECT COUNT(cod_usuario) as activos FROM usuarios WHERE activo = 1");
                $numEmpleados += (int) $empQ->activos;

                // CONTRATOS - Empeños
                $empenosArray = [];
                $opsQuery = DB::connection($conn)->select("
                     SELECT 
                        u.nombre as empleado,
                        COUNT(c.cod_contrato) as tickets,
                        SUM(c.prestamo) as monto
                     FROM contratos c
                     INNER JOIN usuarios u ON c.cod_usuario = u.cod_usuario
                     WHERE c.f_contrato BETWEEN ? AND ? AND c.f_cancelacion IS NULL
                     GROUP BY u.nombre
                ", [$fechaInicio, $fechaFin]);
                foreach($opsQuery as $o) {
                    $empenosArray[$o->empleado] = $o;
                    $empenosTotalMonto += (float) $o->monto;
                    $empenosTotalTickets += (int) $o->tickets;
                }

                // VENTAS
                $ventasArray = [];
                try {
                    $vtasQuery = DB::connection($conn)->select("
                         SELECT 
                            u.nombre as empleado,
                            COUNT(v.cod_venta) as tickets,
                            SUM(dv.venta10) as monto,
                            SUM(COALESCE((
                                CASE 
                                    WHEN v.cod_tipo_prenda = 1 THEN a.prestamo
                                    WHEN v.cod_tipo_prenda = 2 THEN au.prestamo
                                    WHEN v.cod_tipo_prenda = 3 THEN va.prestamo
                                    ELSE 0
                                END
                            ), 0)) as costo
                         FROM ventas v
                         INNER JOIN detalle_venta dv ON dv.cod_venta = v.cod_venta
                         LEFT JOIN alhajas a ON v.cod_tipo_prenda = 1 AND dv.cod_prenda = a.cod_alhaja
                         LEFT JOIN autos au ON v.cod_tipo_prenda = 2 AND dv.cod_prenda = au.cod_auto
                         LEFT JOIN varios va ON v.cod_tipo_prenda = 3 AND dv.cod_prenda = va.cod_varios
                         INNER JOIN usuarios u ON v.cod_usuario = u.cod_usuario
                         WHERE v.f_venta BETWEEN ? AND ? AND v.f_cancela IS NULL
                         GROUP BY u.nombre
                    ", [$fechaInicio, $fechaFin]);
                    foreach($vtasQuery as $v) {
                        $ventasArray[$v->empleado] = $v;
                        $ventaTotalMonto += (float) $v->monto;
                        $ventaTotalCosto += (float) $v->costo;
                        $ventaTotalTickets += (int) $v->tickets;
                    }
                } catch (\Exception $e) {
                    Log::info("Sin columna cod_usuario en ventas para {$sucursal->nombre}.");
                }

                $allEmployees = array_unique(array_merge(array_keys($empenosArray), array_keys($ventasArray)));

                foreach ($allEmployees as $emp) {
                    $eOp = isset($empenosArray[$emp]) ? $empenosArray[$emp] : (object)['tickets' => 0, 'monto' => 0];
                    $vOp = isset($ventasArray[$emp]) ? $ventasArray[$emp] : (object)['tickets' => 0, 'monto' => 0, 'costo' => 0];
                    
                    if (!isset($globalRank[$emp])) {
                        $globalRank[$emp] = [
                            'empleado' => $emp,
                            'sucursal' => $sucursal->nombre,
                            'empenos_monto' => 0,
                            'empenos_tickets' => 0,
                            'ventas_monto' => 0,
                            'utilidad_bruta' => 0,
                            'tickets_totales' => 0
                        ];
                    }

                    $globalRank[$emp]['empenos_monto'] += (float) $eOp->monto;
                    $globalRank[$emp]['empenos_tickets'] += (int) $eOp->tickets;
                    $globalRank[$emp]['ventas_monto'] += (float) $vOp->monto;
                    $globalRank[$emp]['utilidad_bruta'] += ((float) $vOp->monto - (float) $vOp->costo);
                    $globalRank[$emp]['tickets_totales'] += (int) $eOp->tickets + (int) $vOp->tickets;
                }

            } catch (\Exception $e) {
                Log::error("Error Colaboradores en {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // Fórmulas Finales
        $costoPromedioEmpleado = $numEmpleados > 0 ? $nominaTotal / $numEmpleados : 0;
        $ventaPromedioEmpleado = $numEmpleados > 0 ? $ventaTotalMonto / $numEmpleados : 0;
        
        $utilidadBrutaGlobal = $ventaTotalMonto - $ventaTotalCosto;
        $utilidadBrutaPromedioEmpleado = $numEmpleados > 0 ? $utilidadBrutaGlobal / $numEmpleados : 0;
        
        // Ratio de Rentabilidad Humana (Productividad / Gasto)
        $ratioUBvsCosto = $nominaTotal > 0 ? ($utilidadBrutaGlobal / $nominaTotal) : 0; // Por cada $1 de nomina, genera X
        $ratioUNvsCosto = 0; // Omitido sin PnL Full

        $movimientosTotales = $ventaTotalTickets + $empenosTotalTickets;
        $movimientosPromedioEmpleado = $numEmpleados > 0 ? $movimientosTotales / $numEmpleados : 0;

        usort($globalRank, fn($a, $b) => $b['tickets_totales'] <=> $a['tickets_totales']);

        $chartComposicionOperaciones = [
            'labels' => ['Ventas Realizadas', 'Contratos Nuevos (Empeños)'],
            'data' => [$ventaTotalTickets, $empenosTotalTickets]
        ];

        return response()->json([
            'nominaTotal' => $nominaTotal,
            'numEmpleados' => $numEmpleados,
            'costoPromedioEmpleado' => $costoPromedioEmpleado,
            'ventaTotalMonto' => $ventaTotalMonto,
            'ventaTotalTickets' => $ventaTotalTickets,
            'ventaPromedioEmpleado' => $ventaPromedioEmpleado,
            'utilidadBrutaPromedioEmpleado' => $utilidadBrutaPromedioEmpleado,
            'ratioUBvsCosto' => $ratioUBvsCosto,
            'ratioUNvsCosto' => $ratioUNvsCosto,
            'movimientosTotales' => $movimientosTotales,
            'movimientosPromedioEmpleado' => $movimientosPromedioEmpleado,
            
            'chartComposicionOperaciones' => $chartComposicionOperaciones,
            'rankingColaboradores' => array_values($globalRank)
        ]);
    }
}
