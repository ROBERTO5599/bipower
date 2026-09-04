<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class OperacionesCarteraController extends Controller
{
    public function index(Request $request)
    {
        // Filtrar solo sucursales que existen
        $idsQueFuncionan = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19];
        $sucursales = Sucursal::whereNotNull('id_valora_mas')
            ->whereIn('id_valora_mas', $idsQueFuncionan)
            ->get();
        
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();

        return view('operaciones-cartera.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        ini_set('max_execution_time', 120);
        
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFinQuery = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        
        // Fecha de corte al cierre del mes anterior para calcular el inventario de depositaria base
        $corteMesAnterior = \Carbon\Carbon::parse($request->input('fecha_inicio', now()->startOfMonth()->toDateString()))
            ->subMonth()
            ->endOfMonth()
            ->toDateString() . ' 23:59:59';
        
        $sucursalId = $request->input('sucursal_id');
        
        // Filtrar solo sucursales que existen
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

        // Variables globales para Respuesta
        $data = [
            'empenos' => [
                'total_contratos' => 0,
                'monto_total' => 0,
                'oro' => ['contratos' => 0, 'monto' => 0],
                'varios' => ['contratos' => 0, 'monto' => 0],
                'auto' => ['contratos' => 0, 'monto' => 0],
                'avaluo_total' => 0,
                'categorias' => [
                    'AUTOS' => ['contratos' => 0, 'monto' => 0],
                    'ORO' => ['contratos' => 0, 'monto' => 0],
                    'PLATA' => ['contratos' => 0, 'monto' => 0],
                    'OTROS METALES' => ['contratos' => 0, 'monto' => 0],
                    'MERCANCIA GENERAL' => ['contratos' => 0, 'monto' => 0],
                    'SIN CATEGORIA' => ['contratos' => 0, 'monto' => 0]
                ]
            ],
            'refrendos' => ['total' => 0, 'monto' => 0], // REFRENDOS NORMALES (comentado en el código, se mantiene por si acaso)
            'refrendos_extemporaneos' => ['total' => 0, 'monto' => 0], // NUEVO: REFRENDOS EXTEMPORÁNEOS
            'desempenos' => [
                'total' => 0,
                'monto' => 0,
                'categorias' => [
                    'AUTOS' => ['contratos' => 0, 'monto' => 0],
                    'ORO' => ['contratos' => 0, 'monto' => 0],
                    'PLATA' => ['contratos' => 0, 'monto' => 0],
                    'OTROS METALES' => ['contratos' => 0, 'monto' => 0],
                    'MERCANCIA GENERAL' => ['contratos' => 0, 'monto' => 0],
                    'SIN CATEGORIA' => ['contratos' => 0, 'monto' => 0]
                ]
            ],
            'abonos_capital' => [
                'total' => 0,
                'monto' => 0,
                'categorias' => [
                    'AUTOS' => ['contratos' => 0, 'monto' => 0],
                    'ORO' => ['contratos' => 0, 'monto' => 0],
                    'PLATA' => ['contratos' => 0, 'monto' => 0],
                    'OTROS METALES' => ['contratos' => 0, 'monto' => 0],
                    'MERCANCIA GENERAL' => ['contratos' => 0, 'monto' => 0],
                    'SIN CATEGORIA' => ['contratos' => 0, 'monto' => 0]
                ]
            ],
            'cartera' => [
                'vigente' => 0,
                'vencida' => 0,
                'oro' => 0,
                'varios' => 0,
                'auto' => 0,
            ],
            'tiempos' => [
                'dias_empeno_desempeno' => 0,
                'total_desempenos_con_dias' => 0
            ],
            'intereses' => [
                'cobrados' => 0,
                'refrendo_desempeno' => 0,
                'depositaria_mes_anterior' => 0,
                'tasa_real_mensual_pct' => 0,
                'tasa_real_anual_pct' => 0,
            ],
            'mora' => [
                '0_30' => 0,
                '31_60' => 0,
                '61_90' => 0,
                'mas_90' => 0
            ],
            'mora_detallado' => [],
            'rankings' => [
                'articulos_empenados' => [],
                'articulos_desempenados' => []
            ]
        ];

        // Recolectores para promedios y rankings globales
        $rankingsEmpenados = [];
        $rankingsDesempenados = [];
        $rankingsRefrendados = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi_' . $sucursal->id_valora_mas;

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                // ============================================
                // 1. EMPEÑOS
                // ============================================
                // Primero obtener el total general de empeños y desglose por categorías
                $empenosRes = DB::connection($connectionName)->select("
                    SELECT 
                        categoria,
                        COUNT(DISTINCT contrato) AS contratos,
                        COALESCE(SUM(avance), 0) AS monto,
                        COALESCE(SUM(avaluo), 0) AS avaluo
                    FROM (
                        SELECT 
                            con.contrato,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 1 THEN 'OTROS METALES'
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
                            COALESCE(
                                CASE
                                    WHEN con.cod_tipo_prenda = 1 THEN al.precio
                                    WHEN con.cod_tipo_prenda = 2 THEN au.precio
                                    WHEN con.cod_tipo_prenda = 3 THEN va.precio
                                END
                            , 0) AS avaluo
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE con.f_cancelacion IS NULL                             
                          AND mo.cod_tipo_movimiento = 1
                          AND mo.f_cancela IS NULL
                          AND mo.f_alta BETWEEN :fIni AND :fFin
                    ) AS t
                    WHERE avance > 0
                    GROUP BY categoria
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);


                foreach ($empenosRes as $row) {
                    $cat = $row->categoria;
                    $monto = (float)$row->monto;
                    $contratos = (int)$row->contratos;
                    $avaluo = (float)$row->avaluo;

                    // Acumuladores globales
                    $data['empenos']['monto_total'] += $monto;
                    $data['empenos']['total_contratos'] += $contratos;
                    $data['empenos']['avaluo_total'] += $avaluo;

                    $data['empenos']['categorias'][$cat]['contratos'] += $contratos;
                    $data['empenos']['categorias'][$cat]['monto'] += $monto;

                    // Mapeo a categorías tradicionales para compatibilidad
                    if (in_array($cat, ['ORO', 'PLATA', 'OTROS METALES'])) {
                        $data['empenos']['oro']['contratos'] += $contratos;
                        $data['empenos']['oro']['monto'] += $monto;
                    } elseif ($cat === 'AUTOS') {
                        $data['empenos']['auto']['contratos'] += $contratos;
                        $data['empenos']['auto']['monto'] += $monto;
                    } elseif (in_array($cat, ['MERCANCIA GENERAL', 'SIN CATEGORIA'])) {
                        $data['empenos']['varios']['contratos'] += $contratos;
                        $data['empenos']['varios']['monto'] += $monto;
                    }
                }

                // ============================================
                // 2. REFRENDOS NORMALES (Movimientos 2) - COMENTADO
                // Si se necesita en el futuro, descomentar este bloque
                // ============================================
                /*
                $refrendosRes = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COUNT(*) AS total,
                        COALESCE(SUM(total), 0) AS monto
                    FROM (
                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) 
                                 FROM alhajas 
                                 WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 1 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1

                        UNION ALL

                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) 
                                 FROM autos 
                                 WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 2 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2

                        UNION ALL

                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) 
                                 FROM varios 
                                 WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 3 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni3 AND :fFin3
                    ) AS t
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFinQuery
                ]);
                
                $data['refrendos']['total'] += (int)($refrendosRes->total ?? 0);
                $data['refrendos']['monto'] += (float)($refrendosRes->monto ?? 0);
                */

                // ============================================
                // 2.1 REFRENDOS EXTEMPORÁNEOS (con p_venta <= fecha_refrendo)
                // ============================================
                $refrendosExtemporaneos = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COUNT(*) AS total,
                        COALESCE(SUM(total), 0) AS monto
                    FROM (
                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) 
                                 FROM alhajas 
                                 WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 1 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1
                          AND al.p_venta IS NOT NULL
                          AND al.p_venta <= mo.f_alta

                        UNION ALL

                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) 
                                 FROM autos 
                                 WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 2 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2
                          AND au.p_venta IS NOT NULL
                          AND au.p_venta <= mo.f_alta

                        UNION ALL

                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) 
                                 FROM varios 
                                 WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 3 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni3 AND :fFin3
                          AND va.p_venta IS NOT NULL
                          AND va.p_venta <= mo.f_alta
                    ) AS t
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFinQuery
                ]);
                
                $data['refrendos_extemporaneos']['total'] += (int)($refrendosExtemporaneos->total ?? 0);
                $data['refrendos_extemporaneos']['monto'] += (float)($refrendosExtemporaneos->monto ?? 0);

                // ============================================
                // 2.2 ABONOS A CAPITAL (Movimiento 3)
                // ============================================
                $abonosCapitalRes = DB::connection($connectionName)->select("
                    SELECT 
                        categoria,
                        COUNT(DISTINCT contrato) AS contratos,
                        COALESCE(SUM(abono_capital), 0) AS monto
                    FROM (
                        SELECT 
                            con.contrato,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 1 THEN 'OTROS METALES'
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            COALESCE((SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior ORDER BY f_contrato DESC LIMIT 1), 0) AS abono_capital
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato   = mo.cod_contrato
                        LEFT  JOIN alhajas al   ON al.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 1 AND al.id = 1
                        LEFT  JOIN autos   au   ON au.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 2 AND au.id = 1
                        LEFT  JOIN varios  va   ON va.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 3 AND va.id = 1
                        WHERE con.f_cancelacion IS NULL
                          AND mo.cod_tipo_movimiento = 3
                          AND mo.f_cancela IS NULL
                          AND mo.f_alta BETWEEN :fIni AND :fFin
                    ) AS t
                    WHERE abono_capital > 0
                    GROUP BY categoria
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);
                
                foreach ($abonosCapitalRes as $row) {
                    $cat = $row->categoria;
                    $monto = (float)$row->monto;
                    $contratos = (int)$row->contratos;

                    $data['abonos_capital']['total'] += $contratos;
                    $data['abonos_capital']['monto'] += $monto;
                    $data['abonos_capital']['categorias'][$cat]['contratos'] += $contratos;
                    $data['abonos_capital']['categorias'][$cat]['monto'] += $monto;
                }

                // ============================================
                // 3. DESEMPEÑOS (Movimiento 4)
                // ============================================
                $desempenosRes = DB::connection($connectionName)->select("
                    SELECT 
                        categoria,
                        COUNT(DISTINCT contrato) AS contratos,
                        COALESCE(SUM(prestamo_desempenio), 0) AS monto
                    FROM (
                        SELECT 
                            con.contrato,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 1 THEN 'OTROS METALES'
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            COALESCE(CASE
                                WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
                                WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
                                WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
                            END, 0) AS prestamo_desempenio
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato   = mo.cod_contrato
                        LEFT  JOIN alhajas al   ON al.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT  JOIN autos   au   ON au.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT  JOIN varios  va   ON va.cod_contrato     = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE con.f_cancelacion IS NULL
                          AND mo.cod_tipo_movimiento = 4
                          AND mo.f_cancela IS NULL
                          AND mo.f_alta BETWEEN :fIni AND :fFin
                    ) AS t
                    WHERE prestamo_desempenio > 0
                    GROUP BY categoria
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);

                foreach ($desempenosRes as $row) {
                    $cat = $row->categoria;
                    $monto = (float)$row->monto;
                    $contratos = (int)$row->contratos;

                    $data['desempenos']['total'] += $contratos;
                    $data['desempenos']['monto'] += $monto;
                    $data['desempenos']['categorias'][$cat]['contratos'] += $contratos;
                    $data['desempenos']['categorias'][$cat]['monto'] += $monto;
                }

                // ============================================
                // 4. CARTERA VIGENTE/VENCIDA
                // ============================================
                $inventarioResult = DB::connection($connectionName)->select("
                    SELECT
                        'Alhaja' AS Tipo,
                        CASE 
                            WHEN kilataje BETWEEN 500 AND 999 THEN 'Plata' 
                            WHEN kilataje BETWEEN 8 AND 26 THEN 'Oro' 
                            ELSE 'Varios' 
                        END AS CategoriaMetal,
                        cod_estatus_prenda,
                        COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM alhajas 
                    WHERE cod_estatus_prenda IN (1,9) 
                    GROUP BY CategoriaMetal, cod_estatus_prenda
                    
                    UNION ALL
                    
                    SELECT 
                        'Varios' AS Tipo, 
                        'Varios' AS CategoriaMetal, 
                        cod_estatus_prenda, 
                        COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM varios 
                    WHERE cod_estatus_prenda IN (1,9) 
                    GROUP BY cod_estatus_prenda
                    
                    UNION ALL
                    
                    SELECT 
                        'Auto' AS Tipo, 
                        'Auto' AS CategoriaMetal, 
                        cod_estatus_prenda, 
                        COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM autos 
                    WHERE cod_estatus_prenda IN (1,9) 
                    GROUP BY cod_estatus_prenda
                ");

                foreach ($inventarioResult as $inv) {
                    $monto = (float)$inv->total_prestamo;
                    
                    if ($inv->cod_estatus_prenda == 1) { // Vigente
                        $data['cartera']['vigente'] += $monto;
                    } elseif ($inv->cod_estatus_prenda == 9) { // Vencida/Piso de venta
                        $data['cartera']['vencida'] += $monto;
                    }

                    // Clasificación por tipo
                    if ($inv->Tipo == 'Alhaja') {
                        $data['cartera']['oro'] += $monto;
                    } elseif ($inv->Tipo == 'Varios') {
                        $data['cartera']['varios'] += $monto;
                    } elseif ($inv->Tipo == 'Auto') {
                        $data['cartera']['auto'] += $monto;
                    }
                }

                // ============================================
                // 5. INTERESES COBRADOS Y DEPOSITARIA MES ANTERIOR
                // ============================================
                $interesesQ = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(
                            CASE 
                                WHEN mo.cod_tipo_movimiento = 2 THEN IF(mo.monto10 < 20, 20.0, mo.monto10)
                                WHEN mo.cod_tipo_movimiento = 4 THEN mo.monto10 - con.prestamo
                                WHEN mo.cod_tipo_movimiento = 3 THEN mo.monto10 - COALESCE((SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior), 0)
                                ELSE 0 
                            END
                        ), 0) AS total_intereses,
                        COALESCE(SUM(
                            CASE 
                                WHEN mo.cod_tipo_movimiento = 2 THEN IF(mo.monto10 < 20, 20.0, mo.monto10)
                                WHEN mo.cod_tipo_movimiento = 4 THEN mo.monto10 - con.prestamo
                                ELSE 0 
                            END
                        ), 0) AS total_refrendo_desempeno
                    FROM movimientos mo 
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE con.f_cancelacion IS NULL 
                      AND con.cod_tipo_prenda IN (1, 2, 3)
                      AND mo.f_alta BETWEEN :fIni AND :fFin
                      AND mo.cod_tipo_movimiento IN (2, 3, 4)
                ", [
                    ':fIni' => $fechaInicio, 
                    ':fFin' => $fechaFinQuery
                ]);
                
                $data['intereses']['cobrados'] += (float)($interesesQ->total_intereses ?? 0);
                $data['intereses']['refrendo_desempeno'] += (float)($interesesQ->total_refrendo_desempeno ?? 0);

                // Inventario de Depositaria al cierre del mes anterior (para base de tasa real)
                $depoMesAntQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(prestamo), 0) AS total_depositaria
                    FROM (
                        SELECT h.prestamo, h.cod_estatus_prenda,
                               ROW_NUMBER() OVER (
                                   PARTITION BY h.cod_tipo_prenda, h.cod_prenda 
                                   ORDER BY h.f_movimiento DESC, h.id_historico DESC
                               ) AS rn
                        FROM historico_articulos h
                        WHERE h.cod_tipo_prenda IN (1, 2, 3)
                          AND h.f_movimiento <= :corteMesAnt
                    ) t
                    WHERE t.rn = 1 AND t.cod_estatus_prenda IN (1, 2)
                ", [':corteMesAnt' => $corteMesAnterior]);

                $data['intereses']['depositaria_mes_anterior'] += (float)($depoMesAntQ->total_depositaria ?? 0);

                // ============================================
                // 6. DÍAS DE MORA (distribución)
                // ============================================
                $moraQ = DB::connection($connectionName)->select("
                    SELECT 
                        CASE 
                            WHEN DATEDIFF(NOW(), DATE_ADD(con.f_contrato, INTERVAL 30 DAY)) <= 30 THEN '0_30'
                            WHEN DATEDIFF(NOW(), DATE_ADD(con.f_contrato, INTERVAL 30 DAY)) BETWEEN 31 AND 60 THEN '31_60'
                            WHEN DATEDIFF(NOW(), DATE_ADD(con.f_contrato, INTERVAL 30 DAY)) BETWEEN 61 AND 90 THEN '61_90'
                            ELSE 'mas_90'
                        END as rango_mora,
                        COALESCE(SUM(con.prestamo), 0) as monto
                    FROM contratos con
                    WHERE con.f_cancelacion IS NULL
                      AND con.cod_tipo_prenda IN (1, 2, 3)
                    GROUP BY rango_mora
                ");
                
                $sucursalMora = [
                    '0_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'mas_90' => 0
                ];
                foreach ($moraQ as $mora) {
                    if (isset($data['mora'][$mora->rango_mora])) {
                        $data['mora'][$mora->rango_mora] += (float)$mora->monto;
                    }
                    $sucursalMora[$mora->rango_mora] = (float)$mora->monto;
                }
                $data['mora_detallado'][$sucursal->nombre] = $sucursalMora;

                // ============================================
                // 7. TIEMPO PROMEDIO DE EMPEÑO A DESEMPEÑO
                // ============================================
                $tiempoQ = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COUNT(*) as count_dias,
                        COALESCE(SUM(DATEDIFF(mo.f_alta, con.f_contrato)), 0) as sum_dias
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE mo.cod_tipo_movimiento = 4
                      AND mo.f_cancela IS NULL
                      AND con.f_cancelacion IS NULL
                      AND mo.f_alta BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFinQuery]);
                
                $data['tiempos']['dias_empeno_desempeno'] += (int)($tiempoQ->sum_dias ?? 0);
                $data['tiempos']['total_desempenos_con_dias'] += (int)($tiempoQ->count_dias ?? 0);

                // ============================================
                // 8. RANKINGS DE ARTÍCULOS MÁS EMPEÑADOS
                // ============================================
                $topEmpQ = DB::connection($connectionName)->select("
                    SELECT cod_prenda, articulo, SUM(total_movs) as total_movs, SUM(monto) as monto
                    FROM (
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda AND pre.cod_tipo_prenda = 1
                        WHERE mo.cod_tipo_movimiento = 1 
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 1
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1
                        GROUP BY pre.cod_prenda, pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                        WHERE mo.cod_tipo_movimiento = 1 
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 2
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2
                        GROUP BY pre.cod_prenda, pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda AND pre.cod_tipo_prenda = 3
                        WHERE mo.cod_tipo_movimiento = 1 
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 3
                          AND mo.f_alta BETWEEN :fIni3 AND :fFin3
                        GROUP BY pre.cod_prenda, pre.prenda
                    ) as t
                    GROUP BY cod_prenda, articulo
                    ORDER BY total_movs DESC
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFinQuery
                ]);

                foreach ($topEmpQ as $emp) {
                    $key = $emp->articulo;
                    if (!isset($rankingsEmpenados[$key])) {
                        $rankingsEmpenados[$key] = ['cod_prenda' => $emp->cod_prenda, 'articulo' => $key, 'total' => 0, 'monto' => 0];
                    }
                    $rankingsEmpenados[$key]['total'] += (int)$emp->total_movs;
                    $rankingsEmpenados[$key]['monto'] += (float)$emp->monto;
                }

                // ============================================
                // 9. RANKINGS DE ARTÍCULOS MÁS DESEMPEÑADOS
                // ============================================
                $topDesQ = DB::connection($connectionName)->select("
                    SELECT cod_prenda, articulo, SUM(total_movs) as total_movs, SUM(monto) as monto
                    FROM (
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda AND pre.cod_tipo_prenda = 1
                        WHERE mo.cod_tipo_movimiento = 4 
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 1
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1
                        GROUP BY pre.cod_prenda, pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                        WHERE mo.cod_tipo_movimiento = 4 
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 2
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2
                        GROUP BY pre.cod_prenda, pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda AND pre.cod_tipo_prenda = 3
                        WHERE mo.cod_tipo_movimiento = 4 
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 3
                          AND mo.f_alta BETWEEN :fIni3 AND :fFin3
                        GROUP BY pre.cod_prenda, pre.prenda
                    ) as t
                    GROUP BY cod_prenda, articulo
                    ORDER BY total_movs DESC
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFinQuery
                ]);

                foreach ($topDesQ as $des) {
                    $key = $des->articulo;
                    if (!isset($rankingsDesempenados[$key])) {
                        $rankingsDesempenados[$key] = ['cod_prenda' => $des->cod_prenda, 'articulo' => $key, 'total' => 0, 'monto' => 0];
                    }
                    $rankingsDesempenados[$key]['total'] += (int)$des->total_movs;
                    $rankingsDesempenados[$key]['monto'] += (float)$des->monto;
                }

                // ============================================
                // 9.1 RANKINGS DE ARTÍCULOS MÁS REFRENDADOS
                // ============================================
                $topRefQ = DB::connection($connectionName)->select("
                    SELECT cod_prenda, articulo, SUM(total_movs) as total_movs, SUM(monto) as monto
                    FROM (
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda AND pre.cod_tipo_prenda = 1
                        WHERE mo.cod_tipo_movimiento = 2
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 1
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1
                        GROUP BY pre.cod_prenda, pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                        WHERE mo.cod_tipo_movimiento = 2
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 2
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2
                        GROUP BY pre.cod_prenda, pre.prenda
                        
                        UNION ALL
                        
                        SELECT pre.cod_prenda, pre.prenda as articulo, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda AND pre.cod_tipo_prenda = 3
                        WHERE mo.cod_tipo_movimiento = 2
                          AND mo.f_cancela IS NULL 
                          AND con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 3
                          AND mo.f_alta BETWEEN :fIni3 AND :fFin3
                        GROUP BY pre.cod_prenda, pre.prenda
                    ) as t
                    GROUP BY cod_prenda, articulo
                    ORDER BY total_movs DESC
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFinQuery
                ]);

                foreach ($topRefQ as $ref) {
                    $key = $ref->articulo;
                    if (!isset($rankingsRefrendados[$key])) {
                        $rankingsRefrendados[$key] = ['cod_prenda' => $ref->cod_prenda, 'articulo' => $key, 'total' => 0, 'monto' => 0];
                    }
                    $rankingsRefrendados[$key]['total'] += (int)$ref->total_movs;
                    $rankingsRefrendados[$key]['monto'] += (float)$ref->monto;
                }

            } catch (\Exception $e) {
                Log::error("Error procesando sucursal {$sucursal->nombre} ({$dbName}) en OperacionesCartera: " . $e->getMessage());
                continue;
            }
        }

        // Ordenar y limitar rankings a los top 5
        usort($rankingsEmpenados, function($a, $b) { return $b['total'] <=> $a['total']; });
        usort($rankingsDesempenados, function($a, $b) { return $b['total'] <=> $a['total']; });
        usort($rankingsRefrendados, function($a, $b) { return $b['total'] <=> $a['total']; });
        
        $data['rankings']['articulos_empenados'] = array_slice($rankingsEmpenados, 0, 5);
        $data['rankings']['articulos_desempenados'] = array_slice($rankingsDesempenados, 0, 5);
        $data['rankings']['articulos_refrendados'] = array_slice($rankingsRefrendados, 0, 5);

        // Promedios derivados
        $data['empenos']['prestamo_promedio'] = $data['empenos']['total_contratos'] > 0 ? 
            $data['empenos']['monto_total'] / $data['empenos']['total_contratos'] : 0;
            
        $data['empenos']['sobreavaluo_pct'] = $data['empenos']['avaluo_total'] > 0 ? 
            ($data['empenos']['monto_total'] / $data['empenos']['avaluo_total']) * 100 : 0;

        $data['tiempos']['promedio_dias'] = $data['tiempos']['total_desempenos_con_dias'] > 0 ? 
            round($data['tiempos']['dias_empeno_desempeno'] / $data['tiempos']['total_desempenos_con_dias'], 2) : 0;

        $carteraTotal = $data['cartera']['vigente'] + $data['cartera']['vencida'];
        
        // Base de cálculo: Inventario de depositaria al cierre del mes anterior
        $baseDepositaria = $data['intereses']['depositaria_mes_anterior'] > 0 
            ? $data['intereses']['depositaria_mes_anterior'] 
            : $carteraTotal;

        // Numerador: Suma de intereses de refrendo y desempeño
        $interesesCalculo = $data['intereses']['refrendo_desempeno'] > 0 
            ? $data['intereses']['refrendo_desempeno'] 
            : $data['intereses']['cobrados'];

        $tasaRealMensual = $baseDepositaria > 0 ? 
            ($interesesCalculo / $baseDepositaria) * 100 : 0;
            
        $data['intereses']['tasa_real_mensual_pct'] = round($tasaRealMensual, 2);
        $data['intereses']['tasa_real_anual_pct'] = round($tasaRealMensual * 12, 2);

        return response()->json($data);
    }

    public function topMarcas(Request $request)
    {
        $codPrenda = $request->input('cod_prenda');
        $tipoMovimiento = $request->input('tipo_movimiento', 1); // 1 = Empeño, 4 = Desempeño
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
            $connectionName = 'dynamic_kpi_marcas_' . $sucursal->id_valora_mas;

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
                        SELECT va.cod_marca, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 3
                          AND va.cod_prenda = :codPrenda1
                          AND mo.cod_tipo_movimiento = :tipoMov1
                          AND mo.f_cancela IS NULL
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1
                        GROUP BY va.cod_marca
                        
                        UNION ALL
                        
                        SELECT au.cod_marca, COUNT(DISTINCT mo.cod_movimiento) as total_movs, SUM(mo.monto10) as monto
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 2
                          AND au.cod_prenda = :codPrenda2
                          AND mo.cod_tipo_movimiento = :tipoMov2
                          AND mo.f_cancela IS NULL
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2
                        GROUP BY au.cod_marca
                    ) as t
                    INNER JOIN marcas mar ON mar.cod_marca = t.cod_marca
                    GROUP BY mar.marca
                    ORDER BY total_movs DESC
                ", [
                    ':codPrenda1' => $codPrenda, ':tipoMov1' => $tipoMovimiento, ':fIni1' => $fechaInicio, ':fFin1' => $fechaFinQuery,
                    ':codPrenda2' => $codPrenda, ':tipoMov2' => $tipoMovimiento, ':fIni2' => $fechaInicio, ':fFin2' => $fechaFinQuery
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
                Log::error("Error top marcas sucursal {$sucursal->nombre}: " . $e->getMessage());
                continue;
            }
        }

        // Ordenar y limitar al top 10
        usort($rankingsMarcas, function($a, $b) { return $b['total'] <=> $a['total']; });
        $rankingsMarcas = array_slice($rankingsMarcas, 0, 10);

        return response()->json($rankingsMarcas);
    }
}