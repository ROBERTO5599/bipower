<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use App\Models\Sucursal;

use App\Services\PowerBIPermissionService;

class HomeController extends Controller
{
    public function index(Request $request = null)
    {
        $regionales = PowerBIPermissionService::getRegionales();
        $regionalUserId = $request ? $request->input('regional_user_id') : null;
        $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];
        $sucursales = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);
        if ($sucursales->isEmpty()) {
            $sucursales = Sucursal::whereNotNull('id_valora_mas')
                ->whereIn('id_valora_mas', $availableDbs)
                ->orderBy('nombre')
                ->get();
        }

        $totalEmpeno = 0;
        $totalRefrendo = 0;
        $totalDesempeno = 0;

        $sucursalesDetalleEmpeno = [];
        $sucursalesDetalleRefrendo = [];
        $sucursalesDetalleDesempeno = [];

        // Default to current month with precise time boundaries
        $fechaDel = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
        $fechaAl = Carbon::now()->endOfMonth()->format('Y-m-d H:i:s');

        // Base connection configuration (using the default mysql connection as a template)
        $baseConfig = Config::get('database.connections.mysql');

        if (!$baseConfig) {
            Log::error("MySQL connection 'mysql' is not configured.");
            return view('employees.home', [
                'totalEmpeno' => 0,
                'totalRefrendo' => 0,
                'totalDesempeno' => 0,
                'error' => 'Database configuration missing',
                'sucursalesDetalleEmpeno' => [],
                'sucursalesDetalleRefrendo' => [],
                'sucursalesDetalleDesempeno' => [],
                'fechaDel' => $fechaDel,
                'fechaAl' => $fechaAl
            ]);
        }

        $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];

        foreach ($sucursales as $sucursal) {
            $suffix = $sucursal->id_valora_mas;
            if (!$suffix || !in_array((int)$suffix, $availableDbs)) {
                continue;
            }
            $dbName = 'sistema_prendario_' . $suffix;
            $connectionName = 'dynamic_mysql';

            $empeno = 0;
            $refrendo = 0;
            $desempeno = 0;

            try {
                // Clone the base config and update the database name
                $config = $baseConfig;
                $config['database'] = $dbName;

                // Set the dynamic connection configuration
                Config::set("database.connections.{$connectionName}", $config);

                // Purge the connection to ensure fresh connection with new config
                DB::purge($connectionName);

                // Consulta idéntica a Tablero de Control para Empeño, Refrendos y Desempeño
                $query = "
                    SELECT
                        COALESCE(SUM(CASE WHEN indicador = 'Empeño' THEN avance ELSE 0 END), 0) AS total_empeno,
                        COALESCE(SUM(CASE WHEN indicador = 'Refrendos' THEN avance ELSE 0 END), 0) AS total_refrendo,
                        COALESCE(SUM(CASE WHEN indicador = 'Desempeño' THEN avance ELSE 0 END), 0) AS total_desempeno
                    FROM (
                        -- Empeño
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
                            , 0) AS avance
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE con.f_cancelacion IS NULL                             
                          AND mo.cod_tipo_movimiento = 1
                          AND mo.f_alta BETWEEN ? AND ?

                        UNION ALL

                        -- Refrendos (incluye mov 2 y mov 3)
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
                            , 0) AS avance
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE mo.f_alta BETWEEN ? AND ?
                          AND mo.f_cancela IS NULL
                          AND mo.cod_tipo_movimiento IN (2, 3)

                        UNION ALL

                        -- Desempeño Parte 1 (mov 4)
                        SELECT 'Desempeño' AS indicador,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            COALESCE(CASE
                                WHEN con.cod_tipo_prenda = 1 THEN al.prestamo
                                WHEN con.cod_tipo_prenda = 2 THEN au.prestamo
                                WHEN con.cod_tipo_prenda = 3 THEN va.prestamo
                            END, 0) AS avance
                        FROM movimientos mo
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        LEFT JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 1
                        LEFT JOIN autos   au ON au.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 2
                        LEFT JOIN varios  va ON va.cod_contrato = con.cod_seguimiento AND con.cod_tipo_prenda = 3
                        WHERE con.f_cancelacion IS NULL
                          AND mo.cod_tipo_movimiento = 4
                          AND mo.f_alta BETWEEN ? AND ?

                        UNION ALL

                        -- Desempeño Parte 2 (abono a capital en mov 3)
                        SELECT 'Desempeño' AS indicador,
                            CASE
                                WHEN con.cod_tipo_prenda = 2 THEN 'AUTOS'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 8   AND 26  THEN 'ORO'
                                WHEN con.cod_tipo_prenda = 1 AND al.kilataje BETWEEN 500 AND 999 THEN 'PLATA'
                                WHEN con.cod_tipo_prenda = 3 THEN 'MERCANCIA GENERAL'
                                ELSE 'SIN CATEGORIA'
                            END AS categoria,
                            COALESCE((SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior ORDER BY f_contrato DESC LIMIT 1), 0) AS avance
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
                          AND mo.f_alta BETWEEN ? AND ?
                    ) t
                    WHERE avance > 0
                      AND categoria IN ('MERCANCIA GENERAL', 'ORO', 'PLATA', 'AUTOS')
                ";

                $params = [
                    $fechaDel, $fechaAl,
                    $fechaDel, $fechaAl,
                    $fechaDel, $fechaAl,
                    $fechaDel, $fechaAl
                ];

                $result = DB::connection($connectionName)->selectOne($query, $params);

                if ($result) {
                    $empeno = (float)($result->total_empeno ?? 0);
                    $refrendo = (float)($result->total_refrendo ?? 0);
                    $desempeno = (float)($result->total_desempeno ?? 0);

                    $totalEmpeno += $empeno;
                    $totalRefrendo += $refrendo;
                    $totalDesempeno += $desempeno;
                }

            } catch (Exception $e) {
                // Log the error
                Log::error("Error connecting to or querying {$dbName}: " . $e->getMessage());
                // Totals remain 0 for this branch on error
            }

            // Add to detail lists
            $sucursalesDetalleEmpeno[] = [
                'nombre' => $sucursal->nombre,
                'total' => $empeno
            ];
            $sucursalesDetalleRefrendo[] = [
                'nombre' => $sucursal->nombre,
                'total' => $refrendo
            ];
            $sucursalesDetalleDesempeno[] = [
                'nombre' => $sucursal->nombre,
                'total' => $desempeno
            ];
        }

        // Sort details by total descending
        usort($sucursalesDetalleEmpeno, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        usort($sucursalesDetalleRefrendo, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        usort($sucursalesDetalleDesempeno, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        // Available branches with actual active databases
        $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];
        $filtered = $sucursales->filter(function($s) use ($availableDbs) {
            return in_array((int)$s->id_valora_mas, $availableDbs);
        });
        $sucursalesList = ($filtered->isNotEmpty() ? $filtered : $sucursales)->sortBy('nombre')->values();

        return view('employees.home', compact(
            'totalEmpeno', 'totalRefrendo', 'totalDesempeno',
            'sucursalesDetalleEmpeno', 'sucursalesDetalleRefrendo', 'sucursalesDetalleDesempeno',
            'fechaDel', 'fechaAl', 'regionales', 'regionalUserId', 'sucursalesList', 'sucursales'
        ));
    }

    /**
     * Obtener movimientos operativos por hora y periodo (día, semana, mes)
     */
    public function getMovimientosData(Request $request)
    {
        try {
            ini_set('max_execution_time', 120);

            $sucursalId = $request->input('sucursal_id', 'all'); // Por defecto todas las sucursales
            $periodo = $request->input('periodo', 'mes'); // Por defecto 'mes' (o 'hoy'/'dia')
            $fecha = $request->input('fecha', now()->toDateString());
            $fechaInicioReq = $request->input('fecha_inicio');
            $fechaFinReq = $request->input('fecha_fin');
            $tipoFiltro = $request->input('tipo_movimiento', 'todos');

            $carbonFecha = Carbon::parse($fecha);

            $rangoFechas = '';
            if ($periodo === 'rango' && $fechaInicioReq && $fechaFinReq) {
                $cIni = Carbon::parse($fechaInicioReq);
                $cFin = Carbon::parse($fechaFinReq);
                $fechaInicio = $cIni->format('Y-m-d 00:00:00');
                $fechaFin = $cFin->format('Y-m-d 23:59:59');
                $periodoLabel = 'Rango del ' . $cIni->format('d/m/Y') . ' al ' . $cFin->format('d/m/Y');
                $rangoFechas = $cIni->format('d/m/Y') . ' al ' . $cFin->format('d/m/Y');
            } elseif ($periodo === 'rango') {
                $cIni = Carbon::parse($fecha)->copy()->startOfMonth();
                $cFin = Carbon::parse($fecha);
                $fechaInicio = $cIni->format('Y-m-d 00:00:00');
                $fechaFin = $cFin->format('Y-m-d 23:59:59');
                $periodoLabel = 'Rango del ' . $cIni->format('d/m/Y') . ' al ' . $cFin->format('d/m/Y');
                $rangoFechas = $cIni->format('d/m/Y') . ' al ' . $cFin->format('d/m/Y');
            } elseif ($periodo === 'ayer') {
                $carbonAyer = Carbon::now()->subDay();
                $fechaInicio = $carbonAyer->format('Y-m-d 00:00:00');
                $fechaFin = $carbonAyer->format('Y-m-d 23:59:59');
                $periodoLabel = 'Ayer ' . $carbonAyer->format('d/m/Y');
                $rangoFechas = $carbonAyer->format('d/m/Y');
            } elseif ($periodo === 'hoy') {
                $carbonHoy = Carbon::now();
                $fechaInicio = $carbonHoy->format('Y-m-d 00:00:00');
                $fechaFin = $carbonHoy->format('Y-m-d 23:59:59');
                $periodoLabel = 'Hoy ' . $carbonHoy->format('d/m/Y');
                $rangoFechas = $carbonHoy->format('d/m/Y');
            } elseif ($periodo === 'semana') {
                $cStart = $carbonFecha->copy()->startOfWeek();
                $cEnd = $carbonFecha->copy()->endOfWeek();
                $fechaInicio = $cStart->format('Y-m-d 00:00:00');
                $fechaFin = $cEnd->format('Y-m-d 23:59:59');
                $periodoLabel = 'Semana del ' . $cStart->format('d/m/Y') . ' al ' . $cEnd->format('d/m/Y');
                $rangoFechas = $cStart->format('d/m/Y') . ' al ' . $cEnd->format('d/m/Y');
            } elseif ($periodo === 'mes') {
                $cStart = $carbonFecha->copy()->startOfMonth();
                $cEnd = $carbonFecha->copy()->endOfMonth();
                $fechaInicio = $cStart->format('Y-m-d 00:00:00');
                $fechaFin = $cEnd->format('Y-m-d 23:59:59');
                $mesesNombres = [
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                ];
                $mesNombre = $mesesNombres[(int)$carbonFecha->format('n')] ?? $carbonFecha->format('m');
                $periodoLabel = "Mes de {$mesNombre} " . $carbonFecha->format('Y');
                $rangoFechas = $cStart->format('d/m/Y') . ' al ' . $cEnd->format('d/m/Y');
            } else { // 'dia'
                $fechaInicio = $carbonFecha->format('Y-m-d 00:00:00');
                $fechaFin = $carbonFecha->format('Y-m-d 23:59:59');
                $diasNombres = [
                    0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
                    4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
                ];
                $diaNombre = $diasNombres[(int)$carbonFecha->format('w')] ?? '';
                $periodoLabel = "Día {$diaNombre} " . $carbonFecha->format('d/m/Y');
                $rangoFechas = $carbonFecha->format('d/m/Y');
            }

            $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];
            $permittedIds = PowerBIPermissionService::getPermittedSucursalIds();
            if (empty($permittedIds)) {
                $permittedIds = $availableDbs;
            }

            // Procesar si se filtran una, múltiples o todas las sucursales
            $selectedIds = [];
            if (is_array($sucursalId)) {
                $selectedIds = array_filter($sucursalId, function($id) { return $id !== 'all' && !empty($id); });
            } elseif (is_string($sucursalId) && strpos($sucursalId, ',') !== false) {
                $selectedIds = array_filter(explode(',', $sucursalId), function($id) { return $id !== 'all' && !empty($id); });
            } elseif ($sucursalId !== 'all' && !empty($sucursalId)) {
                $selectedIds = [$sucursalId];
            }
            $selectedIds = array_map('intval', $selectedIds);

            $sucursalesToQuery = [];
            if (empty($selectedIds)) {
                $sucursales = Sucursal::whereNotNull('id_valora_mas')
                    ->whereIn('id_valora_mas', $availableDbs)
                    ->whereIn('id_valora_mas', $permittedIds)
                    ->get();
                if ($sucursales->isEmpty()) {
                    $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
                }
                $sucursalesToQuery = $sucursales->all();
            } else {
                $sucursales = Sucursal::whereNotNull('id_valora_mas')
                    ->whereIn('id_valora_mas', $availableDbs)
                    ->whereIn('id_valora_mas', $permittedIds)
                    ->whereIn('id_valora_mas', $selectedIds)
                    ->get();
                if ($sucursales->isEmpty()) {
                    $sucursales = Sucursal::whereNotNull('id_valora_mas')
                        ->whereIn('id_valora_mas', $selectedIds)
                        ->get();
                }
                $sucursalesToQuery = $sucursales->all();
            }

            $baseConfig = Config::get('database.connections.mysql');
            if (!$baseConfig) {
                return response()->json(['success' => false, 'error' => 'Configuración de base de datos no encontrada.'], 500);
            }

            $hourlyCounts = array_fill(0, 24, 0);
            $hourlyMontos = array_fill(0, 24, 0.0);

            $periodoCounts = [];
            $periodoMontos = [];

            $typesMap = [];
            $movimientosList = [];
            $totalMovimientos = 0;
            $totalMonto = 0.0;
            $sucursalesData = [];

            foreach ($sucursalesToQuery as $suc) {
                $suffix = $suc->id_valora_mas;
                $dbName = 'sistema_prendario_' . $suffix;
                $connName = 'dynamic_home_' . $suffix;

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.{$connName}", $config);
                DB::purge($connName);

                try {
                    $params = [$fechaInicio, $fechaFin];
                    
                    // Contadores específicos de esta sucursal
                    $sucHourlyCounts = array_fill(0, 24, 0);
                    $sucHourlyMontos = array_fill(0, 24, 0.0);
                    $sucPeriodoCounts = [];
                    $sucPeriodoMontos = [];
                    $sucTotalMovimientos = 0;
                    $sucTotalMonto = 0.0;

                    // Excluir dotaciones, inicios de caja, retiros y cierres de la contabilización operativa
                    // (9: Dotación Caja, 10: Retiro Bóveda, 14: Retiro Caja a Bóveda, 15: Inicio Caja, 16: Cierre Caja, 17: Dotación Bóveda, 25: Dotación Bancos)
                    $excludedTypes = [9, 10, 14, 15, 16, 17, 25];
                    $excludedList = implode(',', $excludedTypes);

                    $typeCondition = " AND m.cod_tipo_movimiento NOT IN ({$excludedList})";
                    if ($tipoFiltro !== 'todos' && is_numeric($tipoFiltro)) {
                        if (in_array((int)$tipoFiltro, $excludedTypes)) {
                            $typeCondition = " AND 1 = 0";
                        } else {
                            $typeCondition = " AND m.cod_tipo_movimiento = ?";
                            $params[] = (int)$tipoFiltro;
                        }
                    }

                    // El monto operado corresponde al valor préstamo del contrato en operaciones prendarias (empeño, refrendo, abono capital, desempeño)
                    $montoOperadoSql = "COALESCE(
                        CASE 
                            WHEN m.cod_tipo_movimiento IN (1, 2, 3, 4) AND con.prestamo IS NOT NULL AND con.prestamo > 0 
                                THEN con.prestamo
                            ELSE m.monto
                        END,
                        m.monto,
                        0
                    )";

                    // 1. Agrupado por Hora
                    $qHourly = "
                        SELECT HOUR(m.f_alta) as hora, COUNT(*) as conteo, COALESCE(SUM({$montoOperadoSql}), 0) as total_monto
                        FROM movimientos m
                        LEFT JOIN contratos con ON con.cod_contrato = COALESCE(m.cod_contrato, m.contrato)
                        WHERE m.f_cancela IS NULL
                          AND m.f_alta BETWEEN ? AND ?
                          {$typeCondition}
                        GROUP BY HOUR(m.f_alta)
                        ORDER BY hora ASC
                    ";
                    $resHourly = DB::connection($connName)->select($qHourly, $params);
                    foreach ($resHourly as $h) {
                        $hr = (int)$h->hora;
                        if ($hr >= 0 && $hr < 24) {
                            $cnt = (int)$h->conteo;
                            $mnt = (float)$h->total_monto;
                            $hourlyCounts[$hr] += $cnt;
                            $hourlyMontos[$hr] += $mnt;
                            $sucHourlyCounts[$hr] += $cnt;
                            $sucHourlyMontos[$hr] += $mnt;
                        }
                    }

                    // 2. Agrupado por Día (si es semana, mes o rango)
                    if ($periodo === 'semana' || $periodo === 'mes' || $periodo === 'rango') {
                        $qPeriod = "
                            SELECT CAST(m.f_alta AS DATE) as fecha_dia, COUNT(*) as conteo, COALESCE(SUM({$montoOperadoSql}), 0) as total_monto
                            FROM movimientos m
                            LEFT JOIN contratos con ON con.cod_contrato = COALESCE(m.cod_contrato, m.contrato)
                            WHERE m.f_cancela IS NULL
                              AND m.f_alta BETWEEN ? AND ?
                              {$typeCondition}
                            GROUP BY CAST(m.f_alta AS DATE)
                            ORDER BY fecha_dia ASC
                        ";
                        $resPeriod = DB::connection($connName)->select($qPeriod, $params);
                        foreach ($resPeriod as $p) {
                            $fKey = (string)$p->fecha_dia;
                            if (!isset($periodoCounts[$fKey])) {
                                $periodoCounts[$fKey] = 0;
                                $periodoMontos[$fKey] = 0.0;
                            }
                            $cnt = (int)$p->conteo;
                            $mnt = (float)$p->total_monto;
                            $periodoCounts[$fKey] += $cnt;
                            $periodoMontos[$fKey] += $mnt;
                            $sucPeriodoCounts[$fKey] = ($sucPeriodoCounts[$fKey] ?? 0) + $cnt;
                            $sucPeriodoMontos[$fKey] = ($sucPeriodoMontos[$fKey] ?? 0.0) + $mnt;
                        }
                    }

                    // 3. Agrupado por Tipo de Movimiento (excluyendo dotaciones e inicios de caja)
                    $qTypes = "
                        SELECT m.cod_tipo_movimiento, COALESCE(tm.tipo_movimiento, 'OTRO') as tipo_nombre,
                               COUNT(*) as conteo, COALESCE(SUM({$montoOperadoSql}), 0) as total_monto
                        FROM movimientos m
                        LEFT JOIN contratos con ON con.cod_contrato = COALESCE(m.cod_contrato, m.contrato)
                        LEFT JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = m.cod_tipo_movimiento
                        WHERE m.f_cancela IS NULL
                          AND m.f_alta BETWEEN ? AND ?
                          {$typeCondition}
                          AND (tm.tipo_movimiento IS NULL OR (tm.tipo_movimiento NOT LIKE '%DOTACI%' AND tm.tipo_movimiento NOT LIKE '%INICIO%' AND tm.tipo_movimiento NOT LIKE '%CIERRE%' AND tm.tipo_movimiento NOT LIKE '%RETIRO%'))
                        GROUP BY m.cod_tipo_movimiento, tm.tipo_movimiento
                        ORDER BY conteo DESC
                    ";
                    $resTypes = DB::connection($connName)->select($qTypes, $params);
                    foreach ($resTypes as $t) {
                        $tid = (int)$t->cod_tipo_movimiento;
                        if (!isset($typesMap[$tid])) {
                            $typesMap[$tid] = [
                                'cod_tipo_movimiento' => $tid,
                                'nombre' => $t->tipo_nombre,
                                'conteo' => 0,
                                'monto' => 0.0
                            ];
                        }
                        $cnt = (int)$t->conteo;
                        $mnt = (float)$t->total_monto;
                        $typesMap[$tid]['conteo'] += $cnt;
                        $typesMap[$tid]['monto'] += $mnt;
                        $totalMovimientos += $cnt;
                        $totalMonto += $mnt;

                        $sucTotalMovimientos += $cnt;
                        $sucTotalMonto += $mnt;
                    }

                    // 4. Detalle de movimientos con tiempo
                    $qList = "
                        SELECT m.cod_movimiento, m.cod_contrato, m.contrato, m.cod_caja, m.cod_usuario,
                               COALESCE(u.nombre, CONCAT('Usuario ', m.cod_usuario)) as usuario_nombre,
                               u.usuario as usuario_login,
                               {$montoOperadoSql} as monto,
                               m.monto as monto_transaccion,
                               con.prestamo as valor_prestamo,
                               m.monto_efectivo, m.monto_tarjeta, m.f_alta,
                               m.cod_tipo_movimiento, COALESCE(tm.tipo_movimiento, 'OTRO') as tipo_nombre,
                               m.referencia
                        FROM movimientos m
                        LEFT JOIN contratos con ON con.cod_contrato = COALESCE(m.cod_contrato, m.contrato)
                        LEFT JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = m.cod_tipo_movimiento
                        LEFT JOIN usuarios u ON u.cod_usuario = m.cod_usuario
                        WHERE m.f_cancela IS NULL
                          AND m.f_alta BETWEEN ? AND ?
                          {$typeCondition}
                          AND (tm.tipo_movimiento IS NULL OR (tm.tipo_movimiento NOT LIKE '%DOTACI%' AND tm.tipo_movimiento NOT LIKE '%INICIO%' AND tm.tipo_movimiento NOT LIKE '%CIERRE%' AND tm.tipo_movimiento NOT LIKE '%RETIRO%'))
                        ORDER BY m.f_alta DESC
                        LIMIT 300
                    ";
                    $resList = DB::connection($connName)->select($qList, $params);
                    foreach ($resList as $item) {
                        $fCarbon = Carbon::parse($item->f_alta);
                        $movimientosList[] = [
                            'cod_movimiento' => (int)$item->cod_movimiento,
                            'cod_contrato' => $item->cod_contrato ?: $item->contrato,
                            'sucursal' => $suc->nombre,
                            'cod_caja' => (int)$item->cod_caja,
                            'cod_usuario' => (int)$item->cod_usuario,
                            'usuario_nombre' => $item->usuario_nombre ?: ('Usuario ' . $item->cod_usuario),
                            'usuario_login' => $item->usuario_login ?? '',
                            'monto' => (float)$item->monto,
                            'monto_transaccion' => (float)$item->monto_transaccion,
                            'valor_prestamo' => $item->valor_prestamo !== null ? (float)$item->valor_prestamo : null,
                            'monto_efectivo' => (float)$item->monto_efectivo,
                            'monto_tarjeta' => (float)$item->monto_tarjeta,
                            'f_alta' => $item->f_alta,
                            'fecha' => $fCarbon->format('d/m/Y'),
                            'hora' => $fCarbon->format('H:i:s'),
                            'hora_corta' => $fCarbon->format('H:i'),
                            'cod_tipo_movimiento' => (int)$item->cod_tipo_movimiento,
                            'tipo_nombre' => $item->tipo_nombre,
                            'referencia' => $item->referencia ?? ''
                        ];
                    }

                    $sucursalesData[] = [
                        'id' => $suc->id ?? $suc->id_valora_mas,
                        'id_valora_mas' => $suc->id_valora_mas,
                        'nombre' => $suc->nombre,
                        'total_movimientos' => $sucTotalMovimientos,
                        'total_monto' => $sucTotalMonto,
                        'hourly_counts' => $sucHourlyCounts,
                        'hourly_montos' => $sucHourlyMontos,
                        'periodo_counts' => $sucPeriodoCounts,
                        'periodo_montos' => $sucPeriodoMontos,
                    ];
                } catch (\Exception $e) {
                    Log::error("Error querying movimientos for {$dbName}: " . $e->getMessage());
                }
            }

            // Ordenar listado por fecha/hora descendente
            usort($movimientosList, function($a, $b) {
                return strcmp($b['f_alta'], $a['f_alta']);
            });
            $movimientosList = array_slice($movimientosList, 0, 300);

            // Armar datos horarios (de 07:00 a 20:00)
            $hourlyLabels = [];
            $hourlyDataCounts = [];
            $hourlyDataMontos = [];
            $maxHourCount = -1;
            $horaPico = 'Sin movimientos';

            for ($h = 7; $h <= 20; $h++) {
                $label = sprintf('%02d:00', $h);
                $hourlyLabels[] = $label;
                $count = $hourlyCounts[$h];
                $hourlyDataCounts[] = $count;
                $hourlyDataMontos[] = $hourlyMontos[$h];

                if ($count > $maxHourCount && $count > 0) {
                    $maxHourCount = $count;
                    $horaPico = sprintf('%02d:00 a %02d:00 hrs (%d movs)', $h, $h + 1, $count);
                }
            }

            // Armar datos de periodo (semana o mes)
            $periodLabels = [];
            $periodCountsArr = [];
            $periodMontosArr = [];

            if ($periodo === 'semana') {
                $startOfWeek = $carbonFecha->copy()->startOfWeek();
                $diasNombres = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
                for ($d = 0; $d < 7; $d++) {
                    $cur = $startOfWeek->copy()->addDays($d);
                    $key = $cur->toDateString();
                    $periodLabels[] = $diasNombres[$d] . ' ' . $cur->format('d/m');
                    $periodCountsArr[] = $periodoCounts[$key] ?? 0;
                    $periodMontosArr[] = $periodoMontos[$key] ?? 0.0;
                }
            } elseif ($periodo === 'mes') {
                $daysInMonth = $carbonFecha->daysInMonth;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $key = $carbonFecha->copy()->day($d)->toDateString();
                    $periodLabels[] = "Día $d";
                    $periodCountsArr[] = $periodoCounts[$key] ?? 0;
                    $periodMontosArr[] = $periodoMontos[$key] ?? 0.0;
                }
            } elseif ($periodo === 'rango') {
                $cStart = Carbon::parse($fechaInicio);
                $cEnd = Carbon::parse($fechaFin);
                $diffDays = $cStart->diffInDays($cEnd);
                if ($diffDays <= 45) {
                    for ($d = 0; $d <= $diffDays; $d++) {
                        $cur = $cStart->copy()->addDays($d);
                        $key = $cur->toDateString();
                        $periodLabels[] = $cur->format('d/m');
                        $periodCountsArr[] = $periodoCounts[$key] ?? 0;
                        $periodMontosArr[] = $periodoMontos[$key] ?? 0.0;
                    }
                } else {
                    ksort($periodoCounts);
                    foreach ($periodoCounts as $key => $cnt) {
                        $periodLabels[] = Carbon::parse($key)->format('d/m');
                        $periodCountsArr[] = $cnt;
                        $periodMontosArr[] = $periodoMontos[$key] ?? 0.0;
                    }
                }
            }

            // Cálculo de días y horas operativas según el periodo
            // Fórmula solicitada por el usuario:
            // Horas operativas = (64 / 7) * (número de días o día)
            // Promedio por hora = Total movimientos / Horas operativas
            // Ejemplo: 64/7 = 9.1 * 19 = 173.7 hrs -> 2348 / 173.7 = 13.5 movs/hr
            $diasPeriodo = 1;
            if ($periodo === 'dia' || $periodo === 'hoy' || $periodo === 'ayer') {
                $diasPeriodo = 1;
            } elseif ($periodo === 'semana') {
                $startOfWeek = $carbonFecha->copy()->startOfWeek();
                $diasPeriodo = $carbonFecha->isCurrentWeek()
                    ? min(7, max(1, $startOfWeek->diffInDays($carbonFecha) + 1))
                    : 7;
            } elseif ($periodo === 'mes') {
                $diasPeriodo = $carbonFecha->isCurrentMonth()
                    ? min((int)$carbonFecha->daysInMonth, max(1, (int)$carbonFecha->day))
                    : (int)$carbonFecha->daysInMonth;
            } elseif ($periodo === 'rango') {
                $cIni = Carbon::parse($fechaInicioReq ?? $fechaInicio);
                $cFin = Carbon::parse($fechaFinReq ?? $fechaFin);
                $diasPeriodo = max(1, $cIni->diffInDays($cFin) + 1);
            }

            $horasOperativas = (64.0 / 7.0) * $diasPeriodo;
            $horasOperativasRedondeadas = round($horasOperativas, 1);
            $promedioPorHora = $horasOperativasRedondeadas > 0 ? round($totalMovimientos / $horasOperativasRedondeadas, 1) : 0;

            // Armar datos por cada sucursal individual para comparar gráficas
            $porSucursalResult = [];
            foreach ($sucursalesData as $sd) {
                $sdHourlyCounts = [];
                $sdHourlyMontos = [];
                $sdMaxCount = -1;
                $sdHoraPico = 'Sin movimientos';

                for ($h = 7; $h <= 20; $h++) {
                    $c = $sd['hourly_counts'][$h] ?? 0;
                    $m = $sd['hourly_montos'][$h] ?? 0.0;
                    $sdHourlyCounts[] = $c;
                    $sdHourlyMontos[] = $m;

                    if ($c > $sdMaxCount && $c > 0) {
                        $sdMaxCount = $c;
                        $sdHoraPico = sprintf('%02d:00 a %02d:00 hrs (%d movs)', $h, $h + 1, $c);
                    }
                }

                $sdPeriodCounts = [];
                $sdPeriodMontos = [];

                if ($periodo === 'semana') {
                    $startOfWeek = $carbonFecha->copy()->startOfWeek();
                    for ($d = 0; $d < 7; $d++) {
                        $cur = $startOfWeek->copy()->addDays($d);
                        $key = $cur->toDateString();
                        $sdPeriodCounts[] = $sd['periodo_counts'][$key] ?? 0;
                        $sdPeriodMontos[] = $sd['periodo_montos'][$key] ?? 0.0;
                    }
                } elseif ($periodo === 'mes') {
                    $daysInMonth = $carbonFecha->daysInMonth;
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $key = $carbonFecha->copy()->day($d)->toDateString();
                        $sdPeriodCounts[] = $sd['periodo_counts'][$key] ?? 0;
                        $sdPeriodMontos[] = $sd['periodo_montos'][$key] ?? 0.0;
                    }
                } elseif ($periodo === 'rango') {
                    $cStart = Carbon::parse($fechaInicio);
                    $cEnd = Carbon::parse($fechaFin);
                    $diffDays = $cStart->diffInDays($cEnd);
                    if ($diffDays <= 45) {
                        for ($d = 0; $d <= $diffDays; $d++) {
                            $cur = $cStart->copy()->addDays($d);
                            $key = $cur->toDateString();
                            $sdPeriodCounts[] = $sd['periodo_counts'][$key] ?? 0;
                            $sdPeriodMontos[] = $sd['periodo_montos'][$key] ?? 0.0;
                        }
                    } else {
                        ksort($periodoCounts);
                        foreach ($periodoCounts as $key => $cnt) {
                            $sdPeriodCounts[] = $sd['periodo_counts'][$key] ?? 0;
                            $sdPeriodMontos[] = $sd['periodo_montos'][$key] ?? 0.0;
                        }
                    }
                }

                $sdPromedio = $horasOperativasRedondeadas > 0 ? round($sd['total_movimientos'] / $horasOperativasRedondeadas, 1) : 0;

                $porSucursalResult[] = [
                    'id' => $sd['id'],
                    'id_valora_mas' => $sd['id_valora_mas'],
                    'nombre' => $sd['nombre'],
                    'total_movimientos' => $sd['total_movimientos'],
                    'total_monto' => $sd['total_monto'],
                    'hora_pico' => $sdHoraPico,
                    'promedio_por_hora' => $sdPromedio,
                    'por_hora' => [
                        'labels' => $hourlyLabels,
                        'counts' => $sdHourlyCounts,
                        'montos' => $sdHourlyMontos,
                    ],
                    'por_periodo' => [
                        'labels' => $periodLabels,
                        'counts' => $sdPeriodCounts,
                        'montos' => $sdPeriodMontos,
                    ],
                ];
            }

            // Ordenar por total_movimientos de mayor a menor
            usort($porSucursalResult, function($a, $b) {
                return $b['total_movimientos'] <=> $a['total_movimientos'];
            });

            // Ordenar desglose de tipos
            usort($typesMap, function($a, $b) {
                return $b['conteo'] <=> $a['conteo'];
            });

            // Paleta de colores para tipos de movimiento
            $colorPalette = [
                1 => '#0d6efd', // Empeño: Azul
                2 => '#198754', // Refrendo: Verde
                3 => '#ffc107', // Abono capital: Amarillo
                4 => '#0dcaf0', // Desempeño: Cian
                5 => '#fd7e14', // Venta menudeo: Naranja
                6 => '#d63384', // Venta mayoreo: Rosa
                7 => '#6f42c1', // Anticipo apartado: Morado
                8 => '#20c997', // Abono apartado: Teal
                10 => '#475569', // Retiro de bóveda: Pizarra
                12 => '#6366f1', // Liq apartado: Índigo vivo
                14 => '#64748b', // Retiro de caja a bóveda: Slate
                16 => '#94a3b8', // Cierre caja: Gris neutro
                18 => '#e11d48', // Gastos: Carmesí
                19 => '#0284c7', // Enganche crédito: Sky
                20 => '#15803d', // Pago crédito: Verde bosque
                21 => '#374151', // Liq crédito: Gris oscuro
                22 => '#d97706', // Devolución garantía: Ámbar
                23 => '#78716c', // Devolución crédito: Piedra
                24 => '#0891b2', // Retiro de bancos: Cyan oscuro
            ];

            foreach ($typesMap as &$item) {
                $tid = $item['cod_tipo_movimiento'];
                $item['color'] = $colorPalette[$tid] ?? '#adb5bd';
            }

            return response()->json([
                'success' => true,
                'periodo' => $periodo,
                'periodoLabel' => $periodoLabel,
                'rango_fechas' => $rangoFechas,
                'fecha' => $fecha,
                'sucursal_id' => is_array($sucursalId) ? implode(',', $sucursalId) : (string)$sucursalId,
                'sucursales_count' => count($sucursalesToQuery),
                'total_movimientos' => $totalMovimientos,
                'total_monto' => $totalMonto,
                'hora_pico' => $horaPico,
                'promedio_por_hora' => $promedioPorHora,
                'dias_periodo' => $diasPeriodo,
                'horas_operativas' => $horasOperativasRedondeadas,
                'por_hora' => [
                    'labels' => $hourlyLabels,
                    'counts' => $hourlyDataCounts,
                    'montos' => $hourlyDataMontos,
                ],
                'por_periodo' => [
                    'labels' => $periodLabels,
                    'counts' => $periodCountsArr,
                    'montos' => $periodMontosArr,
                ],
                'por_tipo' => array_values($typesMap),
                'por_sucursal' => $porSucursalResult,
                'movimientos' => $movimientosList,
            ]);

        } catch (\Exception $e) {
            Log::error("Error in getMovimientosData: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
