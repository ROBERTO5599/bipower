<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;
use Carbon\Carbon;

class TableroControlController extends Controller
{
    public function index()
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('tablero-control.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        ini_set('max_execution_time', 240);

        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        $sucursalId = $request->input('sucursal_id');

        $sistema = $request->input('sistema', 'varamas');
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        // Sucursales pertenecientes a MySonda (IDs 3, 5, 7, 9, 10 en la tabla sucursals)
        $mySondaIds = [3, 5, 7, 9, 10];

        if ($sistema === 'mysonda') {
            $sucursales = $sucursales->filter(function($s) use ($mySondaIds) {
                return in_array((int)$s->id, $mySondaIds) || strtoupper(trim($s->descripcion ?? '')) === 'MYSONDA';
            });
        } elseif ($sistema === 'varamas') {
            $sucursales = $sucursales->filter(function($s) use ($mySondaIds) {
                return !in_array((int)$s->id, $mySondaIds) && strtoupper(trim($s->descripcion ?? '')) !== 'MYSONDA';
            });
        }

        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $carbonInicio = Carbon::parse($fechaInicio);
        $carbonFin = Carbon::parse($fechaFin);
        $anio = $carbonInicio->year;
        $mesInicio = $carbonInicio->month;
        $mesFin = $carbonFin->month;

        $categories = ['MERCANCIA GENERAL', 'ORO', 'PLATA', 'AUTOS'];
        $standardIndicators = [
            "INVENTARIO", "Empeño", "Refrendos", "Desempeño", "Ventas",
            "Ventas Directas", "Apartados Liquidados",
            "Intereses", "Remate", "Bazar",
            "Utilidad del Mes Por venta", "Interés + Util Vta",
            "Gastos del Mes", "Utilidad Neta del Mes",
            "Créditos Vigentes",
            "Créditos Colocados",
            "Pago Crédito/Abono a Créditos",
            "Liquidación de Créditos",
            "Utilidad del Crédito",
            "Crédito Vencido Mensual - Préstamo",
            "Crédito Vencido Mensual - Cantidad Pagada",
            "Crédito Vencido Mensual - Adeudo",
            "Crédito Vencido Global - Préstamo",
            "Crédito Vencido Global - Cantidad Pagada",
            "Crédito Vencido Global - Adeudo",
            "Devoluciones",                 
            "Garantías - Ventas",               
            "Garantías - Apartados Liquidados",
            "Garantías - Enganche Crédito",
        ];

        // Initialize final result structure
        $tablero = [];
        foreach ($standardIndicators as $ind) {
            $tablero[$ind] = [];
            foreach ($categories as $cat) {
                $tablero[$ind][$cat] = [
                    'meta' => 0.0,
                    'avance' => 0.0,
                    'operaciones' => 0
                ];
            }
        }

        $vtasOpsTotal = 0;
        $liqOpsTotal = 0;

        $varamasSucursales = $sucursalesSeleccionadas->reject(function($s) use ($mySondaIds) {
            return in_array((int)$s->id, $mySondaIds) || strtoupper(trim($s->descripcion ?? '')) === 'MYSONDA';
        });

        $mySondaSucursales = $sucursalesSeleccionadas->filter(function($s) use ($mySondaIds) {
            return in_array((int)$s->id, $mySondaIds) || strtoupper(trim($s->descripcion ?? '')) === 'MYSONDA';
        });

        $baseConfig = Config::get('database.connections.mysql');

        // 1. Procesar sucursales de Sistema Varamas (SVM) desde sus bases de datos prendarias
        foreach ($varamasSucursales as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi_tablero_' . $sucursal->id_valora_mas;

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                // 1. Fetch Metas
                $metasQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(meta) as total_meta
                    FROM metas
                    WHERE anio = :anio AND mes BETWEEN :mesInicio AND :mesFin
                    GROUP BY indicador, categoria
                ", [
                    'anio' => $anio,
                    'mesInicio' => $mesInicio,
                    'mesFin' => $mesFin
                ]);

                foreach ($metasQuery as $metaRow) {
                    $dbInd = $metaRow->indicador;
                    $dbCat = $this->normalizeCategory($metaRow->categoria);
                    $metaVal = (float)$metaRow->total_meta;

                    foreach ($standardIndicators as $stdInd) {
                        if ($this->matchMeta($dbInd, $stdInd)) {
                            if (in_array($dbCat, $categories)) {
                                $tablero[$stdInd][$dbCat]['meta'] += $metaVal;
                            }
                        }
                    }
                }

                // 2. Fetch Avance Categorizado (Movimientos C# SQL)
                $movsQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(avance) AS avance, COUNT(DISTINCT cod_operacion) AS operaciones
                    FROM (
                        SELECT 'Ventas_Vta' AS indicador,
                            CASE
                                WHEN ve.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8  AND 26  THEN 'ORO'
                                WHEN ve.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN ve.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN ve.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            CASE
                                WHEN ve.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                                ELSE 0
                            END AS avance,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        INNER JOIN ventas ve ON ve.cod_movimiento = mo.cod_movimiento
                        INNER JOIN detalle_venta dv ON dv.cod_venta = ve.cod_venta
                        LEFT JOIN alhajas al ON al.cod_alhaja = dv.cod_prenda AND ve.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_auto   = dv.cod_prenda AND ve.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_varios = dv.cod_prenda AND ve.cod_tipo_prenda = 3
                        WHERE mo.f_alta BETWEEN :f1_1 AND :f2_1
                          AND mo.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento IN (5, 6)

                        UNION ALL

                        SELECT 'Ventas_Liq' AS indicador,
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8  AND 26  THEN 'ORO'
                                WHEN ap.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN ap.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN ap.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                                WHEN ap.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                                WHEN ap.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                                ELSE 0
                            END AS avance,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        LEFT JOIN apartado_pagos apg ON apg.cod_movimiento = mo.cod_movimiento
                        INNER JOIN apartados ap ON ap.cod_apartado = COALESCE(apg.cod_apartado, mo.cod_contrato, mo.contrato)
                        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                        LEFT JOIN alhajas al ON al.cod_alhaja = da.cod_prenda AND ap.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_auto   = da.cod_prenda AND ap.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_varios = da.cod_prenda AND ap.cod_tipo_prenda = 3
                        WHERE mo.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento = 12
                          AND mo.f_alta BETWEEN :f1_2 AND :f2_2

                        UNION ALL

                        SELECT 'Empeño' AS indicador,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            COALESCE(
                                CASE
                                    WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
                                    WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
                                    WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
                                END
                            , 0) AS avance,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE con.f_cancelacion IS NULL                             
                          AND mo.cod_tipo_movimiento = 1
                          AND mo.f_alta BETWEEN :f1_3 AND :f2_3

                        UNION ALL

                        SELECT 'Refrendos' AS indicador,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            COALESCE(
                                CASE
                                    WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
                                    WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
                                    WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
                                END
                            , 0) AS avance,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE mo.f_alta BETWEEN :f1_4 AND :f2_4
                          AND mo.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento IN (2, 3)

                        UNION ALL

                        SELECT indicador, categoria, SUM(abono_capital) + SUM(prestamo_desempenio) AS avance, cod_operacion
                        FROM (
                            SELECT 'Desempeño' AS indicador,
                                CASE
                                    WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                    WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                    WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                    WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                    ELSE 'SIN CATEGORIA'
                                END AS categoria,
                                0 AS abono_capital,
                                COALESCE(CASE
                                    WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
                                    WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
                                    WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
                                END, 0) AS prestamo_desempenio,
                                mo.cod_movimiento AS cod_operacion
                            FROM movimientos mo
                            INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                            LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                            LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                            LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                            WHERE con.f_cancelacion IS NULL
                              AND mo.cod_tipo_movimiento = 4
                              AND mo.f_alta BETWEEN :f1_5 AND :f2_5

                            UNION ALL

                            SELECT 'Desempeño' AS indicador,
                                CASE
                                    WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                    WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                    WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                    WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                    ELSE 'SIN CATEGORIA'
                                END AS categoria,
                                COALESCE((SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior ORDER BY f_contrato DESC LIMIT 1), 0) AS abono_capital,
                                0 AS prestamo_desempenio,
                                mo.cod_movimiento AS cod_operacion
                            FROM movimientos mo
                            INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                            LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1 
                                 AND al.id = (SELECT MIN(id) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)
                            LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2 
                                 AND au.id = (SELECT MIN(id) FROM autos WHERE cod_contrato = con.cod_seguimiento)
                            LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3 
                                 AND va.id = (SELECT MIN(id) FROM varios WHERE cod_contrato = con.cod_seguimiento)
                            WHERE con.f_cancelacion IS NULL
                              AND mo.cod_tipo_movimiento = 3
                              AND mo.f_alta BETWEEN :f1_6 AND :f2_6
                        ) AS desempeno_movs
                        GROUP BY indicador, categoria, cod_operacion
                    ) AS movs_indicador
                    WHERE avance > 0
                    GROUP BY indicador, categoria
                ", [
                    'f1_1' => $fechaInicio, 'f2_1' => $fechaFin,
                    'f1_2' => $fechaInicio, 'f2_2' => $fechaFin,
                    'f1_3' => $fechaInicio, 'f2_3' => $fechaFin,
                    'f1_4' => $fechaInicio, 'f2_4' => $fechaFin,
                    'f1_5' => $fechaInicio, 'f2_5' => $fechaFin,
                    'f1_6' => $fechaInicio, 'f2_6' => $fechaFin,
                ]);

                foreach ($movsQuery as $row) {
                    $ind = $row->indicador;
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    $ops = (int)$row->operaciones;

                    if ($ind === 'Ventas_Vta') {
                        $vtasOpsTotal += $ops;
                        if (isset($tablero['Ventas Directas'][$cat])) {
                            $tablero['Ventas Directas'][$cat]['avance'] += $val;
                            $tablero['Ventas Directas'][$cat]['operaciones'] += $ops;
                        }
                        if (isset($tablero['Ventas'][$cat])) {
                            $tablero['Ventas'][$cat]['avance'] += $val;
                            $tablero['Ventas'][$cat]['operaciones'] += $ops;
                        }
                    } elseif ($ind === 'Ventas_Liq') {
                        $liqOpsTotal += $ops;
                        if (isset($tablero['Apartados Liquidados'][$cat])) {
                            $tablero['Apartados Liquidados'][$cat]['avance'] += $val;
                            $tablero['Apartados Liquidados'][$cat]['operaciones'] += $ops;
                        }
                        if (isset($tablero['Ventas'][$cat])) {
                            $tablero['Ventas'][$cat]['avance'] += $val;
                            $tablero['Ventas'][$cat]['operaciones'] += $ops;
                        }
                    } else {
                        if (isset($tablero[$ind]) && isset($tablero[$ind][$cat])) {
                            $tablero[$ind][$cat]['avance'] += $val;
                            $tablero[$ind][$cat]['operaciones'] += $ops;
                        }
                    }
                }

                // 3. Gastos
                $gastosQuery = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(g.solicitado), 0) AS TotalGastos, COUNT(g.cod_gasto) AS TotalOperaciones
                    FROM gastos g
                    WHERE g.activo = 1
                      AND g.cod_estatus = 2
                      AND g.f_solicitado BETWEEN :f1 AND :f2
                ", ['f1' => $fechaInicio, 'f2' => $fechaFin]);
                
                if ($gastosQuery) {
                    $tablero['Gastos del Mes']['MERCANCIA GENERAL']['avance'] += (float)$gastosQuery->TotalGastos;
                    $tablero['Gastos del Mes']['MERCANCIA GENERAL']['operaciones'] += (int)$gastosQuery->TotalOperaciones;
                }

                // 4. Intereses
                $interesesQuery = DB::connection($connectionName)->select("
                    SELECT categoria, SUM(interesCobrado) AS avance, COUNT(DISTINCT cod_operacion) AS operaciones
                    FROM (
                        SELECT con.cod_contrato,
                            CASE
                                WHEN al.kilataje BETWEEN 8  AND 26  THEN 'ORO'
                                WHEN al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                ELSE 'MERCANCIA GENERAL'
                            END AS categoria,
                            CASE
                                WHEN mo.cod_tipo_movimiento = 4       THEN (mo.monto10 - con.prestamo) / cnt.total
                                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - COALESCE(ca.abono, 0)) / cnt.total
                                ELSE 0
                            END AS interesCobrado,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        LEFT JOIN contratos ca ON ca.cod_contrato = con.cod_anterior
                        LEFT JOIN (SELECT cod_contrato, COALESCE(NULLIF(COUNT(*),0),1) AS total FROM alhajas GROUP BY cod_contrato) cnt ON cnt.cod_contrato = con.cod_seguimiento
                        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL
                          AND con.cod_tipo_prenda = 1
                          AND mo.cod_tipo_movimiento IN (2, 3, 4)
                          AND mo.f_alta BETWEEN :f1_1 AND :f2_1

                        UNION ALL

                        SELECT con.cod_contrato, 'AUTOS' AS categoria,
                            CASE
                                WHEN mo.cod_tipo_movimiento = 4       THEN (mo.monto10 - con.prestamo) / cnt.total
                                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - COALESCE(ca.abono, 0)) / cnt.total
                                ELSE 0
                            END AS interesCobrado,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        LEFT JOIN contratos ca ON ca.cod_contrato = con.cod_anterior
                        LEFT JOIN (SELECT cod_contrato, COALESCE(NULLIF(COUNT(*),0),1) AS total FROM autos GROUP BY cod_contrato) cnt ON cnt.cod_contrato = con.cod_seguimiento
                        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL
                          AND con.cod_tipo_prenda = 2
                          AND mo.cod_tipo_movimiento IN (2, 3, 4)
                          AND mo.f_alta BETWEEN :f1_2 AND :f2_2

                        UNION ALL

                        SELECT con.cod_contrato, 'MERCANCIA GENERAL' AS categoria,
                            CASE
                                WHEN mo.cod_tipo_movimiento = 4       THEN (mo.monto10 - con.prestamo) / cnt.total
                                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - COALESCE(ca.abono, 0)) / cnt.total
                                ELSE 0
                            END AS interesCobrado,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        LEFT JOIN contratos ca ON ca.cod_contrato = con.cod_anterior
                        LEFT JOIN (SELECT cod_contrato, COALESCE(NULLIF(COUNT(*),0),1) AS total FROM varios GROUP BY cod_contrato) cnt ON cnt.cod_contrato = con.cod_seguimiento
                        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL
                          AND con.cod_tipo_prenda = 3
                          AND mo.cod_tipo_movimiento IN (2, 3, 4)
                          AND mo.f_alta BETWEEN :f1_3 AND :f2_3
                    ) base
                    GROUP BY categoria
                ", [
                    'f1_1' => $fechaInicio, 'f2_1' => $fechaFin,
                    'f1_2' => $fechaInicio, 'f2_2' => $fechaFin,
                    'f1_3' => $fechaInicio, 'f2_3' => $fechaFin,
                ]);

                foreach ($interesesQuery as $row) {
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    $ops = (int)$row->operaciones;
                    if (isset($tablero['Intereses'][$cat])) {
                        $tablero['Intereses'][$cat]['avance'] += $val;
                        $tablero['Intereses'][$cat]['operaciones'] += $ops;
                    }
                }

                // 5. Utilidad Ventas
                $utilVentasQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(avance) AS avance, COUNT(DISTINCT cod_operacion) AS operaciones
                    FROM (
                        SELECT 'Utilidad del Mes Por venta' AS indicador,
                            CASE
                                WHEN ve.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8  AND 26  THEN 'ORO'
                                WHEN ve.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN ve.cod_tipo_prenda = 1 THEN 'ORO'
                                WHEN ve.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN ve.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'MERCANCIA GENERAL'
                            END AS categoria,
                            COALESCE(dv.venta10, 0) - CASE
                                WHEN ve.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                                ELSE 0
                            END AS avance,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        INNER JOIN ventas ve ON ve.cod_movimiento = mo.cod_movimiento
                        INNER JOIN detalle_venta dv ON dv.cod_venta = ve.cod_venta
                        LEFT JOIN alhajas al ON al.cod_alhaja = dv.cod_prenda AND ve.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_auto   = dv.cod_prenda AND ve.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_varios = dv.cod_prenda AND ve.cod_tipo_prenda = 3
                        WHERE mo.f_alta BETWEEN :f1_1 AND :f2_1
                          AND mo.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento IN (5, 6)

                        UNION ALL

                        SELECT 'Utilidad del Mes Por venta' AS indicador,
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8  AND 26  THEN 'ORO'
                                WHEN ap.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN ap.cod_tipo_prenda = 1 THEN 'ORO'
                                WHEN ap.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN ap.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 THEN COALESCE(al.precio, 0) - COALESCE(al.prestamo, 0)
                                WHEN ap.cod_tipo_prenda = 2 THEN COALESCE(au.precio, 0) - COALESCE(au.prestamo, 0)
                                WHEN ap.cod_tipo_prenda = 3 THEN COALESCE(va.precio, 0) - COALESCE(va.prestamo, 0)
                                ELSE 0
                            END AS avance,
                            mo.cod_movimiento AS cod_operacion
                        FROM movimientos mo
                        LEFT JOIN apartado_pagos apg ON apg.cod_movimiento = mo.cod_movimiento
                        INNER JOIN apartados ap ON ap.cod_apartado = COALESCE(apg.cod_apartado, mo.cod_contrato, mo.contrato)
                        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                        LEFT JOIN alhajas al ON al.cod_alhaja = da.cod_prenda AND ap.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_auto   = da.cod_prenda AND ap.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_varios = da.cod_prenda AND ap.cod_tipo_prenda = 3
                        WHERE mo.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento = 12
                          AND mo.f_alta BETWEEN :f1_2 AND :f2_2
                    ) t
                    GROUP BY indicador, categoria
                ", [
                    'f1_1' => $fechaInicio, 'f2_1' => $fechaFin,
                    'f1_2' => $fechaInicio, 'f2_2' => $fechaFin,
                ]);

                foreach ($utilVentasQuery as $row) {
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    $ops = (int)$row->operaciones;
                    if (isset($tablero['Utilidad del Mes Por venta'][$cat])) {
                        $tablero['Utilidad del Mes Por venta'][$cat]['avance'] += $val;
                        $tablero['Utilidad del Mes Por venta'][$cat]['operaciones'] += $ops;
                    }
                }

                // 6. INVENTARIO Depositaria (C# Query historico_articulos)
                $invQuery = DB::connection($connectionName)->select("
                    SELECT 'INVENTARIO' AS indicador,
                        CASE
                            WHEN tipo = 1 AND kilataje BETWEEN 8   AND 26  THEN 'ORO'
                            WHEN tipo = 1 AND kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                            WHEN tipo = 2                                  THEN 'AUTOS'
                            WHEN tipo = 3                                  THEN 'MERCANCIA GENERAL'
                            ELSE 'OTROS'
                        END AS categoria,
                        SUM(prestamo) AS avance,
                        COUNT(cod_prenda) AS operaciones
                    FROM (
                        SELECT 1 AS tipo, a.kilataje, h.prestamo, h.cod_prenda
                        FROM (
                            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY h.cod_prenda
                                       ORDER BY h.f_movimiento DESC, h.id_historico DESC
                                   ) AS rn
                            FROM historico_articulos h
                            WHERE h.cod_tipo_prenda = 1
                              AND h.f_movimiento <= :f2_1
                        ) h
                        INNER JOIN alhajas a ON a.cod_alhaja = h.cod_prenda
                        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (1, 2)

                        UNION ALL

                        SELECT 3 AS tipo, 0 AS kilataje, h.prestamo, h.cod_prenda
                        FROM (
                            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY h.cod_prenda
                                       ORDER BY h.f_movimiento DESC, h.id_historico DESC
                                   ) AS rn
                            FROM historico_articulos h
                            WHERE h.cod_tipo_prenda = 3
                              AND h.f_movimiento <= :f2_2
                        ) h
                        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (1, 2)

                        UNION ALL

                        SELECT 2 AS tipo, 0 AS kilataje, h.prestamo, h.cod_prenda
                        FROM (
                            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY h.cod_prenda
                                       ORDER BY h.f_movimiento DESC, h.id_historico DESC
                                   ) AS rn
                            FROM historico_articulos h
                            WHERE h.cod_tipo_prenda = 2
                              AND h.f_movimiento <= :f2_3
                        ) h
                        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (1, 2)
                    ) AS t
                    GROUP BY categoria
                ", ['f2_1' => $fechaFin, 'f2_2' => $fechaFin, 'f2_3' => $fechaFin]);

                foreach ($invQuery as $row) {
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    $ops = (int)$row->operaciones;
                    if (isset($tablero['INVENTARIO'][$cat])) {
                        $tablero['INVENTARIO'][$cat]['avance'] += $val;
                        $tablero['INVENTARIO'][$cat]['operaciones'] += $ops;
                    }
                }

                // 7. Bazar Piso de Venta (C# Query historico_articulos)
                $bazarQuery = DB::connection($connectionName)->select("
                    SELECT 'Bazar' AS indicador,
                        CASE
                            WHEN tipo = 1 AND kilataje BETWEEN 8   AND 26  THEN 'ORO'
                            WHEN tipo = 1 AND kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                            WHEN tipo = 2                                  THEN 'AUTOS'
                            WHEN tipo = 3                                  THEN 'MERCANCIA GENERAL'
                            ELSE 'OTROS'
                        END AS categoria,
                        SUM(prestamo) AS avance,
                        COUNT(cod_prenda) AS operaciones
                    FROM (
                        SELECT 1 AS tipo, a.kilataje, h.prestamo, h.cod_prenda
                        FROM (
                            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY h.cod_prenda
                                       ORDER BY h.f_movimiento DESC, h.id_historico DESC
                                   ) AS rn
                            FROM historico_articulos h
                            WHERE h.cod_tipo_prenda = 1
                              AND h.f_movimiento <= :f2_1
                        ) h
                        INNER JOIN alhajas a ON a.cod_alhaja = h.cod_prenda
                        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (9)

                        UNION ALL

                        SELECT 3 AS tipo, 0 AS kilataje, h.prestamo, h.cod_prenda
                        FROM (
                            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY h.cod_prenda
                                       ORDER BY h.f_movimiento DESC, h.id_historico DESC
                                   ) AS rn
                            FROM historico_articulos h
                            WHERE h.cod_tipo_prenda = 3
                              AND h.f_movimiento <= :f2_2
                        ) h
                        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (9)

                        UNION ALL

                        SELECT 2 AS tipo, 0 AS kilataje, h.prestamo, h.cod_prenda
                        FROM (
                            SELECT h.cod_prenda, h.prestamo, h.cod_estatus_prenda,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY h.cod_prenda
                                       ORDER BY h.f_movimiento DESC, h.id_historico DESC
                                   ) AS rn
                            FROM historico_articulos h
                            WHERE h.cod_tipo_prenda = 2
                              AND h.f_movimiento <= :f2_3
                        ) h
                        WHERE h.rn = 1 AND h.cod_estatus_prenda IN (9)
                    ) AS t
                    GROUP BY categoria
                ", ['f2_1' => $fechaFin, 'f2_2' => $fechaFin, 'f2_3' => $fechaFin]);

                foreach ($bazarQuery as $row) {
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    $ops = (int)$row->operaciones;
                    if (isset($tablero['Bazar'][$cat])) {
                        $tablero['Bazar'][$cat]['avance'] += $val;
                        $tablero['Bazar'][$cat]['operaciones'] += $ops;
                    }
                }

                // 8. Remate
                $remateQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(prestamo) AS avance, COUNT(cod_operacion) AS operaciones
                    FROM (
                        SELECT 'Remate' AS indicador,
                            CASE
                                WHEN a.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN a.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                ELSE 'ORO'
                            END AS categoria,
                            a.prestamo,
                            a.cod_alhaja AS cod_operacion
                        FROM alhajas a
                        WHERE a.p_venta IS NOT NULL AND a.p_venta BETWEEN :f1_1 AND :f2_1

                        UNION ALL

                        SELECT 'Remate' AS indicador,
                            'MERCANCIA GENERAL' AS categoria,
                            v.prestamo,
                            v.cod_varios AS cod_operacion
                        FROM varios v
                        WHERE v.p_venta IS NOT NULL AND v.p_venta BETWEEN :f1_2 AND :f2_2

                        UNION ALL

                        SELECT 'Remate' AS indicador,
                            'AUTOS' AS categoria,
                            au.prestamo,
                            au.cod_auto AS cod_operacion
                        FROM autos au
                        WHERE au.p_venta IS NOT NULL AND au.p_venta BETWEEN :f1_3 AND :f2_3
                    ) AS t
                    GROUP BY indicador, categoria
                ", [
                    'f1_1' => $fechaInicio, 'f2_1' => $fechaFin,
                    'f1_2' => $fechaInicio, 'f2_2' => $fechaFin,
                    'f1_3' => $fechaInicio, 'f2_3' => $fechaFin,
                ]);

                foreach ($remateQuery as $row) {
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    $ops = (int)$row->operaciones;
                    if (isset($tablero['Remate'][$cat])) {
                        $tablero['Remate'][$cat]['avance'] += $val;
                        $tablero['Remate'][$cat]['operaciones'] += $ops;
                    }
                }

                // 9. Créditos Tablero
                $creditosQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(avance) AS avance, SUM(operaciones) AS operaciones
                    FROM (
                        -- CRÉDITOS VIGENTES
                        SELECT 
                            'Créditos Vigentes' AS indicador,
                            'MERCANCIA GENERAL' AS categoria,
                            COALESCE(SUM(va.prestamo), 0) AS avance,
                            COUNT(cre.cod_credito) AS operaciones
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 2
                          AND cre.f_solicitud < :f2_1

                        UNION ALL

                        -- COLOCADOS
                        SELECT 
                            'Créditos Colocados' AS indicador,
                            'MERCANCIA GENERAL' AS categoria,
                            COALESCE(SUM(art.prestamo), 0),
                            COUNT(DISTINCT mo.cod_movimiento)
                        FROM movimientos mo
                        INNER JOIN creditos op  ON op.cod_credito = mo.cod_contrato
                        INNER JOIN varios   art ON art.cod_varios  = op.cod_varios
                        WHERE mo.cod_estatus IN (1, 2)
                          AND mo.cod_tipo_movimiento = 19
                          AND mo.f_alta BETWEEN :f1_2 AND :f2_2

                        UNION ALL

                        -- PAGOS
                        SELECT 
                            'Pago Crédito/Abono a Créditos',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(mo.monto), 0),
                            COUNT(DISTINCT mo.cod_movimiento)
                        FROM movimientos mo
                        INNER JOIN creditos op ON op.cod_credito = mo.cod_contrato
                        WHERE mo.cod_estatus IN (1, 2)
                          AND mo.cod_tipo_movimiento = 20
                          AND mo.f_alta BETWEEN :f1_3 AND :f2_3

                        UNION ALL

                        -- LIQUIDADOS
                        SELECT 
                            'Liquidación de Créditos',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(art.prestamo), 0),
                            COUNT(DISTINCT mo.cod_movimiento)
                        FROM movimientos mo
                        INNER JOIN creditos op  ON op.cod_credito = mo.cod_contrato
                        INNER JOIN varios   art ON art.cod_varios  = op.cod_varios
                        WHERE mo.cod_estatus IN (1, 2)
                          AND mo.cod_tipo_movimiento = 21
                          AND mo.f_alta BETWEEN :f1_4 AND :f2_4

                        UNION ALL

                        -- UTILIDAD
                        SELECT 
                            'Utilidad del Crédito',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(op.monto_total - art.prestamo), 0),
                            COUNT(DISTINCT mo.cod_movimiento)
                        FROM movimientos mo
                        INNER JOIN creditos op  ON op.cod_credito = mo.cod_contrato
                        INNER JOIN varios   art ON art.cod_varios  = op.cod_varios
                        WHERE mo.cod_estatus IN (1, 2)
                          AND mo.cod_tipo_movimiento = 21
                          AND mo.f_alta BETWEEN :f1_5 AND :f2_5

                        UNION ALL

                        -- VENCIDOS GLOBAL (solo préstamo)
                        SELECT 
                            'Crédito Vencido Global - Préstamo',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(va.prestamo), 0),
                            COUNT(cre.cod_credito)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud < :f2_6

                        UNION ALL

                        -- VENCIDOS GLOBAL (solo Pagado)
                        SELECT 
                            'Crédito Vencido Global - Cantidad Pagada',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(cre.pagado), 0),
                            COUNT(cre.cod_credito)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud < :f2_7

                        UNION ALL

                        -- VENCIDOS GLOBAL (solo Adeudo)
                        SELECT 
                            'Crédito Vencido Global - Adeudo',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(cre.adeudo), 0),
                            COUNT(cre.cod_credito)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud < :f2_8

                        UNION ALL

                        -- VENCIDOS MENSUAL (solo préstamo)
                        SELECT 
                            'Crédito Vencido Mensual - Préstamo',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(va.prestamo), 0),
                            COUNT(cre.cod_credito)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud BETWEEN :f1_9 AND :f2_9

                        UNION ALL

                        -- VENCIDOS MENSUAL (solo Pagado)
                        SELECT 
                            'Crédito Vencido Mensual - Cantidad Pagada',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(cre.pagado), 0),
                            COUNT(cre.cod_credito)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud BETWEEN :f1_10 AND :f2_10

                        UNION ALL

                        -- VENCIDOS MENSUAL (solo Adeudo)
                        SELECT 
                            'Crédito Vencido Mensual - Adeudo',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(cre.adeudo), 0),
                            COUNT(cre.cod_credito)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud BETWEEN :f1_11 AND :f2_11

                        UNION ALL

                        -- DEVOLUCIONES
                        SELECT 
                            'Devoluciones',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(va.prestamo), 0),
                            COUNT(cre.cod_credito)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 6
                          AND cre.f_solicitud BETWEEN :f1_12 AND :f2_12
                    ) AS t
                    GROUP BY indicador, categoria
                ", [
                    'f2_1' => $fechaFin,
                    'f1_2' => $fechaInicio, 'f2_2' => $fechaFin,
                    'f1_3' => $fechaInicio, 'f2_3' => $fechaFin,
                    'f1_4' => $fechaInicio, 'f2_4' => $fechaFin,
                    'f1_5' => $fechaInicio, 'f2_5' => $fechaFin,
                    'f2_6' => $fechaFin,
                    'f2_7' => $fechaFin,
                    'f2_8' => $fechaFin,
                    'f1_9' => $fechaInicio, 'f2_9' => $fechaFin,
                    'f1_10' => $fechaInicio, 'f2_10' => $fechaFin,
                    'f1_11' => $fechaInicio, 'f2_11' => $fechaFin,
                    'f1_12' => $fechaInicio, 'f2_12' => $fechaFin,
                ]);

                foreach ($creditosQuery as $row) {
                    $ind = $row->indicador;
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    $ops = (int)$row->operaciones;
                    if (isset($tablero[$ind]) && isset($tablero[$ind][$cat])) {
                        $tablero[$ind][$cat]['avance'] += $val;
                        $tablero[$ind][$cat]['operaciones'] += $ops;
                    }
                }

                // 10. Garantías
                $garantiasQuery = DB::connection($connectionName)->select("
                    SELECT indicador, SUM(monto_garantia) AS avance, COUNT(1) AS operaciones
                    FROM (
                        SELECT 
                            CASE 
                                WHEN gar.cod_tipo_movimiento IN (5, 6) THEN 'Garantías - Ventas'
                                WHEN gar.cod_tipo_movimiento = 12      THEN 'Garantías - Apartados Liquidados'
                                WHEN gar.cod_tipo_movimiento = 19      THEN 'Garantías - Enganche Crédito'
                            END AS indicador,
                            gar.monto_garantia
                        FROM garantias gar
                        WHERE gar.f_alta BETWEEN :f1 AND :f2
                          AND gar.f_cancelacion IS NULL
                          AND gar.cod_tipo_movimiento IN (5, 6, 12, 19)
                          AND gar.cod_tipo_prenda = 3
                          AND gar.cod_estatus IN (1, 2, 4)
                    ) AS t
                    GROUP BY indicador
                ", ['f1' => $fechaInicio, 'f2' => $fechaFin]);

                foreach ($garantiasQuery as $row) {
                    $ind = $row->indicador;
                    $val = (float)$row->avance;
                    $ops = (int)$row->operaciones;
                    if (isset($tablero[$ind]) && isset($tablero[$ind]['MERCANCIA GENERAL'])) {
                        $tablero[$ind]['MERCANCIA GENERAL']['avance'] += $val;
                        $tablero[$ind]['MERCANCIA GENERAL']['operaciones'] += $ops;
                    }
                }

            } catch (\Exception $e) {
                Log::error("Error TableroControl en sucursal {$sucursal->nombre}: " . $e->getMessage());
                continue;
            }
        }

        // 2. Procesar sucursales de MySonda desde valorama_intranet.resumen_operaciones_mysonda
        $mySondaDetail = null;
        if ($mySondaSucursales->count() > 0) {
            $mySondaIntranetIds = $mySondaSucursales->pluck('id')->toArray();

            // Verificar si existen registros en el rango de fechas seleccionado
            $hasDateMatch = DB::table('resumen_operaciones_mysonda')
                ->whereIn('sucursal_id', $mySondaIntranetIds)
                ->whereBetween('fecha_reporte', [$carbonInicio->toDateString(), $carbonFin->toDateString()])
                ->exists();

            $mySondaQuery = DB::table('resumen_operaciones_mysonda')
                ->whereIn('sucursal_id', $mySondaIntranetIds);

            if ($hasDateMatch) {
                $mySondaQuery->whereBetween('fecha_reporte', [$carbonInicio->toDateString(), $carbonFin->toDateString()]);
            } else {
                // Fallback a la fecha reportada mas reciente si la fecha elegida no tiene cargas
                $latestDate = DB::table('resumen_operaciones_mysonda')
                    ->whereIn('sucursal_id', $mySondaIntranetIds)
                    ->max('fecha_reporte');

                if ($latestDate) {
                    $mySondaQuery->where('fecha_reporte', $latestDate);
                }
            }

            $mySondaSummary = (clone $mySondaQuery)
                ->select(
                    DB::raw('COALESCE(SUM(refrendos_pago_a_cuenta + desempenos_prestamo), 0) as empenos_totales'),
                    DB::raw('COALESCE(SUM(empenos_prestamo), 0) as empenos'),
                    DB::raw('COALESCE(SUM(refrendos_prestamo), 0) as refrendos'),
                    DB::raw('COALESCE(SUM(desempenos_prestamo), 0) as desempenos'),
                    DB::raw('COALESCE(SUM(venta_prendas_prestamo + apartados_liquidados_prestamo), 0) as venta_total'),
                    DB::raw('COALESCE(SUM(venta_prendas_ventas), 0) as ventas_directas'),
                    DB::raw('COALESCE(SUM(apartados_liquidados_precio), 0) as apartados_liquidados'),
                    DB::raw('COALESCE(SUM((refrendos_intereses - refrendos_descuentos + refrendos_iva) + (desempenos_intereses - desempenos_descuentos + desempenos_iva)), 0) as intereses'),
                    DB::raw('COALESCE(SUM((venta_prendas_ventas + apartados_liquidados_precio) - (venta_prendas_prestamo + apartados_liquidados_prestamo)), 0) as utilidad_ventas'),
                    DB::raw('0 as gastos')
                )
                ->first();

            if ($mySondaSummary) {
                $tablero['Empeño']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->empenos_totales;
                $tablero['Refrendos']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->refrendos;
                $tablero['Desempeño']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->desempenos;
                $tablero['Ventas Directas']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->venta_total;
                $tablero['Apartados Liquidados']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->apartados_liquidados;
                $tablero['Ventas']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->venta_total;
                $tablero['Intereses']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->intereses;
                $tablero['Utilidad del Mes Por venta']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->utilidad_ventas;
                $tablero['Gastos del Mes']['MERCANCIA GENERAL']['avance'] += (float)$mySondaSummary->gastos;
            }

            // Desglose completo de MySonda para vista de tarjetas
            $mySondaDetail = (clone $mySondaQuery)
                ->select(
                    DB::raw('COALESCE(SUM(empenos_contratos), 0) as empenos_contratos'),
                    DB::raw('COALESCE(SUM(empenos_prendas), 0) as empenos_prendas'),
                    DB::raw('COALESCE(SUM(empenos_prestamo), 0) as empenos_prestamo'),

                    DB::raw('COALESCE(SUM(refrendos_contratos), 0) as refrendos_contratos'),
                    DB::raw('COALESCE(SUM(refrendos_prendas), 0) as refrendos_prendas'),
                    DB::raw('COALESCE(SUM(refrendos_prestamo), 0) as refrendos_prestamo'),
                    DB::raw('COALESCE(SUM(refrendos_pago_a_cuenta), 0) as refrendos_pago_a_cuenta'),
                    DB::raw('COALESCE(SUM(refrendos_intereses), 0) as refrendos_intereses'),
                    DB::raw('COALESCE(SUM(refrendos_descuentos), 0) as refrendos_descuentos'),
                    DB::raw('COALESCE(SUM(refrendos_iva), 0) as refrendos_iva'),
                    DB::raw('COALESCE(SUM(refrendos_total_cobrado), 0) as refrendos_total_cobrado'),

                    DB::raw('COALESCE(SUM(desempenos_contratos), 0) as desempenos_contratos'),
                    DB::raw('COALESCE(SUM(desempenos_prendas), 0) as desempenos_prendas'),
                    DB::raw('COALESCE(SUM(desempenos_prestamo), 0) as desempenos_prestamo'),
                    DB::raw('COALESCE(SUM(desempenos_intereses), 0) as desempenos_intereses'),
                    DB::raw('COALESCE(SUM(desempenos_descuentos), 0) as desempenos_descuentos'),
                    DB::raw('COALESCE(SUM(desempenos_iva), 0) as desempenos_iva'),
                    DB::raw('COALESCE(SUM(desempenos_total_cobrado), 0) as desempenos_total_cobrado'),

                    DB::raw('COALESCE(SUM(venta_prendas_prendas), 0) as venta_prendas_prendas'),
                    DB::raw('COALESCE(SUM(venta_prendas_peso), 0) as venta_prendas_peso'),
                    DB::raw('COALESCE(SUM(venta_prendas_prestamo), 0) as venta_prendas_prestamo'),
                    DB::raw('COALESCE(SUM(venta_prendas_avaluo), 0) as venta_prendas_avaluo'),
                    DB::raw('COALESCE(SUM(venta_prendas_ventas), 0) as venta_prendas_ventas'),
                    DB::raw('COALESCE(SUM(venta_prendas_prestamo + apartados_liquidados_prestamo), 0) as venta_total'),

                    DB::raw('COALESCE(SUM(apartados_liquidados_prendas), 0) as apartados_liquidados_prendas'),
                    DB::raw('COALESCE(SUM(apartados_liquidados_peso), 0) as apartados_liquidados_peso'),
                    DB::raw('COALESCE(SUM(apartados_liquidados_prestamo), 0) as apartados_liquidados_prestamo'),
                    DB::raw('COALESCE(SUM(apartados_liquidados_precio), 0) as apartados_liquidados_precio'),
                    DB::raw('COALESCE(SUM(apartados_liquidados_abonado), 0) as apartados_liquidados_abonado'),

                    DB::raw('COALESCE(SUM(abonos_apartados_prendas), 0) as abonos_apartados_prendas'),
                    DB::raw('COALESCE(SUM(abonos_apartados_peso), 0) as abonos_apartados_peso'),
                    DB::raw('COALESCE(SUM(abonos_apartados_prestamo), 0) as abonos_apartados_prestamo'),
                    DB::raw('COALESCE(SUM(abonos_apartados_precio), 0) as abonos_apartados_precio'),
                    DB::raw('COALESCE(SUM(abonos_apartados_rematados), 0) as abonos_apartados_rematados'),
                    DB::raw('COALESCE(SUM(abonos_apartados_abonos), 0) as abonos_apartados_abonos'),
                    DB::raw('COALESCE(SUM(abonos_apartados_total), 0) as abonos_apartados_total'),

                    DB::raw('COALESCE(SUM(reimpresiones_total), 0) as reimpresiones_total'),
                    DB::raw('COALESCE(SUM(comision_tarjetas_total), 0) as comision_tarjetas_total'),
                    DB::raw('COALESCE(SUM(saldo_inicial_boveda), 0) as saldo_inicial_boveda'),
                    DB::raw('COALESCE(SUM(aportacion_boveda), 0) as aportacion_boveda'),
                    DB::raw('COALESCE(SUM(retiros_boveda), 0) as retiros_boveda'),
                    DB::raw('COALESCE(SUM(dotacion_a_caja), 0) as dotacion_a_caja'),
                    DB::raw('COALESCE(SUM(retiros_de_caja), 0) as retiros_de_caja'),
                    DB::raw('COALESCE(SUM(cobros_tarjeta), 0) as cobros_tarjeta'),
                    DB::raw('COALESCE(SUM(demasias), 0) as demasias'),
                    DB::raw('COALESCE(SUM(compras_varios), 0) as compras_varios'),
                    DB::raw('COALESCE(SUM(entradas), 0) as entradas'),
                    DB::raw('COALESCE(SUM(salidas), 0) as salidas'),
                    DB::raw('COALESCE(SUM(ajuste_efectivo_caja), 0) as ajuste_efectivo_caja'),
                    DB::raw('COALESCE(SUM(efectivo_caja), 0) as efectivo_caja'),
                    DB::raw('COALESCE(SUM(efectivo_boveda), 0) as efectivo_boveda')
                )
                ->first();
        }

        // Calculate derived/calculated indicators for each category
        foreach ($categories as $cat) {
            // Interés + Util Vta
            $intereses = $tablero['Intereses'][$cat]['avance'];
            $utilVenta = $tablero['Utilidad del Mes Por venta'][$cat]['avance'];
            $tablero['Interés + Util Vta'][$cat]['avance'] = $intereses + $utilVenta;
            $tablero['Interés + Util Vta'][$cat]['operaciones'] = ($tablero['Intereses'][$cat]['operaciones'] ?? 0) + ($tablero['Utilidad del Mes Por venta'][$cat]['operaciones'] ?? 0);

            // Interés + Util Vta Meta
            $interesesMeta = $tablero['Intereses'][$cat]['meta'];
            $utilVentaMeta = $tablero['Utilidad del Mes Por venta'][$cat]['meta'];
            $tablero['Interés + Util Vta'][$cat]['meta'] = $interesesMeta + $utilVentaMeta;

            // Utilidad Neta del Mes por categoría (idéntico a C#, no resta gastos por categoría)
            $tablero['Utilidad Neta del Mes'][$cat]['avance'] = $intereses + $utilVenta;
            $tablero['Utilidad Neta del Mes'][$cat]['operaciones'] = $tablero['Interés + Util Vta'][$cat]['operaciones'] ?? 0;
            $tablero['Utilidad Neta del Mes'][$cat]['meta'] = $interesesMeta + $utilVentaMeta;
        }

        // Calcular totales exactos (_total) para cada indicador tal como en C#
        foreach ($standardIndicators as $ind) {
            $tMeta = 0;
            $tAvance = 0;
            $tOps = 0;
            foreach ($categories as $cat) {
                $tMeta += (float)($tablero[$ind][$cat]['meta'] ?? 0);
                $tAvance += (float)($tablero[$ind][$cat]['avance'] ?? 0);
                $tOps += (int)($tablero[$ind][$cat]['operaciones'] ?? 0);
            }

            if ($ind === 'Utilidad Neta del Mes') {
                $gastosTotalAvance = (float)($tablero['Gastos del Mes']['MERCANCIA GENERAL']['avance'] ?? 0);
                $gastosTotalMeta = (float)($tablero['Gastos del Mes']['MERCANCIA GENERAL']['meta'] ?? 0);
                $tablero[$ind]['_total'] = [
                    'meta' => $tMeta - $gastosTotalMeta,
                    'avance' => $tAvance - $gastosTotalAvance,
                    'operaciones' => $tOps
                ];
            } else {
                $tablero[$ind]['_total'] = [
                    'meta' => $tMeta,
                    'avance' => $tAvance,
                    'operaciones' => $tOps
                ];
            }
        }

        // Generar resumen de operaciones por fila (idéntico al C# "Operaciones")
        $operacionesFilas = [];
        foreach ($standardIndicators as $ind) {
            if ($ind === 'Ventas') {
                $operacionesFilas[$ind] = "{$vtasOpsTotal} vtas / {$liqOpsTotal} liq";
            } else {
                $operacionesFilas[$ind] = (string)($tablero[$ind]['_total']['operaciones'] ?? 0);
            }
        }

        return response()->json(array_merge($tablero, [
            '_mysonda_detail' => $mySondaDetail,
            '_operaciones_row' => $operacionesFilas
        ]));
    }

    private function matchMeta($dbIndicator, $standardIndicator)
    {
        $dbInd = $this->normalizeString($dbIndicator);
        $stdInd = $this->normalizeString($standardIndicator);
        
        if ($dbInd === $stdInd) {
            return true;
        }

        $cleanDb = preg_replace('/[()\-]/', ' ', $dbInd);
        $cleanDb = preg_replace('/\s+/', ' ', trim($cleanDb));
        
        $cleanStd = preg_replace('/[()\-]/', ' ', $stdInd);
        $cleanStd = preg_replace('/\s+/', ' ', trim($cleanStd));

        if ($cleanDb === $cleanStd) {
            return true;
        }

        if (strpos($stdInd, 'utilidad del mes por ven') === 0 && strpos($dbInd, 'utilidad del mes por ven') === 0) {
            return true;
        }

        $aliases = [
            'creditos vigentes' => ['credito vigente', 'creditos vigentes', 'vigentes'],
            'creditos colocados' => ['credito colocado', 'creditos colocados', 'colocacion', 'creditos colocados colocacion'],
            'pago credito/abono a creditos' => ['pago a creditos', 'pago credito', 'pago a creditos cobranza', 'cobranza', 'abono a creditos', 'pago credito abono a creditos'],
            'liquidacion de creditos' => ['liquidacion creditos', 'liquidacion de creditos', 'liquidacion de credito'],
            'utilidad del credito' => ['utilidad credito', 'utilidad del credito'],
            'credito vencido mensual - prestamo' => ['credito vencido mensual prestamo'],
            'credito vencido mensual - cantidad pagada' => ['credito vencido mensual cobrado', 'credito vencido mensual cantidad pagada'],
            'credito vencido mensual - adeudo' => ['credito vencido mensual adeudo'],
            'credito vencido global - prestamo' => ['credito vencido global prestamo'],
            'credito vencido global - cantidad pagada' => ['credito vencido global cobrado', 'credito vencido global cantidad pagada'],
            'credito vencido global - adeudo' => ['credito vencido global adeudo'],
            'garantias - ventas' => ['garantias de ventas', 'garantias ventas'],
            'garantias - apartados liquidados' => ['garantias de apartados liquidados', 'garantias apartados liquidados'],
            'garantias - enganche credito' => ['garantias de enganche credito', 'garantias de enganche de credito', 'garantias enganche credito'],
        ];

        if (isset($aliases[$stdInd])) {
            foreach ($aliases[$stdInd] as $alias) {
                if ($cleanDb === $alias || strpos($cleanDb, $alias) !== false) {
                    return true;
                }
            }
        }

        if (strpos($stdInd, ' - ') !== false) {
            $partes = explode(' - ', $stdInd, 2);
            $izquierda = trim($partes[0]);
            $derecha = trim($partes[1]);
            
            if (strpos($stdInd, 'garantias') === 0) {
                if ($dbInd === 'garantias - ' . $derecha || $dbInd === $derecha || strpos($dbInd, $derecha) !== false) {
                    return true;
                }
            } else {
                if ($dbInd === $izquierda || strpos($dbInd, $izquierda) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    private function normalizeCategory($category)
    {
        $catNorm = strtoupper($this->normalizeString($category));
        if (strpos($catNorm, 'MERCANCIA') !== false || strpos($catNorm, 'VARIOS') !== false) {
            return 'MERCANCIA GENERAL';
        }
        return $catNorm;
    }

    private function normalizeString($string)
    {
        if (empty($string)) return '';
        $string = trim($string);
        $unwanted = [
            'Á'=>'A', 'À'=>'A', 'Â'=>'A', 'Ä'=>'A', 'Ã'=>'A', 'Å'=>'A', 'á'=>'a', 'à'=>'a', 'â'=>'a', 'ä'=>'a', 'ã'=>'a', 'å'=>'a',
            'É'=>'E', 'È'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
            'Í'=>'I', 'Ì'=>'I', 'Î'=>'I', 'Ï'=>'I', 'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
            'Ó'=>'O', 'Ò'=>'O', 'Ô'=>'O', 'Ö'=>'O', 'Õ'=>'O', 'ó'=>'o', 'ò'=>'o', 'ô'=>'o', 'ö'=>'o', 'õ'=>'o',
            'Ú'=>'U', 'Ù'=>'U', 'Û'=>'U', 'Ü'=>'U', 'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
            'Ñ'=>'N', 'ñ'=>'n', 'Ç'=>'C', 'ç'=>'c'
        ];
        return strtolower(strtr($string, $unwanted));
    }
}
