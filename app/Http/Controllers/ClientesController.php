<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class ClientesController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('clientes.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
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

        $clientesUnicos = []; // Key: nombre_completo

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'clientes_dynamic';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // 1. Obtener empeños y prendas en el periodo
                $empenos = DB::connection($connectionName)->select("
                    SELECT 
                        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                        MIN(c.f_alta) as f_alta,
                        COUNT(con.cod_contrato) as num_empenos,
                        SUM(con.prestamo) as prestamo,
                        SUM(CASE WHEN con.cod_estatus = 4 THEN 1 ELSE 0 END) as desempenadas,
                        SUM(CASE WHEN con.cod_estatus IN (3,5,6) THEN 1 ELSE 0 END) as perdidas
                    FROM clientes c
                    INNER JOIN contratos con ON con.cod_cliente = c.cod_cliente
                    WHERE con.f_contrato BETWEEN ? AND ?
                    GROUP BY c.nombre, c.a_paterno, c.a_materno
                ", [$fechaInicio, $fechaFin]);

                // 2. Obtener intereses pagados en el periodo
                $intereses = DB::connection($connectionName)->select("
                    SELECT 
                        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                        SUM(CASE 
                            WHEN mo.cod_tipo_movimiento = 4 THEN mo.monto10 - con.prestamo
                            WHEN mo.cod_tipo_movimiento IN (2, 3) THEN mo.monto10 - (select abono from contratos where cod_contrato =  con.cod_anterior)
                            ELSE 0
                        END) AS interes_pagado
                    FROM movimientos mo 
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    INNER JOIN clientes c ON c.cod_cliente = con.cod_cliente
                    WHERE mo.f_alta BETWEEN ? AND ?
                      AND mo.cod_tipo_movimiento IN (2,3,4)
                    GROUP BY c.nombre, c.a_paterno, c.a_materno
                ", [$fechaInicio, $fechaFin]);

                $interesesMap = [];
                foreach ($intereses as $int) {
                    $interesesMap[$int->nombre_completo] = (float) $int->interes_pagado;
                }

                // 3. Obtener ventas (compras en piso) en el periodo
                $ventas = DB::connection($connectionName)->select("
                    SELECT 
                        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                        SUM(dv.venta10) as total_ventas
                    FROM ventas ve
                    INNER JOIN detalle_venta dv ON dv.cod_venta = ve.cod_venta
                    INNER JOIN clientes c ON c.cod_cliente = ve.cod_cliente
                    WHERE ve.f_cancela IS NULL
                      AND ve.f_venta BETWEEN ? AND ?
                    GROUP BY c.nombre, c.a_paterno, c.a_materno
                ", [$fechaInicio, $fechaFin]);

                $ventasMap = [];
                foreach ($ventas as $v) {
                    $ventasMap[$v->nombre_completo] = (float) $v->total_ventas;
                }

                // 4. Obtener certificados de confianza en el periodo
                $certificados = DB::connection($connectionName)->select("
                    SELECT 
                        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                        SUM(ga.monto_garantia) as total_certificados
                    FROM garantias ga
                    INNER JOIN clientes c ON ga.cod_cliente = c.cod_cliente
                    WHERE ga.f_cancelacion IS NULL
                      AND ga.cod_estatus <> 3
                      AND ga.f_alta BETWEEN ? AND ?
                    GROUP BY c.nombre, c.a_paterno, c.a_materno
                ", [$fechaInicio, $fechaFin]);

                $certificadosMap = [];
                foreach ($certificados as $cert) {
                    $certificadosMap[$cert->nombre_completo] = (float) $cert->total_certificados;
                }

                // 5. Obtener liquidaciones de crédito en el periodo
                $liquidaciones = DB::connection($connectionName)->select("
                    SELECT 
                        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                        SUM(mo.monto10) as total_liquidacion
                    FROM movimientos mo
                    INNER JOIN creditos cre ON cre.cod_credito = mo.cod_contrato
                    INNER JOIN clientes c ON c.cod_cliente = cre.cod_cliente
                    WHERE mo.cod_tipo_movimiento = 21
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN ? AND ?
                    GROUP BY c.nombre, c.a_paterno, c.a_materno
                ", [$fechaInicio, $fechaFin]);

                $liquidacionesMap = [];
                foreach ($liquidaciones as $liq) {
                    $liquidacionesMap[$liq->nombre_completo] = (float) $liq->total_liquidacion;
                }

                // Consolidar
                foreach ($empenos as $emp) {
                    $nombre = $emp->nombre_completo;
                    if (!isset($clientesUnicos[$nombre])) {
                        $clientesUnicos[$nombre] = [
                            'nombre' => $nombre,
                            'f_alta' => $emp->f_alta,
                            'num_empenos' => 0,
                            'prestamo' => 0,
                            'desempenadas' => 0,
                            'perdidas' => 0,
                            'interes_pagado' => 0,
                            'compras_piso' => 0,
                            'certificados' => 0,
                            'liquidaciones' => 0,
                            'sucursales' => []
                        ];
                    }

                    $clientesUnicos[$nombre]['num_empenos'] += $emp->num_empenos;
                    $clientesUnicos[$nombre]['prestamo'] += $emp->prestamo;
                    $clientesUnicos[$nombre]['desempenadas'] += $emp->desempenadas;
                    $clientesUnicos[$nombre]['perdidas'] += $emp->perdidas;
                    
                    if (isset($interesesMap[$nombre])) {
                        $clientesUnicos[$nombre]['interes_pagado'] += $interesesMap[$nombre];
                    }

                    if (isset($ventasMap[$nombre])) {
                        $clientesUnicos[$nombre]['compras_piso'] += $ventasMap[$nombre];
                    }

                    if (isset($certificadosMap[$nombre])) {
                        $clientesUnicos[$nombre]['certificados'] += $certificadosMap[$nombre];
                    }

                    if (isset($liquidacionesMap[$nombre])) {
                        $clientesUnicos[$nombre]['liquidaciones'] += $liquidacionesMap[$nombre];
                    }

                    if (!in_array($sucursal->nombre, $clientesUnicos[$nombre]['sucursales'])) {
                        $clientesUnicos[$nombre]['sucursales'][] = $sucursal->nombre;
                    }
                }

            } catch (\Exception $e) {
                Log::error("Error clientes {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // ========================= KPIs =========================
        $totalClientes = count($clientesUnicos);
        $nuevos = 0;
        $recurrentes = 0;
        
        $totalEmpenos = 0;
        $montoTotalPrestado = 0;
        $interesesTotalesPagados = 0;
        $comprasPisoTotal = 0;
        $certificadosTotal = 0;
        $liquidacionesTotal = 0;
        $totalDesempenos = 0;
        $totalPerdidas = 0;
        $totalSucursalesVisitadas = 0;

        $freqOcasionales = 0;
        $freqRegulares = 0;
        $freqFrecuentes = 0;

        $topClientes = [];

        foreach ($clientesUnicos as $cliente) {
            // Nuevos vs Recurrentes
            if ($cliente['f_alta'] >= $fechaInicio) {
                $nuevos++;
            } else {
                $recurrentes++;
            }

            $totalEmpenos += $cliente['num_empenos'];
            $montoTotalPrestado += $cliente['prestamo'];
            $interesesTotalesPagados += $cliente['interes_pagado'];
            $comprasPisoTotal += ($cliente['compras_piso'] ?? 0);
            $certificadosTotal += ($cliente['certificados'] ?? 0);
            $liquidacionesTotal += ($cliente['liquidaciones'] ?? 0);
            $totalDesempenos += $cliente['desempenadas'];
            $totalPerdidas += $cliente['perdidas'];
            
            $numSucursales = count($cliente['sucursales']);
            $totalSucursalesVisitadas += $numSucursales;

            // Frecuencia
            if ($cliente['num_empenos'] <= 1) {
                $freqOcasionales++;
            } elseif ($cliente['num_empenos'] <= 4) {
                $freqRegulares++;
            } else {
                $freqFrecuentes++;
            }

            // LTV (Fórmula: Intereses + Compras + Certificados + Liquidaciones)
            $ltv = $cliente['interes_pagado'] + ($cliente['compras_piso'] ?? 0) + ($cliente['certificados'] ?? 0) + ($cliente['liquidaciones'] ?? 0);
            
            $topClientes[] = [
                'nombre' => $cliente['nombre'],
                'saldo' => $cliente['prestamo'],
                'intereses' => $cliente['interes_pagado'],
                'compras_piso' => ($cliente['compras_piso'] ?? 0),
                'certificados' => ($cliente['certificados'] ?? 0),
                'liquidaciones' => ($cliente['liquidaciones'] ?? 0),
                'ltv' => $ltv,
                'sucursales' => $numSucursales
            ];
        }

        $nuevosPorcentaje = $totalClientes > 0 ? ($nuevos / $totalClientes) * 100 : 0;
        $recurrentesPorcentaje = $totalClientes > 0 ? ($recurrentes / $totalClientes) * 100 : 0;
        
        $frecuenciaPromedio = $totalClientes > 0 ? $totalEmpenos / $totalClientes : 0;
        $ltvPromedio = $totalClientes > 0 ? ($interesesTotalesPagados + $comprasPisoTotal + $certificadosTotal + $liquidacionesTotal) / $totalClientes : 0;
        
        $totalPrendasResueltas = $totalDesempenos + $totalPerdidas;
        $porcentajePerdidas = $totalPrendasResueltas > 0 ? ($totalPerdidas / $totalPrendasResueltas) * 100 : 0;
        $porcentajeDesempeno = $totalPrendasResueltas > 0 ? ($totalDesempenos / $totalPrendasResueltas) * 100 : 0;
        
        $sucursalesPromedioPorCliente = $totalClientes > 0 ? $totalSucursalesVisitadas / $totalClientes : 1.0;

        $chartSegmentacionFrecuencia = [
            'labels' => ['Ocasionales (1)', 'Regulares (2-4)', 'Frecuentes (5+)'],
            'data' => [$freqOcasionales, $freqRegulares, $freqFrecuentes]
        ];

        $chartLTV = [
            'labels' => ['Préstamos Colocados', 'Intereses Generados'],
            'data' => [$montoTotalPrestado, $interesesTotalesPagados] 
        ];

        usort($topClientes, fn($a, $b) => $b['ltv'] <=> $a['ltv']);
        $topClientes = array_slice($topClientes, 0, 15);

        return response()->json([
            'totalClientes' => $totalClientes,
            'nuevosPorcentaje' => round($nuevosPorcentaje, 1),
            'recurrentesPorcentaje' => round($recurrentesPorcentaje, 1),
            'frecuenciaPromedio' => round($frecuenciaPromedio, 1),
            'ltvPromedio' => $ltvPromedio,
            'montoTotalPrestado' => $montoTotalPrestado,
            'interesesTotalesPagados' => $interesesTotalesPagados,
            'porcentajePerdidas' => round($porcentajePerdidas, 1),
            'porcentajeDesempeno' => round($porcentajeDesempeno, 1),
            'sucursalesPromedioPorCliente' => round($sucursalesPromedioPorCliente, 1),
            'chartSegmentacionFrecuencia' => $chartSegmentacionFrecuencia,
            'chartLTV' => $chartLTV,
            'topClientes' => $topClientes
        ]);
    }
}
