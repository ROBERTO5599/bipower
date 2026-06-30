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

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
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
                    'avance' => 0.0
                ];
            }
        }

        $baseConfig = Config::get('database.connections.mysql');

        foreach ($sucursalesSeleccionadas as $sucursal) {
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
                    $dbCat = $metaRow->categoria;
                    $metaVal = (float)$metaRow->total_meta;

                    foreach ($standardIndicators as $stdInd) {
                        if ($this->matchMeta($dbInd, $stdInd)) {
                            if (in_array($dbCat, $categories)) {
                                $tablero[$stdInd][$dbCat]['meta'] += $metaVal;
                            }
                        }
                    }
                }

                // 2. Fetch Avance Categorizado (Movimientos)
                $movsQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(avance) AS avance
                    FROM (
                        -- Ventas Directas
                        SELECT 'Ventas' AS indicador,
                            CASE
                                WHEN ve.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8  AND 26  THEN 'ORO'
                                WHEN ve.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN ve.cod_tipo_prenda = 1 THEN 'ORO' -- fallback a oro
                                WHEN ve.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN ve.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'MERCANCIA GENERAL'
                            END AS categoria,
                            CASE
                                WHEN ve.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                                ELSE 0
                            END AS avance
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

                        -- Ventas Apartados Liquidados
                        SELECT 'Ventas' AS indicador,
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8  AND 26  THEN 'ORO'
                                WHEN ap.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN ap.cod_tipo_prenda = 1 THEN 'ORO' -- fallback a oro
                                WHEN ap.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN ap.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'MERCANCIA GENERAL'
                            END AS categoria,
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                                WHEN ap.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                                WHEN ap.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                                ELSE 0
                            END AS avance
                        FROM apartado_pagos apg
                        INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
                        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                        INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                        LEFT JOIN alhajas al ON al.cod_alhaja = da.cod_prenda AND ap.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_auto   = da.cod_prenda AND ap.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_varios = da.cod_prenda AND ap.cod_tipo_prenda = 3
                        WHERE apg.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento = 12
                          AND apg.f_pago BETWEEN :f1_2 AND :f2_2

                        UNION ALL

                        -- Empeño
                        SELECT 'Empeño' AS indicador,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 1 THEN 'ORO' -- fallback
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'MERCANCIA GENERAL'
                            END AS categoria,
                            COALESCE(
                                CASE
                                    WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
                                    WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
                                    WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
                                END
                            , 0) AS avance
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE con.f_cancelacion IS NULL                             
                          AND mo.cod_tipo_movimiento = 1
                          AND mo.f_alta BETWEEN :f1_3 AND :f2_3

                        UNION ALL

                        -- Refrendos (Optimizado sin UNION correlacionado)
                        SELECT 'Refrendos' AS indicador,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 1 THEN 'ORO' -- fallback
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'MERCANCIA GENERAL'
                            END AS categoria,
                            COALESCE(con.prestamo, 0) AS avance
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1 AND al.id = 1
                        WHERE mo.f_alta BETWEEN :f1_4 AND :f2_4
                          AND mo.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento IN (2, 3)

                        UNION ALL

                        -- Desempeño
                        SELECT 'Desempeño' AS indicador,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'MERCANCIA GENERAL'
                            END AS categoria,
                            COALESCE(CASE
                                WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
                                WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
                                WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
                            END, 0) AS avance
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato   = mo.cod_contrato
                        LEFT  JOIN alhajas al   ON al.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT  JOIN autos   au   ON au.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT  JOIN varios  va   ON va.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE con.f_cancelacion IS NULL
                          AND mo.cod_tipo_movimiento = 4
                          AND mo.f_cancela IS NULL
                          AND mo.f_alta BETWEEN :f1_5 AND :f2_5

                        UNION ALL

                        -- Desempeño (Abono a Capital tipo 3)
                        SELECT 'Desempeño' AS indicador,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'MERCANCIA GENERAL'
                            END AS categoria,
                            COALESCE(
                                (SELECT ab.abono FROM contratos ab 
                                 WHERE ab.cod_contrato = con.cod_anterior 
                                 ORDER BY ab.f_contrato DESC LIMIT 1)
                            , 0) AS avance
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato   = mo.cod_contrato
                        LEFT  JOIN alhajas al   ON al.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 1 AND al.id = 1
                        LEFT  JOIN autos   au   ON au.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 2 AND au.id = 1
                        LEFT  JOIN varios  va   ON va.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 3 AND va.id = 1
                        WHERE con.f_cancelacion IS NULL
                          AND mo.cod_tipo_movimiento = 3
                          AND mo.f_cancela IS NULL
                          AND mo.f_alta BETWEEN :f1_6 AND :f2_6
                    ) AS t
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
                    if (isset($tablero[$ind]) && isset($tablero[$ind][$cat])) {
                        $tablero[$ind][$cat]['avance'] += $val;
                    }
                }

                // 3. Gastos
                $gastosQuery = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(g.solicitado), 0) AS TotalGastos
                    FROM gastos g
                    WHERE g.activo = 1
                      AND g.cod_estatus = 2
                      AND g.f_solicitado BETWEEN :f1 AND :f2
                ", ['f1' => $fechaInicio, 'f2' => $fechaFin]);
                
                if ($gastosQuery) {
                    $tablero['Gastos del Mes']['MERCANCIA GENERAL']['avance'] += (float)$gastosQuery->TotalGastos;
                }

                // 4. Intereses
                $interesesQuery = DB::connection($connectionName)->select("
                    SELECT categoria, SUM(interesCobrado) AS avance
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
                            END AS interesCobrado
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
                            END AS interesCobrado
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
                            END AS interesCobrado
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
                    if (isset($tablero['Intereses'][$cat])) {
                        $tablero['Intereses'][$cat]['avance'] += $val;
                    }
                }

                // 5. Utilidad Ventas
                $utilVentasQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(avance) AS avance
                    FROM (
                        SELECT 'Utilidad del Mes Por venta' AS indicador,
                            CASE
                                WHEN ve.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8  AND 26  THEN 'ORO'
                                WHEN ve.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN ve.cod_tipo_prenda = 1 THEN 'ORO'
                                WHEN ve.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN ve.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                            END AS categoria,
                            COALESCE(dv.venta10, 0) - CASE
                                WHEN ve.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                                ELSE 0
                            END AS avance
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
                            END AS categoria,
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 THEN COALESCE(al.precio, 0) - COALESCE(al.prestamo, 0)
                                WHEN ap.cod_tipo_prenda = 2 THEN COALESCE(au.precio, 0) - COALESCE(au.prestamo, 0)
                                WHEN ap.cod_tipo_prenda = 3 THEN COALESCE(va.precio, 0) - COALESCE(va.prestamo, 0)
                                ELSE 0
                            END AS avance
                        FROM apartado_pagos apg
                        INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
                        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                        INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                        LEFT JOIN alhajas al ON al.cod_alhaja = da.cod_prenda AND ap.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_auto   = da.cod_prenda AND ap.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_varios = da.cod_prenda AND ap.cod_tipo_prenda = 3
                        WHERE apg.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento = 12
                          AND apg.f_pago BETWEEN :f1_2 AND :f2_2
                    ) t
                    GROUP BY indicador, categoria
                ", [
                    'f1_1' => $fechaInicio, 'f2_1' => $fechaFin,
                    'f1_2' => $fechaInicio, 'f2_2' => $fechaFin,
                ]);

                foreach ($utilVentasQuery as $row) {
                    $ind = $row->indicador;
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    if (isset($tablero[$ind]) && isset($tablero[$ind][$cat])) {
                        $tablero[$ind][$cat]['avance'] += $val;
                    }
                }

                // 6. Inventario Depositaria (Optimized)
                $inventarioQuery = DB::connection($connectionName)->select("
                    SELECT
                        'INVENTARIO' AS indicador,
                        CASE
                            WHEN tipo = 1 AND kilataje BETWEEN 8   AND 26  THEN 'ORO'
                            WHEN tipo = 1 AND kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                            WHEN tipo = 2                                  THEN 'AUTOS'
                            WHEN tipo = 3                                  THEN 'MERCANCIA GENERAL'
                            ELSE 'MERCANCIA GENERAL'
                        END AS categoria,
                        SUM(prestamo) AS avance
                    FROM (
                        -- ALHAJAS
                        SELECT 1 AS tipo, a.kilataje, a.prestamo
                        FROM alhajas a
                        WHERE a.cod_contrato IN (
                            SELECT c.cod_seguimiento FROM contratos c
                            WHERE c.f_contrato <= :f2_1 AND c.f_cancelacion IS NULL
                        )
                        AND (
                            a.cod_estatus_prenda = 1
                            OR (a.cod_estatus_prenda IN (3, 4, 9, 10) AND a.p_venta > :f2_2)
                            OR (a.cod_estatus_prenda = 6 AND a.cod_contrato IN (
                                    SELECT c2.cod_seguimiento FROM contratos c2
                                    INNER JOIN movimientos m ON c2.contrato = m.contrato
                                    WHERE m.cod_tipo_movimiento = 4 AND m.f_alta > :f2_3
                                ))
                        )

                        UNION ALL

                        -- VARIOS
                        SELECT 3 AS tipo, 0 AS kilataje, v.prestamo
                        FROM varios v
                        WHERE v.cod_contrato IN (
                            SELECT c.cod_seguimiento FROM contratos c
                            WHERE c.f_contrato <= :f2_4 AND c.f_cancelacion IS NULL
                        )
                        AND (
                            v.cod_estatus_prenda = 1
                            OR (v.cod_estatus_prenda IN (3, 4, 9, 10) AND v.p_venta > :f2_5)
                            OR (v.cod_estatus_prenda = 6 AND v.cod_contrato IN (
                                    SELECT c2.cod_seguimiento FROM contratos c2
                                    INNER JOIN movimientos m ON c2.contrato = m.contrato
                                    WHERE m.cod_tipo_movimiento = 4 AND m.f_alta > :f2_6
                                ))
                        )

                        UNION ALL

                        -- AUTOS
                        SELECT 2 AS tipo, 0 AS kilataje, au.prestamo
                        FROM autos au
                        WHERE au.cod_contrato IN (
                            SELECT c.cod_seguimiento FROM contratos c
                            WHERE c.f_contrato <= :f2_7 AND c.f_cancelacion IS NULL
                        )
                        AND (
                            au.cod_estatus_prenda = 1
                            OR (au.cod_estatus_prenda IN (3, 4, 9, 10) AND au.p_venta > :f2_8)
                            OR (au.cod_estatus_prenda = 6 AND au.cod_contrato IN (
                                    SELECT c2.cod_seguimiento FROM contratos c2
                                    INNER JOIN movimientos m ON c2.contrato = m.contrato
                                    WHERE m.cod_tipo_movimiento = 4 AND m.f_alta > :f2_9
                                ))
                        )
                    ) AS t
                    GROUP BY categoria
                ", [
                    'f2_1' => $fechaFin, 'f2_2' => $fechaFin, 'f2_3' => $fechaFin,
                    'f2_4' => $fechaFin, 'f2_5' => $fechaFin, 'f2_6' => $fechaFin,
                    'f2_7' => $fechaFin, 'f2_8' => $fechaFin, 'f2_9' => $fechaFin,
                ]);

                foreach ($inventarioQuery as $row) {
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    if (isset($tablero['INVENTARIO'][$cat])) {
                        $tablero['INVENTARIO'][$cat]['avance'] += $val;
                    }
                }

                // 7. Bazar
                $bazarQuery = DB::connection($connectionName)->select("
                    SELECT
                        'Bazar' AS indicador,
                        CASE
                            WHEN tipo = 1 AND kilataje BETWEEN 8   AND 26  THEN 'ORO'
                            WHEN tipo = 1 AND kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                            WHEN tipo = 2                                  THEN 'AUTOS'
                            WHEN tipo = 3                                  THEN 'MERCANCIA GENERAL'
                            ELSE 'MERCANCIA GENERAL'
                        END AS categoria,
                        SUM(prestamo) AS avance
                    FROM (
                        -- ALHAJAS
                        SELECT 1 AS tipo, a.kilataje, a.prestamo
                        FROM alhajas a
                        WHERE a.p_venta IS NOT NULL AND a.p_venta <= :f2_1
                          AND (
                              a.cod_estatus_prenda = 9
                              OR (a.cod_estatus_prenda = 3 AND a.cod_alhaja IN (
                                  SELECT dv.cod_prenda FROM detalle_venta dv
                                  INNER JOIN ventas v ON v.cod_venta = dv.cod_venta
                                  WHERE v.cod_tipo_prenda = 1 AND v.f_venta > :f2_2
                              ))
                              OR (a.cod_estatus_prenda = 4 AND a.cod_alhaja IN (
                                  SELECT da.cod_prenda FROM detalle_apartado da
                                  INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado
                                  WHERE ap.cod_tipo_prenda = 1 AND ap.f_apartado > :f2_3
                              ))
                              OR (a.cod_estatus_prenda = 10 AND a.cod_alhaja IN (
                                  SELECT dsi.cod_prenda FROM detalle_salida_inventario dsi
                                  INNER JOIN salidas_inventario si ON si.cod_salida = dsi.cod_salida
                                  WHERE si.cod_tipo_prenda = 1 AND si.f_salida > :f2_4
                              ))
                          )

                        UNION ALL

                        -- VARIOS
                        SELECT 3 AS tipo, 0 AS kilataje, v.prestamo
                        FROM varios v
                        WHERE v.p_venta IS NOT NULL AND v.p_venta <= :f2_5
                          AND (
                              v.cod_estatus_prenda = 9
                              OR (v.cod_estatus_prenda = 3 AND v.cod_varios IN (
                                  SELECT dv.cod_prenda FROM detalle_venta dv
                                  INNER JOIN ventas v2 ON v2.cod_venta = dv.cod_venta
                                  WHERE v2.cod_tipo_prenda = 3 AND v2.f_venta > :f2_6
                              ))
                              OR (v.cod_estatus_prenda = 4 AND v.cod_varios IN (
                                  SELECT da.cod_prenda FROM detalle_apartado da
                                  INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado
                                  WHERE ap.cod_tipo_prenda = 3 AND ap.f_apartado > :f2_7
                              ))
                              OR (v.cod_estatus_prenda = 10 AND v.cod_varios IN (
                                  SELECT dsi.cod_prenda FROM detalle_salida_inventario dsi
                                  INNER JOIN salidas_inventario si ON si.cod_salida = dsi.cod_salida
                                  WHERE si.cod_tipo_prenda = 3 AND si.f_salida > :f2_8
                              ))
                              OR (v.cod_estatus_prenda IN (11, 12) AND v.cod_varios IN (
                                  SELECT c.cod_varios FROM creditos c
                                  WHERE c.f_solicitud > :f2_9
                              ))
                          )

                        UNION ALL

                        -- AUTOS
                        SELECT 2 AS tipo, 0 AS kilataje, au.prestamo
                        FROM autos au
                        WHERE au.p_venta IS NOT NULL AND au.p_venta <= :f2_10
                          AND (
                              au.cod_estatus_prenda = 9
                              OR (au.cod_estatus_prenda = 3 AND au.cod_auto IN (
                                  SELECT dv.cod_prenda FROM detalle_venta dv
                                  INNER JOIN ventas v ON v.cod_venta = dv.cod_venta
                                  WHERE v.cod_tipo_prenda = 2 AND v.f_venta > :f2_11
                              ))
                              OR (au.cod_estatus_prenda = 4 AND au.cod_auto IN (
                                  SELECT da.cod_prenda FROM detalle_apartado da
                                  INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado
                                  WHERE ap.cod_tipo_prenda = 2 AND ap.f_apartado > :f2_12
                              ))
                              OR (au.cod_estatus_prenda = 10 AND au.cod_auto IN (
                                  SELECT dsi.cod_prenda FROM detalle_salida_inventario dsi
                                  INNER JOIN salidas_inventario si ON si.cod_salida = dsi.cod_salida
                                  WHERE si.cod_tipo_prenda = 2 AND si.f_salida > :f2_13
                              ))
                          )
                    ) AS t
                    GROUP BY categoria
                ", [
                    'f2_1' => $fechaFin, 'f2_2' => $fechaFin, 'f2_3' => $fechaFin, 'f2_4' => $fechaFin,
                    'f2_5' => $fechaFin, 'f2_6' => $fechaFin, 'f2_7' => $fechaFin, 'f2_8' => $fechaFin, 'f2_9' => $fechaFin,
                    'f2_10' => $fechaFin, 'f2_11' => $fechaFin, 'f2_12' => $fechaFin, 'f2_13' => $fechaFin,
                ]);

                foreach ($bazarQuery as $row) {
                    $cat = $row->categoria;
                    $val = (float)$row->avance;
                    if (isset($tablero['Bazar'][$cat])) {
                        $tablero['Bazar'][$cat]['avance'] += $val;
                    }
                }

                // 8. Remate
                $remateQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(prestamo) AS avance
                    FROM (
                        SELECT 'Remate' AS indicador,
                            CASE
                                WHEN a.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN a.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                ELSE 'ORO'
                            END AS categoria,
                            a.prestamo
                        FROM alhajas a
                        WHERE a.p_venta IS NOT NULL AND a.p_venta BETWEEN :f1_1 AND :f2_1

                        UNION ALL

                        SELECT 'Remate' AS indicador,
                            'MERCANCIA GENERAL' AS categoria,
                            v.prestamo
                        FROM varios v
                        WHERE v.p_venta IS NOT NULL AND v.p_venta BETWEEN :f1_2 AND :f2_2

                        UNION ALL

                        SELECT 'Remate' AS indicador,
                            'AUTOS' AS categoria,
                            au.prestamo
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
                    if (isset($tablero['Remate'][$cat])) {
                        $tablero['Remate'][$cat]['avance'] += $val;
                    }
                }

                // 9. Créditos Tablero
                $creditosQuery = DB::connection($connectionName)->select("
                    SELECT indicador, categoria, SUM(avance) AS avance
                    FROM (
                        -- CRÉDITOS VIGENTES
                        SELECT 
                            'Créditos Vigentes' AS indicador,
                            'MERCANCIA GENERAL' AS categoria,
                            COALESCE(SUM(va.prestamo), 0) AS avance
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 2
                          AND cre.f_solicitud < :f2_1

                        UNION ALL

                        -- COLOCADOS
                        SELECT 
                            'Créditos Colocados' AS indicador,
                            'MERCANCIA GENERAL' AS categoria,
                            COALESCE(SUM(art.prestamo), 0)
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
                            COALESCE(SUM(mo.monto10), 0)
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
                            COALESCE(SUM(art.prestamo), 0)
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
                            COALESCE(SUM(op.monto_total - art.prestamo), 0)
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
                            COALESCE(SUM(va.prestamo), 0)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud < :f2_6

                        UNION ALL

                        -- VENCIDOS GLOBAL (solo Pagado)
                        SELECT 
                            'Crédito Vencido Global - Cantidad Pagada',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(cre.pagado), 0)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud < :f2_7

                        UNION ALL

                        -- VENCIDOS GLOBAL (solo Adeudo)
                        SELECT 
                            'Crédito Vencido Global - Adeudo',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(cre.adeudo), 0)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud < :f2_8

                        UNION ALL

                        -- VENCIDOS MENSUAL (solo préstamo)
                        SELECT 
                            'Crédito Vencido Mensual - Préstamo',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(va.prestamo), 0)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud BETWEEN :f1_9 AND :f2_9

                        UNION ALL

                        -- VENCIDOS MENSUAL (solo Pagado)
                        SELECT 
                            'Crédito Vencido Mensual - Cantidad Pagada',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(cre.pagado), 0)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud BETWEEN :f1_10 AND :f2_10

                        UNION ALL

                        -- VENCIDOS MENSUAL (solo Adeudo)
                        SELECT 
                            'Crédito Vencido Mensual - Adeudo',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(cre.adeudo), 0)
                        FROM creditos cre
                        INNER JOIN varios va ON va.cod_varios = cre.cod_varios
                        WHERE cre.cod_estatus = 7
                          AND cre.f_solicitud BETWEEN :f1_11 AND :f2_11

                        UNION ALL

                        -- DEVOLUCIONES
                        SELECT 
                            'Devoluciones',
                            'MERCANCIA GENERAL',
                            COALESCE(SUM(va.prestamo), 0)
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
                    if (isset($tablero[$ind]) && isset($tablero[$ind][$cat])) {
                        $tablero[$ind][$cat]['avance'] += $val;
                    }
                }

                // 10. Garantías
                $garantiasQuery = DB::connection($connectionName)->select("
                    SELECT indicador, SUM(monto_garantia) AS avance
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
                    if (isset($tablero[$ind]) && isset($tablero[$ind]['MERCANCIA GENERAL'])) {
                        $tablero[$ind]['MERCANCIA GENERAL']['avance'] += $val;
                    }
                }

            } catch (\Exception $e) {
                Log::error("Error TableroControl en sucursal {$sucursal->nombre}: " . $e->getMessage());
                continue;
            }
        }

        // 11. Calculate derived/calculated indicators for each category
        foreach ($categories as $cat) {
            // Interés + Util Vta
            $intereses = $tablero['Intereses'][$cat]['avance'];
            $utilVenta = $tablero['Utilidad del Mes Por venta'][$cat]['avance'];
            $tablero['Interés + Util Vta'][$cat]['avance'] = $intereses + $utilVenta;

            // Interés + Util Vta Meta
            $interesesMeta = $tablero['Intereses'][$cat]['meta'];
            $utilVentaMeta = $tablero['Utilidad del Mes Por venta'][$cat]['meta'];
            $tablero['Interés + Util Vta'][$cat]['meta'] = $interesesMeta + $utilVentaMeta;

            // Utilidad Neta del Mes
            $gastos = $tablero['Gastos del Mes'][$cat]['avance'];
            $tablero['Utilidad Neta del Mes'][$cat]['avance'] = ($intereses + $utilVenta) - $gastos;

            // Utilidad Neta del Mes Meta
            $gastosMeta = $tablero['Gastos del Mes'][$cat]['meta'];
            $tablero['Utilidad Neta del Mes'][$cat]['meta'] = ($interesesMeta + $utilVentaMeta) - $gastosMeta;
        }

        return response()->json($tablero);
    }

    private function matchMeta($dbIndicator, $standardIndicator)
    {
        $dbInd = trim(strtolower($dbIndicator));
        $stdInd = trim(strtolower($standardIndicator));
        
        if ($dbInd === $stdInd) {
            return true;
        }
        
        if (strpos($stdInd, ' - ') !== false) {
            $partes = explode(' - ', $stdInd, 2);
            $izquierda = trim($partes[0]);
            $derecha = trim($partes[1]);
            
            if (strpos($stdInd, 'garantías') === 0) {
                if ($dbInd === 'garantías - ' . $derecha || $dbInd === $derecha) {
                    return true;
                }
            } else {
                if ($dbInd === $izquierda) {
                    return true;
                }
            }
        }
        return false;
    }
}
