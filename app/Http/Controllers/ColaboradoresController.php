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
        
        $movimientosList = [
            1   => ['label' => 'Empeño', 'key' => 'm_1'],
            2   => ['label' => 'Refrendo', 'key' => 'm_2'],
            3   => ['label' => 'Abono a Capital', 'key' => 'm_3'],
            4   => ['label' => 'Desempeño', 'key' => 'm_4'],
            5   => ['label' => 'Venta Menudeo', 'key' => 'm_5'],
            6   => ['label' => 'Venta Mayoreo', 'key' => 'm_6'],
            7   => ['label' => 'Anticipo de Apartado', 'key' => 'm_7'],
            8   => ['label' => 'Abono de Apartado', 'key' => 'm_8'],
            9   => ['label' => 'Dotación de Caja', 'key' => 'm_9'],
            10  => ['label' => 'Retiro de Bóveda', 'key' => 'm_10'],
            11  => ['label' => 'Cancelación de Gasto', 'key' => 'm_11'],
            12  => ['label' => 'Liquidación de Apartado', 'key' => 'm_12'],
            13  => ['label' => 'Reimpresión', 'key' => 'm_13'],
            14  => ['label' => 'Retiro de Caja a Bóveda', 'key' => 'm_14'],
            15  => ['label' => 'Inicio de Caja', 'key' => 'm_15'],
            16  => ['label' => 'Cierre de Caja', 'key' => 'm_16'],
            17  => ['label' => 'Dotación de Bóveda', 'key' => 'm_17'],
            18  => ['label' => 'Gastos', 'key' => 'm_18'],
            19  => ['label' => 'Enganche Crédito', 'key' => 'm_19'],
            20  => ['label' => 'Pago Crédito', 'key' => 'm_20'],
            21  => ['label' => 'Liquidación de Crédito', 'key' => 'm_21'],
            22  => ['label' => 'Devolución por Garantía', 'key' => 'm_22'],
            23  => ['label' => 'Devolución de Crédito', 'key' => 'm_23'],
            24  => ['label' => 'Retiro de Bancos', 'key' => 'm_24'],
            25  => ['label' => 'Dotación de Bancos', 'key' => 'm_25'],
            101 => ['label' => 'Canc. Empeño', 'key' => 'm_101'],
            102 => ['label' => 'Canc. Refrendo', 'key' => 'm_102'],
            103 => ['label' => 'Canc. Abono a Capital', 'key' => 'm_103'],
            104 => ['label' => 'Canc. Desempeño', 'key' => 'm_104'],
            105 => ['label' => 'Canc. Venta', 'key' => 'm_105'],
            106 => ['label' => 'Canc. Anticipo Apartado', 'key' => 'm_106'],
            107 => ['label' => 'Canc. Abono Apartado', 'key' => 'm_107'],
            108 => ['label' => 'Canc. Liq. Apartado', 'key' => 'm_108'],
            109 => ['label' => 'Canc. Enganche Crédito', 'key' => 'm_109'],
            110 => ['label' => 'Canc. Pago Crédito', 'key' => 'm_110'],
            111 => ['label' => 'Canc. Liq. Crédito', 'key' => 'm_111'],
        ];

        $baseConfig = Config::get('database.connections.mysql');
        
        $nominaTotal = 0;
        $numEmpleados = 0;
        $ventaTotalMonto = 0;
        $ventaTotalCosto = 0;
        $ventaTotalTickets = 0;
        
        $empenosTotalTickets = 0;
        $refrendosTotalTickets = 0;
        $desempenosTotalTickets = 0;
        $otrosTotalTickets = 0;

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

                // MOVIMIENTOS - Todos los tipos
                $sucursalMovs = [];
                $movsQuery = DB::connection($conn)->select("
                     SELECT 
                        u.nombre as empleado,
                        mo.cod_tipo_movimiento,
                        COUNT(mo.cod_movimiento) as tickets,
                        SUM(mo.monto10) as monto
                     FROM movimientos mo
                     INNER JOIN usuarios u ON mo.cod_usuario = u.cod_usuario
                     WHERE mo.f_cancela IS NULL AND mo.f_alta BETWEEN ? AND ?
                     GROUP BY u.nombre, mo.cod_tipo_movimiento
                ", [$fechaInicio, $fechaFin]);
                foreach($movsQuery as $row) {
                    $emp = $row->empleado;
                    $code = (int)$row->cod_tipo_movimiento;
                    if (!isset($sucursalMovs[$emp])) {
                        $sucursalMovs[$emp] = [];
                    }
                    $sucursalMovs[$emp][$code] = [
                        'tickets' => (int)$row->tickets,
                        'monto' => (float)$row->monto
                    ];
                }

                // VENTAS (Para montos y utilidad detallados)
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

                $allEmployees = array_unique(array_merge(
                    array_keys($sucursalMovs), 
                    array_keys($ventasArray)
                ));

                foreach ($allEmployees as $emp) {
                    $vOp = isset($ventasArray[$emp]) ? $ventasArray[$emp] : (object)['tickets' => 0, 'monto' => 0, 'costo' => 0];
                    
                    if (!isset($globalRank[$emp])) {
                        $globalRank[$emp] = [
                            'empleado' => $emp,
                            'sucursal' => $sucursal->nombre,
                            'ventas_monto' => 0,
                            'ventas_tickets' => 0,
                            'utilidad_bruta' => 0,
                            'tickets_totales' => 0,
                            'monto_total' => 0,
                            'movimientos' => []
                        ];
                    }

                    $globalRank[$emp]['ventas_monto'] += (float) $vOp->monto;
                    $globalRank[$emp]['ventas_tickets'] += (int) $vOp->tickets;
                    $globalRank[$emp]['utilidad_bruta'] += ((float) $vOp->monto - (float) $vOp->costo);
                    
                    // Acumular los tickets y montos por tipo de movimiento
                    if (isset($sucursalMovs[$emp])) {
                        foreach ($sucursalMovs[$emp] as $code => $data) {
                            if (!isset($globalRank[$emp]['movimientos'][$code])) {
                                $globalRank[$emp]['movimientos'][$code] = [
                                    'tickets' => 0,
                                    'monto' => 0
                                ];
                            }
                            $globalRank[$emp]['movimientos'][$code]['tickets'] += $data['tickets'];
                            $globalRank[$emp]['movimientos'][$code]['monto'] += $data['monto'];

                            // Acumular totales globales para gráficos
                            if ($code === 1) {
                                $empenosTotalTickets += $data['tickets'];
                            } elseif ($code === 2 || $code === 3) {
                                $refrendosTotalTickets += $data['tickets'];
                            } elseif ($code === 4) {
                                $desempenosTotalTickets += $data['tickets'];
                            } elseif ($code !== 5 && $code !== 6) {
                                $otrosTotalTickets += $data['tickets'];
                            }
                        }
                    }
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

        // Calcular totalizador de operaciones individuales por colaborador
        foreach ($globalRank as $emp => &$row) {
            $totTickets = 0;
            $totMonto = 0;
            foreach ($row['movimientos'] as $code => $data) {
                $totTickets += $data['tickets'];
                $totMonto += $data['monto'];
            }
            $row['tickets_totales'] = $totTickets;
            $row['monto_total'] = $totMonto;
        }
        unset($row);

        $movimientosTotales = $ventaTotalTickets + $empenosTotalTickets + $refrendosTotalTickets + $desempenosTotalTickets + $otrosTotalTickets;
        $movimientosPromedioEmpleado = $numEmpleados > 0 ? $movimientosTotales / $numEmpleados : 0;

        $ordenarPor = $request->input('ordenar_por', 'todos');
        usort($globalRank, function($a, $b) use ($ordenarPor) {
            if ($ordenarPor === 'todos') {
                return $b['tickets_totales'] <=> $a['tickets_totales'];
            }
            if (str_starts_with($ordenarPor, 'm_')) {
                $code = (int)substr($ordenarPor, 2);
                $cntA = isset($a['movimientos'][$code]['tickets']) ? $a['movimientos'][$code]['tickets'] : 0;
                $cntB = isset($b['movimientos'][$code]['tickets']) ? $b['movimientos'][$code]['tickets'] : 0;
                return $cntB <=> $cntA;
            }
            return $b['tickets_totales'] <=> $a['tickets_totales'];
        });

        $chartComposicionOperaciones = [
            'labels' => ['Ventas Realizadas', 'Contratos Nuevos (Empeños)', 'Refrendos', 'Desempeños', 'Otros'],
            'data' => [$ventaTotalTickets, $empenosTotalTickets, $refrendosTotalTickets, $desempenosTotalTickets, $otrosTotalTickets]
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
            'rankingColaboradores' => array_values($globalRank),
            'movimientosList' => $movimientosList
        ]);
    }
}
