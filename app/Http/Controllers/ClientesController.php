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
        $clientesRFM = [];

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

                // 6. Obtener datos RFM históricos
                $rfmRaw = DB::connection($connectionName)->select("
                    SELECT 
                        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                        MIN(c.f_alta) as f_alta,
                        MAX(act.last_date) as last_activity,
                        SUM(act.num_trans) as total_transactions,
                        SUM(act.monto) as total_monto
                    FROM clientes c
                    LEFT JOIN (
                        SELECT cod_cliente, MAX(f_contrato) as last_date, COUNT(*) as num_trans, SUM(prestamo) as monto 
                        FROM contratos 
                        WHERE f_contrato <= ?
                        GROUP BY cod_cliente
                        UNION ALL
                        SELECT ve.cod_cliente, MAX(ve.f_venta) as last_date, COUNT(*) as num_trans, SUM(dv.venta10) as monto 
                        FROM ventas ve 
                        INNER JOIN detalle_venta dv ON dv.cod_venta = ve.cod_venta 
                        WHERE ve.f_cancela IS NULL AND ve.f_venta <= ?
                        GROUP BY ve.cod_cliente
                        UNION ALL
                        SELECT cod_cliente, MAX(f_alta) as last_date, COUNT(*) as num_trans, SUM(monto_garantia) as monto 
                        FROM garantias 
                        WHERE f_cancelacion IS NULL AND cod_estatus <> 3 AND f_alta <= ?
                        GROUP BY cod_cliente
                        UNION ALL
                        SELECT cod_cliente, MAX(f_autorizado) as last_date, COUNT(*) as num_trans, SUM(monto_total) as monto 
                        FROM creditos 
                        WHERE f_autorizado <= ?
                        GROUP BY cod_cliente
                    ) act ON act.cod_cliente = c.cod_cliente
                    GROUP BY c.nombre, c.a_paterno, c.a_materno
                ", [$fechaFin, $fechaFin, $fechaFin, $fechaFin]);

                foreach ($rfmRaw as $row) {
                    $nombre = $row->nombre_completo;
                    if (!isset($clientesRFM[$nombre])) {
                        $clientesRFM[$nombre] = [
                            'nombre' => $nombre,
                            'f_alta' => $row->f_alta,
                            'last_activity' => $row->last_activity,
                            'total_transactions' => 0,
                            'total_monto' => 0.0,
                            'sucursales' => []
                        ];
                    }
                    if ($row->f_alta && (!$clientesRFM[$nombre]['f_alta'] || $row->f_alta < $clientesRFM[$nombre]['f_alta'])) {
                        $clientesRFM[$nombre]['f_alta'] = $row->f_alta;
                    }
                    if ($row->last_activity && (!$clientesRFM[$nombre]['last_activity'] || $row->last_activity > $clientesRFM[$nombre]['last_activity'])) {
                        $clientesRFM[$nombre]['last_activity'] = $row->last_activity;
                    }
                    $clientesRFM[$nombre]['total_transactions'] += (int) ($row->total_transactions ?? 0);
                    $clientesRFM[$nombre]['total_monto'] += (float) ($row->total_monto ?? 0);
                    if (!in_array($sucursal->nombre, $clientesRFM[$nombre]['sucursales'])) {
                        $clientesRFM[$nombre]['sucursales'][] = $sucursal->nombre;
                    }
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

        // ========================= RFM Y DESERCIÓN =========================
        $segmentosRFM = [
            'Campeones' => 0,
            'Fieles' => 0,
            'Nuevos / Prometedores' => 0,
            'En Riesgo / Atención' => 0,
            'Hibernando / Perdidos' => 0
        ];
        
        $rfmTable = [];
        $clientesRiesgo = [];
        $totalClientesHistoricos = count($clientesRFM);
        $clientesDesertores = 0;
        
        $fechaFinCarbon = \Carbon\Carbon::parse($fechaFin);
        
        foreach ($clientesRFM as $cliente) {
            $lastAct = $cliente['last_activity'];
            
            // Recency
            if ($lastAct) {
                $daysSince = $fechaFinCarbon->diffInDays(\Carbon\Carbon::parse($lastAct));
                if ($daysSince <= 30) {
                    $rScore = 5;
                } elseif ($daysSince <= 60) {
                    $rScore = 4;
                } elseif ($daysSince <= 90) {
                    $rScore = 3;
                } elseif ($daysSince <= 180) {
                    $rScore = 2;
                } else {
                    $rScore = 1;
                }
            } else {
                $daysSince = 999;
                $rScore = 1;
            }
            
            if ($daysSince > 90) {
                $clientesDesertores++;
            }
            
            // Frequency
            $freq = $cliente['total_transactions'];
            if ($freq >= 10) {
                $fScore = 5;
            } elseif ($freq >= 5) {
                $fScore = 4;
            } elseif ($freq >= 3) {
                $fScore = 3;
            } elseif ($freq >= 2) {
                $fScore = 2;
            } else {
                $fScore = 1;
            }
            
            // Monetary
            $monto = $cliente['total_monto'];
            if ($monto >= 10000) {
                $mScore = 5;
            } elseif ($monto >= 5000) {
                $mScore = 4;
            } elseif ($monto >= 2000) {
                $mScore = 3;
            } elseif ($monto >= 500) {
                $mScore = 2;
            } else {
                $mScore = 1;
            }
            
            // Segment classification
            if ($rScore >= 4 && $fScore >= 4 && $mScore >= 4) {
                $segment = 'Campeones';
            } elseif ($rScore >= 3 && $fScore >= 3 && $mScore >= 3) {
                $segment = 'Fieles';
            } elseif ($rScore >= 4 && $fScore <= 2) {
                $segment = 'Nuevos / Prometedores';
            } elseif ($rScore <= 2 && $fScore >= 3) {
                $segment = 'En Riesgo / Atención';
            } else {
                $segment = 'Hibernando / Perdidos';
            }
            
            $segmentosRFM[$segment]++;
            
            $scoreText = "{$rScore}-{$fScore}-{$mScore}";
            $recencyText = $lastAct ? "{$daysSince} días" : "Sin actividad";
            
            $rfmTable[] = [
                'nombre' => $cliente['nombre'],
                'recencia_dias' => $daysSince,
                'recencia_texto' => $recencyText,
                'frecuencia' => $freq,
                'monto' => $monto,
                'score' => $scoreText,
                'segmento' => $segment,
                'sucursales' => count($cliente['sucursales'])
            ];
            
            // High-value or high-frequency inactive customers (>60 days)
            if ($daysSince > 60 && ($monto >= 3000 || $freq >= 4)) {
                $nivelRiesgo = $daysSince > 90 ? 'Riesgo Alto' : 'Riesgo Medio';
                $clientesRiesgo[] = [
                    'nombre' => $cliente['nombre'],
                    'dias_inactivo' => $daysSince,
                    'frecuencia' => $freq,
                    'monto' => $monto,
                    'nivel_riesgo' => $nivelRiesgo
                ];
            }
        }
        
        usort($rfmTable, fn($a, $b) => $b['monto'] <=> $a['monto']);
        $rfmTableLimit = array_slice($rfmTable, 0, 100);
        
        usort($clientesRiesgo, fn($a, $b) => $b['monto'] <=> $a['monto']);
        $clientesRiesgoLimit = array_slice($clientesRiesgo, 0, 15);
        
        $churnRate = $totalClientesHistoricos > 0 ? ($clientesDesertores / $totalClientesHistoricos) * 100 : 0;

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
            'topClientes' => $topClientes,
            'churnRate' => round($churnRate, 1),
            'chartRFM' => [
                'labels' => array_keys($segmentosRFM),
                'data' => array_values($segmentosRFM)
            ],
            'rfmTable' => $rfmTableLimit,
            'clientesRiesgo' => $clientesRiesgoLimit
        ]);
    }
}