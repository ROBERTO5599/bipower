<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

use App\Services\PowerBIPermissionService;

class ClientesController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $regionales = PowerBIPermissionService::getRegionales();
        $regionalUserId = $request->input('regional_user_id');

        $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];
        $sucursales = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);
        if ($sucursales->isEmpty()) {
            $sucursales = Sucursal::whereNotNull('id_valora_mas')
                ->whereIn('id_valora_mas', $availableDbs)
                ->where('nombre', 'not like', '%OFICINA%')
                ->orderBy('nombre')
                ->get();
        } else {
            $sucursales = $sucursales->filter(fn($s) => in_array((int)$s->id_valora_mas, $availableDbs) && !str_contains(strtoupper($s->nombre), 'OFICINA'))->sortBy('nombre')->values();
        }

        return view('clientes.index', compact('fechaInicio', 'fechaFin', 'sucursales', 'regionales', 'regionalUserId'));
    }

    public function data(Request $request)
    {
        ini_set('max_execution_time', 180);
        set_time_limit(180);

        $rawInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        $fechaInicio = (strlen($rawInicio) <= 10 ? $rawInicio . ' 00:00:00' : $rawInicio);

        $rawFin = $request->input('fecha_fin', now()->toDateString());
        $fechaFin = (strlen($rawFin) <= 10 ? $rawFin . ' 23:59:59' : $rawFin);
        
        $regionalUserId = $request->input('regional_user_id');
        $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];

        $sucursalesPermitidas = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);
        if ($sucursalesPermitidas->isEmpty()) {
            $sucursalesPermitidas = Sucursal::whereNotNull('id_valora_mas')
                ->whereIn('id_valora_mas', $availableDbs)
                ->where('nombre', 'not like', '%OFICINA%')
                ->orderBy('nombre')
                ->get();
        } else {
            $sucursalesPermitidas = $sucursalesPermitidas->filter(fn($s) => in_array((int)$s->id_valora_mas, $availableDbs) && !str_contains(strtoupper($s->nombre), 'OFICINA'))->sortBy('nombre')->values();
        }

        $sucursalId = $request->input('sucursal_id');
        $sucursalesSeleccionadas = $sucursalId
            ? $sucursalesPermitidas->where('id_valora_mas', $sucursalId)
            : $sucursalesPermitidas;

        $clientesUnicos = []; // Key: nombre_completo
        $clientesRFM = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                DB::statement("USE `$dbName`");

                // 1. Obtener empeños y prendas en el periodo
                $empenos = DB::select("
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
                $intereses = DB::select("
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
                $ventas = DB::select("
                    SELECT 
                        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                        SUM(ve.venta10) as total_ventas
                    FROM ventas ve
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
                $certificados = DB::select("
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
                $liquidaciones = DB::select("
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
                $rfmRaw = DB::select("
                    SELECT 
                        UPPER(TRIM(CONCAT(c.nombre, ' ', c.a_paterno, ' ', c.a_materno))) as nombre_completo,
                        MIN(c.f_alta) as f_alta,
                        MIN(act.first_date) as primer_movimiento,
                        MAX(act.last_date) as last_activity,
                        SUM(act.num_trans) as total_transactions,
                        SUM(act.monto) as total_monto
                    FROM clientes c
                    LEFT JOIN (
                        SELECT cod_cliente, MIN(f_contrato) as first_date, MAX(f_contrato) as last_date, COUNT(*) as num_trans, SUM(prestamo) as monto 
                        FROM contratos 
                        WHERE f_contrato <= ?
                        GROUP BY cod_cliente
                        UNION ALL
                        SELECT ve.cod_cliente, MIN(ve.f_venta) as first_date, MAX(ve.f_venta) as last_date, COUNT(*) as num_trans, SUM(ve.venta10) as monto 
                        FROM ventas ve 
                        WHERE ve.f_cancela IS NULL AND ve.f_venta <= ?
                        GROUP BY ve.cod_cliente
                        UNION ALL
                        SELECT cod_cliente, MIN(f_alta) as first_date, MAX(f_alta) as last_date, COUNT(*) as num_trans, SUM(monto_garantia) as monto 
                        FROM garantias 
                        WHERE f_cancelacion IS NULL AND cod_estatus <> 3 AND f_alta <= ?
                        GROUP BY cod_cliente
                        UNION ALL
                        SELECT cod_cliente, MIN(f_autorizado) as first_date, MAX(f_autorizado) as last_date, COUNT(*) as num_trans, SUM(monto_total) as monto 
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
                            'primer_movimiento' => $row->primer_movimiento ?? $row->f_alta,
                            'last_activity' => $row->last_activity,
                            'total_transactions' => 0,
                            'total_monto' => 0.0,
                            'sucursales' => []
                        ];
                    }
                    if ($row->f_alta && (!$clientesRFM[$nombre]['f_alta'] || $row->f_alta < $clientesRFM[$nombre]['f_alta'])) {
                        $clientesRFM[$nombre]['f_alta'] = $row->f_alta;
                    }
                    if ($row->primer_movimiento && (!$clientesRFM[$nombre]['primer_movimiento'] || $row->primer_movimiento < $clientesRFM[$nombre]['primer_movimiento'])) {
                        $clientesRFM[$nombre]['primer_movimiento'] = $row->primer_movimiento;
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

                // Consolidar todos los clientes con actividad en el periodo
                $todosLosNombres = array_unique(array_merge(
                    array_column($empenos, 'nombre_completo'),
                    array_keys($interesesMap),
                    array_keys($ventasMap),
                    array_keys($certificadosMap),
                    array_keys($liquidacionesMap)
                ));

                foreach ($todosLosNombres as $nom) {
                    if (!isset($clientesUnicos[$nom])) {
                        $clientesUnicos[$nom] = [
                            'nombre' => $nom,
                            'f_alta' => null,
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
                    if (!in_array($sucursal->nombre, $clientesUnicos[$nom]['sucursales'])) {
                        $clientesUnicos[$nom]['sucursales'][] = $sucursal->nombre;
                    }
                }

                foreach ($empenos as $emp) {
                    $nombre = $emp->nombre_completo;
                    $clientesUnicos[$nombre]['num_empenos'] += (int) $emp->num_empenos;
                    $clientesUnicos[$nombre]['prestamo'] += (float) $emp->prestamo;
                    $clientesUnicos[$nombre]['desempenadas'] += (int) $emp->desempenadas;
                    $clientesUnicos[$nombre]['perdidas'] += (int) $emp->perdidas;
                    if ($emp->f_alta && (!$clientesUnicos[$nombre]['f_alta'] || $emp->f_alta < $clientesUnicos[$nombre]['f_alta'])) {
                        $clientesUnicos[$nombre]['f_alta'] = $emp->f_alta;
                    }
                }

                foreach ($interesesMap as $nombre => $monto) {
                    $clientesUnicos[$nombre]['interes_pagado'] += $monto;
                }

                foreach ($ventasMap as $nombre => $monto) {
                    $clientesUnicos[$nombre]['compras_piso'] += $monto;
                }

                foreach ($certificadosMap as $nombre => $monto) {
                    $clientesUnicos[$nombre]['certificados'] += $monto;
                }

                foreach ($liquidacionesMap as $nombre => $monto) {
                    $clientesUnicos[$nombre]['liquidaciones'] += $monto;
                }

            } catch (\Exception $e) {
                Log::error("Error clientes {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        try {
            DB::statement("USE " . Config::get('database.connections.mysql.database', 'sistema_prendario_2'));
        } catch (\Throwable $e) {}

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
            // Nuevos vs Recurrentes: Tomar la fecha del PRIMER MOVIMIENTO HISTÓRICO del usuario en la empresa
            $nombre = $cliente['nombre'];
            $primerMov = $clientesRFM[$nombre]['primer_movimiento'] ?? $cliente['f_alta'];
            
            if ($primerMov >= $fechaInicio && $primerMov <= $fechaFin) {
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

        $criterioTop = $request->input('criterio_top', 'ltv');

        switch ($criterioTop) {
            case 'ventas':
                usort($topClientes, fn($a, $b) => ($b['compras_piso'] <=> $a['compras_piso']) ?: ($b['ltv'] <=> $a['ltv']));
                break;
            case 'intereses':
                usort($topClientes, fn($a, $b) => ($b['intereses'] <=> $a['intereses']) ?: ($b['ltv'] <=> $a['ltv']));
                break;
            case 'empenos':
                usort($topClientes, fn($a, $b) => ($b['saldo'] <=> $a['saldo']) ?: ($b['ltv'] <=> $a['ltv']));
                break;
            case 'creditos':
                usort($topClientes, fn($a, $b) => ($b['liquidaciones'] <=> $a['liquidaciones']) ?: ($b['ltv'] <=> $a['ltv']));
                break;
            case 'certificados':
                usort($topClientes, fn($a, $b) => ($b['certificados'] <=> $a['certificados']) ?: ($b['ltv'] <=> $a['ltv']));
                break;
            default: // 'ltv'
                usort($topClientes, fn($a, $b) => $b['ltv'] <=> $a['ltv']);
                break;
        }
        $topClientes = array_slice($topClientes, 0, 50);

        // ========================= PERFILES DE CLIENTE (RFM) Y DESERCIÓN =========================
        $segmentosRFM = [
            'Oro' => 0,
            'Preferente' => 0,
            'Frecuente' => 0,
            'Tradicional' => 0
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
                $lastActCarbon = \Carbon\Carbon::parse($lastAct);
                if ($lastActCarbon->greaterThanOrEqualTo($fechaFinCarbon)) {
                    $daysSince = 0;
                } else {
                    $daysSince = (int) floor($lastActCarbon->diffInDays($fechaFinCarbon));
                }

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
            
            // Clasificación por perfil de cliente real
            if ($rScore >= 4 && $fScore >= 4 && $mScore >= 4) {
                $segment = 'Oro';
            } elseif ($rScore >= 3 && $fScore >= 3 && $mScore >= 3) {
                $segment = 'Preferente';
            } elseif ($rScore >= 2 || $fScore >= 2) {
                $segment = 'Frecuente';
            } else {
                $segment = 'Tradicional';
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
            'criterioTop' => $criterioTop,
            'totalClientes' => $totalClientes,
            'nuevosCount' => $nuevos,
            'recurrentesCount' => $recurrentes,
            'nuevosPorcentaje' => round($nuevosPorcentaje, 1),
            'recurrentesPorcentaje' => round($recurrentesPorcentaje, 1),
            'totalEmpenos' => $totalEmpenos,
            'frecuenciaPromedio' => round($frecuenciaPromedio, 1),
            'ltvPromedio' => $ltvPromedio,
            'montoTotalPrestado' => $montoTotalPrestado,
            'interesesTotalesPagados' => $interesesTotalesPagados,
            'comprasPisoTotal' => $comprasPisoTotal,
            'certificadosTotal' => $certificadosTotal,
            'liquidacionesTotal' => $liquidacionesTotal,
            'totalDesempenos' => $totalDesempenos,
            'totalPerdidas' => $totalPerdidas,
            'porcentajePerdidas' => round($porcentajePerdidas, 1),
            'porcentajeDesempeno' => round($porcentajeDesempeno, 1),
            'sucursalesPromedioPorCliente' => round($sucursalesPromedioPorCliente, 1),
            'clientesDesertores' => $clientesDesertores,
            'totalClientesHistoricos' => $totalClientesHistoricos,
            'churnRate' => round($churnRate, 1),
            'chartSegmentacionFrecuencia' => $chartSegmentacionFrecuencia,
            'chartLTV' => $chartLTV,
            'topClientes' => $topClientes,
            'chartRFM' => [
                'labels' => array_keys($segmentosRFM),
                'data' => array_values($segmentosRFM)
            ],
            'rfmTable' => $rfmTableLimit,
            'clientesRiesgo' => $clientesRiesgoLimit
        ]);
    }
}