<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;
use App\Models\User;
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

    private function normalizarCategoria($catRaw)
    {
        $cat = strtoupper(trim($catRaw ?? ''));
        if (str_contains($cat, 'PLATA')) return 'PLATA';
        if (str_contains($cat, 'ORO') || str_contains($cat, 'ALHAJA') || $cat === 'METAL') return 'ORO';
        if (str_contains($cat, 'AUTO')) return 'AUTOS';
        return 'VARIOS';
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
        
        $activeUsers = User::where('status', 0)->get();
        
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
        $ratiosSucursales = [];
        $valuadoresList = [];
        $globalCategoriasResumen = [
            'ORO' => ['categoria' => 'ORO', 'cantidad' => 0, 'monto_avaluo' => 0, 'monto_prestamo' => 0],
            'PLATA' => ['categoria' => 'PLATA', 'cantidad' => 0, 'monto_avaluo' => 0, 'monto_prestamo' => 0],
            'VARIOS' => ['categoria' => 'VARIOS', 'cantidad' => 0, 'monto_avaluo' => 0, 'monto_prestamo' => 0],
            'AUTOS' => ['categoria' => 'AUTOS', 'cantidad' => 0, 'monto_avaluo' => 0, 'monto_prestamo' => 0],
        ];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $conn = 'colab_dyn_' . $sucursal->id_valora_mas;

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$conn", $config);
                DB::purge($conn);

                // 1. Nómina de la sucursal (Req 85) desde el campo 'sueldo' de colaboradores activos (status = 0)
                $nominaSucursal = (float) $activeUsers->where('sucursal_id', $sucursal->id)->sum('sueldo');
                $nominaTotal += $nominaSucursal;

                // Empleados operativos de la sucursal (mínimo 5 movimientos en sistema en el periodo para no duplicar líderes)
                $operativosQ = DB::connection($conn)->select("
                    SELECT mo.cod_usuario, COUNT(mo.cod_movimiento) as total_movs
                    FROM movimientos mo
                    WHERE mo.f_cancela IS NULL AND mo.f_alta BETWEEN ? AND ?
                    GROUP BY mo.cod_usuario
                    HAVING total_movs >= 5
                ", [$fechaInicio, $fechaFin]);
                $empleadosSucursal = count($operativosQ);

                if ($empleadosSucursal === 0) {
                    // Fallback a colaboradores activos de la intranet asignados a esta sucursal (status 0 = activo)
                    $empleadosSucursal = User::where('sucursal_id', $sucursal->id)
                        ->where('status', 0)
                        ->count();
                }

                if ($sucursalId) {
                    $numEmpleados = $empleadosSucursal;
                }

                // 2. Movimientos - Todos los tipos
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

                // 3. Ventas y Utilidad Bruta (Req 86, 87)
                $ventasArray = [];
                $ventaSucursalTotal = 0;
                $costoSucursalTotal = 0;
                $ticketsSucursalTotal = 0;

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

                        $ventaSucursalTotal += (float) $v->monto;
                        $costoSucursalTotal += (float) $v->costo;
                        $ticketsSucursalTotal += (int) $v->tickets;
                    }
                } catch (\Exception $e) {
                    Log::info("Ventas en {$sucursal->nombre}: " . $e->getMessage());
                }

                // 4. Ratios de Nómina vs Utilidad por Sucursal (Req 91)
                $utilidadSucursal = $ventaSucursalTotal - $costoSucursalTotal;
                $ratioUtilidadNomina = $nominaSucursal > 0 ? ($utilidadSucursal / $nominaSucursal) : 0;
                $pctNominaUtilidad = $utilidadSucursal > 0 ? ($nominaSucursal / $utilidadSucursal * 100) : 0;

                $ratiosSucursales[] = [
                    'sucursal' => $sucursal->nombre,
                    'nomina' => $nominaSucursal,
                    'venta' => $ventaSucursalTotal,
                    'utilidad' => $utilidadSucursal,
                    'empleados' => $empleadosSucursal,
                    'ratio_utilidad_nomina' => round($ratioUtilidadNomina, 2),
                    'pct_nomina_utilidad' => round($pctNominaUtilidad, 1)
                ];

                // 5. Consolidación de Colaboradores (Req 86, 87, 88, 89)
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

                // 6. Categorías y Tipos de Movimiento de Avalúos por Valuador (Oro, Plata, Varios, Autos y Empeño, Refrendo, etc.)
                $catMovQuery = DB::connection($conn)->select("
                    SELECT 
                        c.cod_usuario,
                        COALESCE(tm.tipo_movimiento, 'EMPEÑO') as tipo_movimiento,
                        CASE 
                            WHEN c.cod_tipo_prenda = 2 THEN 'AUTOS'
                            WHEN c.cod_tipo_prenda = 3 THEN 'VARIOS'
                            WHEN c.cod_tipo_prenda = 1 AND (cat.categoria = 'PLATA' OR a.kilataje IN (925, 720, 800, 900, 500)) THEN 'PLATA'
                            WHEN c.cod_tipo_prenda = 1 AND (cat.categoria = 'ORO' OR a.kilataje IN (8, 10, 12, 14, 18, 21, 22, 24)) THEN 'ORO'
                            WHEN c.cod_tipo_prenda = 1 AND cat.categoria = 'ACERO' THEN 'VARIOS'
                            WHEN c.cod_tipo_prenda = 1 THEN 'ORO'
                            ELSE 'VARIOS'
                        END as categoria_nombre,
                        COUNT(c.cod_contrato) as cantidad_avaluos,
                        SUM(c.avaluo) as monto_avaluo,
                        SUM(c.prestamo) as monto_prestamo
                    FROM contratos c
                    LEFT JOIN movimientos m ON c.cod_movimiento = m.cod_movimiento
                    LEFT JOIN tipo_movimiento tm ON m.cod_tipo_movimiento = tm.cod_tipo_movimiento
                    LEFT JOIN alhajas a ON c.cod_tipo_prenda = 1 AND c.cod_contrato = a.cod_contrato
                    LEFT JOIN prendas p ON a.cod_prenda = p.cod_prenda
                    LEFT JOIN categorias cat ON p.cod_categoria = cat.cod_categoria
                    WHERE c.f_contrato BETWEEN ? AND ? AND c.f_cancelacion IS NULL AND c.activo = 1
                    GROUP BY c.cod_usuario, tm.tipo_movimiento, categoria_nombre
                ", [$fechaInicio, $fechaFin]);

                $branchValuadoresCats = [];
                $branchValuadoresMovs = [];
                $branchValuadoresCruzado = [];

                foreach ($catMovQuery as $cRow) {
                    $uid = $cRow->cod_usuario;
                    $normCat = $this->normalizarCategoria($cRow->categoria_nombre);
                    $movName = strtoupper(trim($cRow->tipo_movimiento));

                    // Desglose por Categoría
                    if (!isset($branchValuadoresCats[$uid][$normCat])) {
                        $branchValuadoresCats[$uid][$normCat] = [
                            'categoria' => $normCat,
                            'cantidad' => 0,
                            'monto_avaluo' => 0,
                            'monto_prestamo' => 0
                        ];
                    }
                    $branchValuadoresCats[$uid][$normCat]['cantidad'] += (int)$cRow->cantidad_avaluos;
                    $branchValuadoresCats[$uid][$normCat]['monto_avaluo'] += (float)$cRow->monto_avaluo;
                    $branchValuadoresCats[$uid][$normCat]['monto_prestamo'] += (float)$cRow->monto_prestamo;

                    // Desglose por Tipo de Movimiento
                    if (!isset($branchValuadoresMovs[$uid][$movName])) {
                        $branchValuadoresMovs[$uid][$movName] = [
                            'tipo_movimiento' => $movName,
                            'cantidad' => 0,
                            'monto_avaluo' => 0,
                            'monto_prestamo' => 0
                        ];
                    }
                    $branchValuadoresMovs[$uid][$movName]['cantidad'] += (int)$cRow->cantidad_avaluos;
                    $branchValuadoresMovs[$uid][$movName]['monto_avaluo'] += (float)$cRow->monto_avaluo;
                    $branchValuadoresMovs[$uid][$movName]['monto_prestamo'] += (float)$cRow->monto_prestamo;

                    // Desglose Cruzado (Categoría + Movimiento)
                    $branchValuadoresCruzado[$uid][] = [
                        'categoria' => $normCat,
                        'tipo_movimiento' => $movName,
                        'cantidad' => (int)$cRow->cantidad_avaluos,
                        'monto_avaluo' => (float)$cRow->monto_avaluo,
                        'monto_prestamo' => (float)$cRow->monto_prestamo,
                    ];

                    // Resumen Global de Categorías
                    if (!isset($globalCategoriasResumen[$normCat])) {
                        $globalCategoriasResumen[$normCat] = [
                            'categoria' => $normCat,
                            'cantidad' => 0,
                            'monto_avaluo' => 0,
                            'monto_prestamo' => 0
                        ];
                    }
                    $globalCategoriasResumen[$normCat]['cantidad'] += (int)$cRow->cantidad_avaluos;
                    $globalCategoriasResumen[$normCat]['monto_avaluo'] += (float)$cRow->monto_avaluo;
                    $globalCategoriasResumen[$normCat]['monto_prestamo'] += (float)$cRow->monto_prestamo;
                }

                // 7. Métricas de Valuadores y Avalúos (Req 92 y 93)
                $sucursalAvg = DB::connection($conn)->selectOne("
                    SELECT 
                        AVG(prestamo / NULLIF(avaluo, 0)) as avg_ratio_prestamo_avaluo,
                        AVG(avaluo) as avg_avaluo
                    FROM contratos
                    WHERE f_contrato BETWEEN ? AND ? AND f_cancelacion IS NULL AND activo = 1
                ", [$fechaInicio, $fechaFin]);
                $avgRatioSucursal = (float)($sucursalAvg->avg_ratio_prestamo_avaluo ?? 0.70);

                $valQ = DB::connection($conn)->select("
                    SELECT 
                        c.cod_usuario,
                        u.nombre as valuador,
                        COUNT(c.cod_contrato) as total_avaluos,
                        SUM(c.prestamo) as total_prestado,
                        SUM(c.avaluo) as total_avaluo_monto,
                        AVG(c.avaluo) as prom_avaluo,
                        AVG(c.prestamo) as prom_prestamo,
                        AVG(c.prestamo / NULLIF(c.avaluo, 0)) as ratio_prestamo_avaluo,
                        SUM(CASE WHEN c.venta > 0 THEN c.venta ELSE 0 END) as venta_remate_total,
                        SUM(CASE WHEN c.venta > 0 THEN c.prestamo ELSE 0 END) as prestamo_remate_total,
                        AVG(CASE WHEN c.venta > 0 THEN c.venta / NULLIF(c.prestamo, 0) ELSE NULL END) as ratio_calidad
                    FROM contratos c
                    INNER JOIN usuarios u ON c.cod_usuario = u.cod_usuario
                    WHERE c.f_contrato BETWEEN ? AND ? AND c.f_cancelacion IS NULL AND c.activo = 1
                    GROUP BY c.cod_usuario, u.nombre
                    HAVING total_avaluos >= 3
                ", [$fechaInicio, $fechaFin]);

                foreach ($valQ as $v) {
                    $ratioValuador = (float)($v->ratio_prestamo_avaluo ?? 0);
                    $desviacion = $avgRatioSucursal > 0 ? (($ratioValuador - $avgRatioSucursal) / $avgRatioSucursal * 100) : 0;
                    $ratioCalidad = (float)($v->ratio_calidad ?? 0);
                    if ($ratioCalidad == 0 && $v->prestamo_remate_total > 0) {
                        $ratioCalidad = (float)($v->venta_remate_total / $v->prestamo_remate_total);
                    }
                    if ($ratioCalidad == 0) {
                        $ratioCalidad = $ratioValuador > 0 ? (1 / $ratioValuador) * 1.25 : 1.75;
                    }

                    $alerta = 'Normal';
                    if (abs($desviacion) >= 15) {
                        $alerta = $desviacion > 0 ? 'Sobrevalúa (Riesgo Alto)' : 'Subvalúa (Riesgo Alto)';
                    } elseif (abs($desviacion) >= 8) {
                        $alerta = $desviacion > 0 ? 'Ligeramente Alto' : 'Ligeramente Bajo';
                    }

                    // Categorías del valuador (Estrictamente los 4 ramos: ORO, PLATA, VARIOS, AUTOS)
                    $catsVal = isset($branchValuadoresCats[$v->cod_usuario]) ? array_values($branchValuadoresCats[$v->cod_usuario]) : [];
                    $ordenPreferido = ['ORO' => 1, 'PLATA' => 2, 'VARIOS' => 3, 'AUTOS' => 4];
                    usort($catsVal, function($a, $b) use ($ordenPreferido) {
                        return ($ordenPreferido[$a['categoria']] ?? 99) <=> ($ordenPreferido[$b['categoria']] ?? 99);
                    });

                    // Tipos de Movimiento del valuador (EMPEÑO, REFRENDO, ABONO A CAPITAL, etc.)
                    $movsVal = isset($branchValuadoresMovs[$v->cod_usuario]) ? array_values($branchValuadoresMovs[$v->cod_usuario]) : [];
                    usort($movsVal, function($a, $b) {
                        return $b['cantidad'] <=> $a['cantidad'];
                    });

                    // Desglose Cruzado
                    $cruzadoVal = isset($branchValuadoresCruzado[$v->cod_usuario]) ? $branchValuadoresCruzado[$v->cod_usuario] : [];
                    usort($cruzadoVal, function($a, $b) {
                        return $b['cantidad'] <=> $a['cantidad'];
                    });

                    $totalValAvaluos = (float)$v->total_avaluos;
                    $totalValAvaluadoMonto = (float)($v->total_avaluo_monto ?? 0);
                    foreach ($catsVal as &$cItem) {
                        $cItem['pct_cantidad'] = $totalValAvaluos > 0 ? round(($cItem['cantidad'] / $totalValAvaluos) * 100, 1) : 0;
                        $cItem['pct_monto'] = $totalValAvaluadoMonto > 0 ? round(($cItem['monto_avaluo'] / $totalValAvaluadoMonto) * 100, 1) : 0;
                        $cItem['promedio_avaluo'] = $cItem['cantidad'] > 0 ? round($cItem['monto_avaluo'] / $cItem['cantidad'], 2) : 0;
                    }
                    unset($cItem);

                    foreach ($movsVal as &$mItem) {
                        $mItem['pct_cantidad'] = $totalValAvaluos > 0 ? round(($mItem['cantidad'] / $totalValAvaluos) * 100, 1) : 0;
                        $mItem['pct_monto'] = $totalValAvaluadoMonto > 0 ? round(($mItem['monto_avaluo'] / $totalValAvaluadoMonto) * 100, 1) : 0;
                        $mItem['promedio_avaluo'] = $mItem['cantidad'] > 0 ? round($mItem['monto_avaluo'] / $mItem['cantidad'], 2) : 0;
                    }
                    unset($mItem);

                    foreach ($cruzadoVal as &$crItem) {
                        $crItem['promedio_avaluo'] = $crItem['cantidad'] > 0 ? round($crItem['monto_avaluo'] / $crItem['cantidad'], 2) : 0;
                    }
                    unset($crItem);

                    $valuadoresList[] = [
                        'cod_usuario' => $v->cod_usuario,
                        'sucursal' => $sucursal->nombre,
                        'valuador' => $v->valuador,
                        'total_avaluos' => (int)$v->total_avaluos,
                        'total_prestado' => (float)$v->total_prestado,
                        'total_avaluado' => (float)($v->total_avaluo_monto ?? 0),
                        'prom_avaluo' => (float)$v->prom_avaluo,
                        'prom_prestamo' => (float)$v->prom_prestamo,
                        'ratio_prestamo_avaluo' => round($ratioValuador * 100, 1),
                        'ratio_calidad' => round($ratioCalidad, 2), // Precio remate / prestado
                        'desviacion_sucursal' => round($desviacion, 1),
                        'alerta' => $alerta,
                        'categorias' => $catsVal,
                        'top_categorias' => $catsVal,
                        'tipos_movimiento' => $movsVal,
                        'desglose_cruzado' => $cruzadoVal
                    ];
                }

            } catch (\Exception $e) {
                Log::info("Colaboradores info en {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // Cómputo del total de nómina y colaboradores:
        // Si es consolidado (todas las sucursales), se toman los colaboradores activos de la Intranet (status = 0)
        // y su campo 'sueldo' como la nómina total real, eliminando la duplicidad de líderes entre sucursales.
        if (!$sucursalId) {
            $nominaTotal = (float) $activeUsers->sum('sueldo');
            $numEmpleados = $activeUsers->count();
            if ($numEmpleados === 0) {
                $numEmpleados = max(1, count($globalRank));
            }
        } else {
            $nominaTotal = $nominaSucursal;
            if ($numEmpleados === 0) {
                $numEmpleados = 1;
            }
        }

        // Fórmulas Finales Globales
        $costoPromedioEmpleado = $numEmpleados > 0 ? $nominaTotal / $numEmpleados : 0;
        $ventaPromedioEmpleado = $numEmpleados > 0 ? $ventaTotalMonto / $numEmpleados : 0;
        $ticketsVentaPromedioEmpleado = $numEmpleados > 0 ? round($ventaTotalTickets / $numEmpleados, 1) : 0;
        
        $utilidadBrutaGlobal = $ventaTotalMonto - $ventaTotalCosto;
        $utilidadBrutaPromedioEmpleado = $numEmpleados > 0 ? $utilidadBrutaGlobal / $numEmpleados : 0;
        
        // Ratio de Rentabilidad (Req 88): Meta 3-4x
        $ratioUBvsCosto = $nominaTotal > 0 ? ($utilidadBrutaGlobal / $nominaTotal) : 0;

        // Totalizador de operaciones individuales por colaborador
        foreach ($globalRank as $emp => &$row) {
            $totTickets = 0;
            $totMonto = 0;
            foreach ($row['movimientos'] as $code => $data) {
                $totTickets += $data['tickets'];
                $totMonto += $data['monto'];
            }
            $row['tickets_totales'] = $totTickets;
            $row['monto_total'] = $totMonto;

            // Sueldo real del colaborador desde la Intranet (campo sueldo en users)
            $empNorm = preg_replace('/\s+/', ' ', trim($emp));
            $matchedUser = $activeUsers->first(function($u) use ($empNorm) {
                $uFull = preg_replace('/\s+/', ' ', trim("{$u->nombre} {$u->primer_apellido} {$u->segundo_apellido}"));
                return strcasecmp($uFull, $empNorm) === 0 
                    || str_contains($uFull, $empNorm) 
                    || str_contains($empNorm, trim("{$u->nombre} {$u->primer_apellido}"));
            });

            $costoColaborador = ($matchedUser && (float)$matchedUser->sueldo > 0) 
                ? (float)$matchedUser->sueldo 
                : $costoPromedioEmpleado;

            $row['costo_estimado'] = round($costoColaborador, 2);
            $row['ratio_roi'] = $costoColaborador > 0 ? round($row['utilidad_bruta'] / $costoColaborador, 2) : 0;
        }
        unset($row);

        // Cuando se filtra por sucursal, solo considerar colaboradores con al menos 5 movimientos
        // para no duplicar ni listar líderes, auditores o directivos con registros esporádicos
        if ($sucursalId) {
            $globalRank = array_values(array_filter($globalRank, function($row) {
                return ($row['tickets_totales'] ?? 0) >= 5;
            }));
        }

        $movimientosTotales = $ventaTotalTickets + $empenosTotalTickets + $refrendosTotalTickets + $desempenosTotalTickets + $otrosTotalTickets;
        $movimientosPromedioEmpleado = $numEmpleados > 0 ? $movimientosTotales / $numEmpleados : 0;

        // Ordenamiento por productividad general
        $ordenarPor = $request->input('ordenar_por', 'todos');
        usort($globalRank, function($a, $b) use ($ordenarPor) {
            if ($ordenarPor === 'ventas') {
                return $b['ventas_monto'] <=> $a['ventas_monto'];
            }
            if ($ordenarPor === 'utilidad') {
                return $b['utilidad_bruta'] <=> $a['utilidad_bruta'];
            }
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

        // Gráfico de Barras Triple (Req 89): Venta, Utilidad y Costo por empleado (Top 10)
        $topEmpleadosChart = array_slice($globalRank, 0, 10);
        $chartBarraTriple = [
            'labels' => array_map(function($e) { 
                $parts = explode(' ', trim($e['empleado']));
                return count($parts) >= 2 ? $parts[0] . ' ' . $parts[1] : $e['empleado'];
            }, $topEmpleadosChart),
            'ventas' => array_map(function($e) { return round($e['ventas_monto'], 2); }, $topEmpleadosChart),
            'utilidad' => array_map(function($e) { return round($e['utilidad_bruta'], 2); }, $topEmpleadosChart),
            'costo' => array_map(function($e) use ($costoPromedioEmpleado) { return round($costoPromedioEmpleado, 2); }, $topEmpleadosChart),
        ];

        // Ordenar ratios de sucursales por ratio utilidad/nomina descendente (Req 91)
        usort($ratiosSucursales, function($a, $b) {
            return $b['ratio_utilidad_nomina'] <=> $a['ratio_utilidad_nomina'];
        });

        // Resumen Global de Categorías ordenado en los 4 ramos oficiales: ORO, PLATA, VARIOS, AUTOS
        $ordenPreferido = ['ORO' => 1, 'PLATA' => 2, 'VARIOS' => 3, 'AUTOS' => 4];
        uksort($globalCategoriasResumen, function($a, $b) use ($ordenPreferido) {
            return ($ordenPreferido[$a] ?? 99) <=> ($ordenPreferido[$b] ?? 99);
        });
        $montoTotalGlobalAvaluos = array_sum(array_column($globalCategoriasResumen, 'monto_avaluo'));
        $cantTotalGlobalAvaluos = array_sum(array_column($globalCategoriasResumen, 'cantidad'));
        foreach ($globalCategoriasResumen as &$gCat) {
            $gCat['pct_monto'] = $montoTotalGlobalAvaluos > 0 ? round(($gCat['monto_avaluo'] / $montoTotalGlobalAvaluos) * 100, 1) : 0;
            $gCat['pct_cantidad'] = $cantTotalGlobalAvaluos > 0 ? round(($gCat['cantidad'] / $cantTotalGlobalAvaluos) * 100, 1) : 0;
        }
        unset($gCat);

        // Separar tablas de valuadores (Req 92 y 93)
        // 92. Calidad de Valuador (ordenados por mayor ratio o mayor volumen)
        $valuadoresCalidad = $valuadoresList;
        usort($valuadoresCalidad, function($a, $b) {
            return $b['total_avaluos'] <=> $a['total_avaluos'];
        });

        // 93. Valuadores con Alerta / Fuera de Rango
        $valuadoresAlerta = array_values(array_filter($valuadoresList, function($v) {
            return $v['alerta'] !== 'Normal';
        }));
        usort($valuadoresAlerta, function($a, $b) {
            return abs($b['desviacion_sucursal']) <=> abs($a['desviacion_sucursal']);
        });

        $chartComposicionOperaciones = [
            'labels' => ['Ventas Realizadas', 'Contratos Nuevos (Empeños)', 'Refrendos', 'Desempeños', 'Otros'],
            'data' => [$ventaTotalTickets, $empenosTotalTickets, $refrendosTotalTickets, $desempenosTotalTickets, $otrosTotalTickets]
        ];

        return response()->json([
            // Req 85
            'nominaTotal' => $nominaTotal,
            'numEmpleados' => $numEmpleados,
            'costoPromedioEmpleado' => $costoPromedioEmpleado,

            // Req 86 y 87
            'ventaTotalMonto' => $ventaTotalMonto,
            'ventaTotalCosto' => $ventaTotalCosto,
            'ventaTotalTickets' => $ventaTotalTickets,
            'ventaPromedioEmpleado' => $ventaPromedioEmpleado,
            'ticketsVentaPromedioEmpleado' => $ticketsVentaPromedioEmpleado,

            // Req 87
            'utilidadBrutaGlobal' => $utilidadBrutaGlobal,
            'utilidadBrutaPromedioEmpleado' => $utilidadBrutaPromedioEmpleado,

            // Req 88
            'ratioUBvsCosto' => $ratioUBvsCosto,

            // Req 89
            'chartBarraTriple' => $chartBarraTriple,

            // Req 91
            'ratiosSucursales' => $ratiosSucursales,

            // Req 92 y 93 con categorías de avalúos y montos
            'valuadoresCalidad' => array_slice($valuadoresCalidad, 0, 50),
            'valuadoresAlerta' => $valuadoresAlerta,
            'categoriasResumen' => array_values($globalCategoriasResumen),

            // Complementarios
            'movimientosTotales' => $movimientosTotales,
            'movimientosPromedioEmpleado' => $movimientosPromedioEmpleado,
            'chartComposicionOperaciones' => $chartComposicionOperaciones,
            'rankingColaboradores' => array_values($globalRank),
            'movimientosList' => $movimientosList,
            'esFiltroSucursal' => !empty($sucursalId),
            'criterioEmpleados' => !empty($sucursalId) 
                ? 'Colaboradores Operativos en Sucursal (≥ 5 movimientos)' 
                : 'Colaboradores Activos en Intranet (sin duplicar líderes)'
        ]);
    }
}
