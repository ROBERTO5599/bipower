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
        $sucursales = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);

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

        foreach ($sucursales as $sucursal) {
            $suffix = $sucursal->id_valora_mas;
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

                // Updated Query:
                // - Movement types 1 (Empeno), 2 (Refrendo), 4 (Desempeno)
                // - Using conditional aggregation to get all 3 sums in one query
                // - Filter on mo.f_alta using precise DATETIME range using CAST AS DATE as requested
                // - Embedding dates directly into query string for precise control/debugging
                $query = "
                    SELECT
                        SUM(CASE WHEN mo.cod_tipo_movimiento = 1 THEN con.prestamo ELSE 0 END) as total_empeno,
                        SUM(CASE WHEN mo.cod_tipo_movimiento = 2 THEN con.prestamo ELSE 0 END) as total_refrendo,
                        SUM(CASE WHEN mo.cod_tipo_movimiento = 4 THEN con.prestamo ELSE 0 END) as total_desempeno
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE con.f_cancelacion IS NULL
                    AND mo.cod_tipo_movimiento IN (1, 2, 4)
                    AND CAST(mo.f_alta AS DATE) BETWEEN '$fechaDel' AND '$fechaAl'
                    AND con.cod_tipo_prenda IN (1, 2, 3)
                ";

                $result = DB::connection($connectionName)->selectOne($query);

                if ($result) {
                    $empeno = $result->total_empeno ?? 0;
                    $refrendo = $result->total_refrendo ?? 0;
                    $desempeno = $result->total_desempeno ?? 0;

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
        $sucursalesList = $sucursales->filter(function($s) use ($availableDbs) {
            return in_array((int)$s->id_valora_mas, $availableDbs);
        })->sortBy('nombre')->values();

        return view('employees.home', compact(
            'totalEmpeno', 'totalRefrendo', 'totalDesempeno',
            'sucursalesDetalleEmpeno', 'sucursalesDetalleRefrendo', 'sucursalesDetalleDesempeno',
            'fechaDel', 'fechaAl', 'regionales', 'regionalUserId', 'sucursalesList'
        ));
    }

    /**
     * Obtener movimientos operativos por hora y periodo (día, semana, mes)
     */
    public function getMovimientosData(Request $request)
    {
        try {
            ini_set('max_execution_time', 120);

            $sucursalId = $request->input('sucursal_id', '2'); // Por defecto Atlacomulco (2)
            $periodo = $request->input('periodo', 'dia'); // 'dia', 'semana', 'mes'
            $fecha = $request->input('fecha', now()->toDateString());
            $tipoFiltro = $request->input('tipo_movimiento', 'todos');

            $carbonFecha = Carbon::parse($fecha);

            if ($periodo === 'semana') {
                $fechaInicio = $carbonFecha->copy()->startOfWeek()->format('Y-m-d 00:00:00');
                $fechaFin = $carbonFecha->copy()->endOfWeek()->format('Y-m-d 23:59:59');
                $periodoLabel = 'Semana del ' . $carbonFecha->copy()->startOfWeek()->format('d/m/Y') . ' al ' . $carbonFecha->copy()->endOfWeek()->format('d/m/Y');
            } elseif ($periodo === 'mes') {
                $fechaInicio = $carbonFecha->copy()->startOfMonth()->format('Y-m-d 00:00:00');
                $fechaFin = $carbonFecha->copy()->endOfMonth()->format('Y-m-d 23:59:59');
                $periodoLabel = 'Mes de ' . $carbonFecha->format('m/Y');
            } else { // 'dia'
                $fechaInicio = $carbonFecha->format('Y-m-d 00:00:00');
                $fechaFin = $carbonFecha->format('Y-m-d 23:59:59');
                $periodoLabel = 'Día ' . $carbonFecha->format('d/m/Y');
            }

            $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];
            $permittedIds = PowerBIPermissionService::getPermittedSucursalIds();

            $sucursalesToQuery = [];
            if ($sucursalId === 'all') {
                $sucursales = Sucursal::whereNotNull('id_valora_mas')
                    ->whereIn('id_valora_mas', $availableDbs)
                    ->whereIn('id_valora_mas', $permittedIds)
                    ->get();
                $sucursalesToQuery = $sucursales->all();
            } else {
                $sucursal = Sucursal::where('id_valora_mas', $sucursalId)->first();
                if ($sucursal && in_array((int)$sucursal->id_valora_mas, $availableDbs) && in_array((int)$sucursal->id_valora_mas, $permittedIds)) {
                    $sucursalesToQuery = [$sucursal];
                } else {
                    $fallback = Sucursal::where('id_valora_mas', 2)->first();
                    if ($fallback) {
                        $sucursalesToQuery = [$fallback];
                    }
                }
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
                    $typeCondition = "";
                    if ($tipoFiltro !== 'todos' && is_numeric($tipoFiltro)) {
                        $typeCondition = " AND m.cod_tipo_movimiento = ?";
                        $params[] = (int)$tipoFiltro;
                    }

                    // 1. Agrupado por Hora
                    $qHourly = "
                        SELECT HOUR(m.f_alta) as hora, COUNT(*) as conteo, COALESCE(SUM(m.monto), 0) as total_monto
                        FROM movimientos m
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
                            $hourlyCounts[$hr] += (int)$h->conteo;
                            $hourlyMontos[$hr] += (float)$h->total_monto;
                        }
                    }

                    // 2. Agrupado por Día (si es semana o mes)
                    if ($periodo === 'semana' || $periodo === 'mes') {
                        $qPeriod = "
                            SELECT CAST(m.f_alta AS DATE) as fecha_dia, COUNT(*) as conteo, COALESCE(SUM(m.monto), 0) as total_monto
                            FROM movimientos m
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
                            $periodoCounts[$fKey] += (int)$p->conteo;
                            $periodoMontos[$fKey] += (float)$p->total_monto;
                        }
                    }

                    // 3. Agrupado por Tipo de Movimiento
                    $qTypes = "
                        SELECT m.cod_tipo_movimiento, COALESCE(tm.tipo_movimiento, 'OTRO') as tipo_nombre,
                               COUNT(*) as conteo, COALESCE(SUM(m.monto), 0) as total_monto
                        FROM movimientos m
                        LEFT JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = m.cod_tipo_movimiento
                        WHERE m.f_cancela IS NULL
                          AND m.f_alta BETWEEN ? AND ?
                          {$typeCondition}
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
                        $typesMap[$tid]['conteo'] += (int)$t->conteo;
                        $typesMap[$tid]['monto'] += (float)$t->total_monto;
                        $totalMovimientos += (int)$t->conteo;
                        $totalMonto += (float)$t->total_monto;
                    }

                    // 4. Detalle de movimientos con tiempo
                    $qList = "
                        SELECT m.cod_movimiento, m.cod_contrato, m.contrato, m.cod_caja, m.cod_usuario,
                               m.monto, m.monto_efectivo, m.monto_tarjeta, m.f_alta,
                               m.cod_tipo_movimiento, COALESCE(tm.tipo_movimiento, 'OTRO') as tipo_nombre,
                               m.referencia
                        FROM movimientos m
                        LEFT JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = m.cod_tipo_movimiento
                        WHERE m.f_cancela IS NULL
                          AND m.f_alta BETWEEN ? AND ?
                          {$typeCondition}
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
                            'monto' => (float)$item->monto,
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
            }

            // Ordenar desglose de tipos
            usort($typesMap, function($a, $b) {
                return $b['conteo'] <=> $a['conteo'];
            });

            // Promedio por hora con actividad
            $activeHours = count(array_filter($hourlyDataCounts, function($c) { return $c > 0; }));
            $promedioPorHora = $activeHours > 0 ? round($totalMovimientos / $activeHours, 1) : 0;

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
                9 => '#4b5563', // Dotación caja
                10 => '#475569', // Dotación bóveda
                12 => '#6366f1', // Liq apartado: Índigo vivo
                14 => '#4b5563', // Retiro a bóveda
                15 => '#1f2937', // Inicio caja
                16 => '#111827', // Cierre caja
                19 => '#0284c7', // Enganche crédito
                20 => '#15803d', // Pago crédito
                21 => '#374151', // Liq crédito
            ];

            foreach ($typesMap as &$item) {
                $tid = $item['cod_tipo_movimiento'];
                $item['color'] = $colorPalette[$tid] ?? '#adb5bd';
            }

            return response()->json([
                'success' => true,
                'periodo' => $periodo,
                'periodoLabel' => $periodoLabel,
                'fecha' => $fecha,
                'sucursal_id' => $sucursalId,
                'total_movimientos' => $totalMovimientos,
                'total_monto' => $totalMonto,
                'hora_pico' => $horaPico,
                'promedio_por_hora' => $promedioPorHora,
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
