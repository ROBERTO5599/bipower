<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;
use App\Services\PowerBIPermissionService;
use Carbon\Carbon;

class InventarioRematadoController extends Controller
{
    /**
     * Muestra la vista principal del Inventario Rematado.
     */
    public function index(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        $fechaFin = $request->input('fecha_fin', now()->toDateString());
        $regionales = PowerBIPermissionService::getRegionales();
        $regionalUserId = $request->input('regional_user_id');
        $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];

        $sucursales = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);
        if ($sucursales->isEmpty()) {
            $sucursales = Sucursal::whereNotNull('id_valora_mas')
                ->whereIn('id_valora_mas', $availableDbs)
                ->orderBy('nombre')
                ->get();
        } else {
            $sucursales = $sucursales->filter(fn($s) => in_array((int)$s->id_valora_mas, $availableDbs))->sortBy('nombre')->values();
        }

        return view('inventario-rematado.index', compact('fechaInicio', 'fechaFin', 'sucursales', 'regionales', 'regionalUserId'));
    }

    /**
     * Obtiene los datos consolidados del inventario rematado tomando en cuenta el histórico
     * y la fecha p_venta (pase a venta / remate).
     */
    public function data(Request $request)
    {
        try {
            ini_set('max_execution_time', 120);

            $rawInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
            $fechaInicio = (strlen($rawInicio) <= 10 ? $rawInicio . ' 00:00:00' : $rawInicio);

            $rawFin = $request->input('fecha_fin', now()->toDateString());
            $fechaFin = (strlen($rawFin) <= 10 ? $rawFin . ' 23:59:59' : $rawFin);

            $regionalUserId = $request->input('regional_user_id');
            $availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];

            $sucursales = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);
            if ($sucursales->isEmpty()) {
                $sucursales = Sucursal::whereNotNull('id_valora_mas')
                    ->whereIn('id_valora_mas', $availableDbs)
                    ->orderBy('nombre')
                    ->get();
            }

            $sucursalId = $request->input('sucursal_id');
            $filtroCategoria = strtolower($request->input('categoria', 'todas'));
            $filtroEstatus = $request->input('estatus_actual', 'todos');

            $isAllSucursales = empty($sucursalId) || $sucursalId === 'all' || $sucursalId === 'todas';

            $sucursalesSeleccionadas = !$isAllSucursales
                ? $sucursales->filter(fn($s) => (string)$s->id_valora_mas === (string)$sucursalId)
                : $sucursales->filter(fn($s) => in_array((int)$s->id_valora_mas, $availableDbs));

            $baseConfig = Config::get('database.connections.mysql');

            // Acumuladores Globales
            $totalArticulos = 0;
            $contratosSet = [];
            $totalPrestamo = 0.0;
            $totalPrecio = 0.0;
            $totalAvaluo = 0.0;

            // Desglose por Categoría
            $categoriasMap = [
                'Oro' => ['articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'avaluo' => 0.0],
                'Plata' => ['articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'avaluo' => 0.0],
                'Varios' => ['articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'avaluo' => 0.0],
                'Autos' => ['articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'avaluo' => 0.0],
            ];

            // Desglose por Estatus Actual
            $estatusActualMap = [
                'Piso de Venta' => ['codigo' => 9, 'articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'color' => '#f59e0b'],
                'Vendida' => ['codigo' => 3, 'articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'color' => '#10b981'],
                'Apartada' => ['codigo' => 4, 'articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'color' => '#6366f1'],
                'Baja / Salida' => ['codigo' => 10, 'articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'color' => '#ef4444'],
                'Desempeño Ext.' => ['codigo' => 6, 'articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'color' => '#06b6d4'],
                'Otros' => ['codigo' => 0, 'articulos' => 0, 'prestamo' => 0.0, 'precio' => 0.0, 'color' => '#64748b'],
            ];

            // Por Sucursal
            $sucursalesList = [];

            // Serie temporal día a día
            $diarioMap = [];
            $cCur = Carbon::parse($rawInicio);
            $cEnd = Carbon::parse($rawFin);
            while ($cCur->lte($cEnd)) {
                $key = $cCur->toDateString();
                $diarioMap[$key] = [
                    'fecha' => $key,
                    'fecha_formato' => $cCur->format('d/m'),
                    'dia_semana' => $cCur->translatedFormat('D'),
                    'articulos' => 0,
                    'prestamo' => 0.0,
                    'precio' => 0.0,
                ];
                $cCur->addDay();
            }

            // Listado de Artículos (hasta 2500 para el datatable)
            $articulosDetallados = [];

            foreach ($sucursalesSeleccionadas as $sucursal) {
                try {
                    $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                    $connectionName = 'inv_rem_dyn_' . $sucursal->id_valora_mas;

                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.$connectionName", $config);
                    DB::purge($connectionName);

                    $qRematados = "
                        SELECT 
                            t.tipo_prenda,
                            t.categoria,
                            t.cod_contrato,
                            t.cod_prenda,
                            t.descripcion,
                            t.observaciones,
                            t.prestamo,
                            t.precio,
                            t.avaluo,
                            t.fecha_remate,
                            t.cod_estatus_actual,
                            COALESCE(ep.estatus_prenda, 'DESCONOCIDO') as estatus_actual_nombre
                        FROM (
                            -- 1. Alhajas (Oro / Plata / Otros)
                            SELECT 
                                'Oro' as tipo_prenda,
                                CASE 
                                    WHEN a.kilataje BETWEEN 8 AND 26 THEN 'Oro' 
                                    WHEN a.kilataje BETWEEN 500 AND 999 THEN 'Plata' 
                                    ELSE 'Oro' 
                                END as categoria,
                                COALESCE(h.cod_contrato, a.cod_contrato) as cod_contrato,
                                h.cod_prenda,
                                COALESCE(pre.prenda, 'ALHAJA') as descripcion,
                                a.observaciones,
                                h.prestamo,
                                COALESCE(h.precio, a.precio, 0) as precio,
                                COALESCE(h.avaluo, a.avaluo, 0) as avaluo,
                                COALESCE(a.p_venta, h.f_movimiento) as fecha_remate,
                                a.cod_estatus_prenda as cod_estatus_actual
                            FROM (
                                SELECT h.cod_prenda, h.prestamo, h.precio, h.avaluo, h.f_movimiento, h.cod_contrato,
                                       ROW_NUMBER() OVER (PARTITION BY h.cod_prenda ORDER BY h.f_movimiento DESC, h.id_historico DESC) as rn
                                FROM historico_articulos h
                                WHERE h.cod_tipo_prenda = 1 
                                  AND (h.cod_estatus_prenda = 9 OR h.comentario LIKE '%Pase a Piso de Venta%')
                            ) h
                            INNER JOIN alhajas a ON a.cod_alhaja = h.cod_prenda
                            LEFT JOIN prendas pre ON pre.cod_prenda = a.cod_prenda AND pre.cod_tipo_prenda = 1
                            WHERE h.rn = 1 
                              AND COALESCE(a.p_venta, h.f_movimiento) BETWEEN :f1 AND :f2

                            UNION ALL

                            -- 2. Varios (Mercancía General)
                            SELECT 
                                'Varios' as tipo_prenda,
                                'Varios' as categoria,
                                COALESCE(h.cod_contrato, v.cod_contrato) as cod_contrato,
                                h.cod_prenda,
                                COALESCE(pre.prenda, 'VARIOS') as descripcion,
                                v.observaciones,
                                h.prestamo,
                                COALESCE(h.precio, v.precio, 0) as precio,
                                COALESCE(h.avaluo, v.avaluo, 0) as avaluo,
                                COALESCE(v.p_venta, h.f_movimiento) as fecha_remate,
                                v.cod_estatus_prenda as cod_estatus_actual
                            FROM (
                                SELECT h.cod_prenda, h.prestamo, h.precio, h.avaluo, h.f_movimiento, h.cod_contrato,
                                       ROW_NUMBER() OVER (PARTITION BY h.cod_prenda ORDER BY h.f_movimiento DESC, h.id_historico DESC) as rn
                                FROM historico_articulos h
                                WHERE h.cod_tipo_prenda = 3 
                                  AND (h.cod_estatus_prenda = 9 OR h.comentario LIKE '%Pase a Piso de Venta%')
                            ) h
                            INNER JOIN varios v ON v.cod_varios = h.cod_prenda
                            LEFT JOIN prendas pre ON pre.cod_prenda = v.cod_prenda AND pre.cod_tipo_prenda = 3
                            WHERE h.rn = 1 
                              AND COALESCE(v.p_venta, h.f_movimiento) BETWEEN :f3 AND :f4

                            UNION ALL

                            -- 3. Autos
                            SELECT 
                                'Autos' as tipo_prenda,
                                'Autos' as categoria,
                                COALESCE(h.cod_contrato, au.cod_contrato) as cod_contrato,
                                h.cod_prenda,
                                COALESCE(pre.prenda, 'AUTO') as descripcion,
                                au.observaciones,
                                h.prestamo,
                                COALESCE(h.precio, au.precio, 0) as precio,
                                COALESCE(h.avaluo, au.avaluo, 0) as avaluo,
                                COALESCE(au.p_venta, h.f_movimiento) as fecha_remate,
                                au.cod_estatus_prenda as cod_estatus_actual
                            FROM (
                                SELECT h.cod_prenda, h.prestamo, h.precio, h.avaluo, h.f_movimiento, h.cod_contrato,
                                       ROW_NUMBER() OVER (PARTITION BY h.cod_prenda ORDER BY h.f_movimiento DESC, h.id_historico DESC) as rn
                                FROM historico_articulos h
                                WHERE h.cod_tipo_prenda = 2 
                                  AND (h.cod_estatus_prenda = 9 OR h.comentario LIKE '%Pase a Piso de Venta%')
                            ) h
                            INNER JOIN autos au ON au.cod_auto = h.cod_prenda
                            LEFT JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                            WHERE h.rn = 1 
                              AND COALESCE(au.p_venta, h.f_movimiento) BETWEEN :f5 AND :f6
                        ) t
                        LEFT JOIN estatus_prenda ep ON ep.cod_estatus_prenda = t.cod_estatus_actual
                    ";

                    $itemsSucursal = DB::connection($connectionName)->select($qRematados, [
                        ':f1' => $fechaInicio, ':f2' => $fechaFin,
                        ':f3' => $fechaInicio, ':f4' => $fechaFin,
                        ':f5' => $fechaInicio, ':f6' => $fechaFin,
                    ]);

                    $sucArticulos = 0;
                    $sucPrestamo = 0.0;
                    $sucPrecio = 0.0;
                    $sucAvaluo = 0.0;

                    foreach ($itemsSucursal as $row) {
                        $cat = $row->categoria;
                        if (!isset($categoriasMap[$cat])) {
                            $cat = ($row->tipo_prenda === 'Oro') ? 'Oro' : 'Varios';
                        }

                        // Filtro de categoría si se especificó
                        if ($filtroCategoria !== 'todas' && strtolower($cat) !== $filtroCategoria && strtolower($row->tipo_prenda) !== $filtroCategoria) {
                            continue;
                        }

                        $codEstatus = (int)$row->cod_estatus_actual;

                        // Filtro de estatus actual si se especificó
                        if ($filtroEstatus !== 'todos' && (string)$codEstatus !== (string)$filtroEstatus) {
                            continue;
                        }

                        $prestamoVal = (float)$row->prestamo;
                        $precioVal = (float)$row->precio;
                        $avaluoVal = (float)$row->avaluo;

                        // Acumular sucursal
                        $sucArticulos++;
                        $sucPrestamo += $prestamoVal;
                        $sucPrecio += $precioVal;
                        $sucAvaluo += $avaluoVal;

                        // Acumular global
                        $totalArticulos++;
                        $totalPrestamo += $prestamoVal;
                        $totalPrecio += $precioVal;
                        $totalAvaluo += $avaluoVal;

                        if ($row->cod_contrato) {
                            $contratosSet[$row->cod_contrato] = true;
                        }

                        // Categorías
                        $categoriasMap[$cat]['articulos']++;
                        $categoriasMap[$cat]['prestamo'] += $prestamoVal;
                        $categoriasMap[$cat]['precio'] += $precioVal;
                        $categoriasMap[$cat]['avaluo'] += $avaluoVal;

                        // Estatus Actual
                        if ($codEstatus === 9) {
                            $estatusActualMap['Piso de Venta']['articulos']++;
                            $estatusActualMap['Piso de Venta']['prestamo'] += $prestamoVal;
                            $estatusActualMap['Piso de Venta']['precio'] += $precioVal;
                        } elseif ($codEstatus === 3 || $codEstatus === 12) {
                            $estatusActualMap['Vendida']['articulos']++;
                            $estatusActualMap['Vendida']['prestamo'] += $prestamoVal;
                            $estatusActualMap['Vendida']['precio'] += $precioVal;
                        } elseif ($codEstatus === 4) {
                            $estatusActualMap['Apartada']['articulos']++;
                            $estatusActualMap['Apartada']['prestamo'] += $prestamoVal;
                            $estatusActualMap['Apartada']['precio'] += $precioVal;
                        } elseif ($codEstatus === 5 || $codEstatus === 10 || $codEstatus === 14) {
                            $estatusActualMap['Baja / Salida']['articulos']++;
                            $estatusActualMap['Baja / Salida']['prestamo'] += $prestamoVal;
                            $estatusActualMap['Baja / Salida']['precio'] += $precioVal;
                        } elseif ($codEstatus === 6) {
                            $estatusActualMap['Desempeño Ext.']['articulos']++;
                            $estatusActualMap['Desempeño Ext.']['prestamo'] += $prestamoVal;
                            $estatusActualMap['Desempeño Ext.']['precio'] += $precioVal;
                        } else {
                            $estatusActualMap['Otros']['articulos']++;
                            $estatusActualMap['Otros']['prestamo'] += $prestamoVal;
                            $estatusActualMap['Otros']['precio'] += $precioVal;
                        }

                        // Evolución diaria
                        $fDate = substr($row->fecha_remate, 0, 10);
                        if (isset($diarioMap[$fDate])) {
                            $diarioMap[$fDate]['articulos']++;
                            $diarioMap[$fDate]['prestamo'] += $prestamoVal;
                            $diarioMap[$fDate]['precio'] += $precioVal;
                        }

                        // Agregar al listado detallado (limitado a 2500 artículos para optimizar memoria en browser)
                        if (count($articulosDetallados) < 2500) {
                            $fCarbon = Carbon::parse($row->fecha_remate);
                            $margenArt = ($prestamoVal > 0) ? round((($precioVal - $prestamoVal) / $prestamoVal) * 100, 1) : 0;

                            $articulosDetallados[] = [
                                'sucursal' => $sucursal->nombre,
                                'cod_contrato' => $row->cod_contrato ? (string)$row->cod_contrato : '-',
                                'cod_prenda' => (int)$row->cod_prenda,
                                'tipo_prenda' => $row->tipo_prenda,
                                'categoria' => $cat,
                                'descripcion' => $row->descripcion,
                                'observaciones' => $row->observaciones ? trim($row->observaciones) : '',
                                'prestamo' => $prestamoVal,
                                'precio' => $precioVal,
                                'avaluo' => $avaluoVal,
                                'fecha_remate' => $row->fecha_remate,
                                'fecha_formato' => $fCarbon->format('d/m/Y'),
                                'cod_estatus_actual' => $codEstatus,
                                'estatus_actual' => $row->estatus_actual_nombre,
                                'margen_pct' => $margenArt,
                            ];
                        }
                    }

                    $sucursalesList[] = [
                        'id_valora_mas' => $sucursal->id_valora_mas,
                        'nombre' => $sucursal->nombre,
                        'articulos' => $sucArticulos,
                        'prestamo' => $sucPrestamo,
                        'precio' => $sucPrecio,
                        'avaluo' => $sucAvaluo,
                        'margen_monto' => $sucPrecio - $sucPrestamo,
                        'margen_pct' => ($sucPrestamo > 0) ? round((($sucPrecio - $sucPrestamo) / $sucPrestamo) * 100, 1) : 0,
                    ];

                } catch (\Exception $e) {
                    Log::error("Error querying inventario rematado for {$sucursal->nombre}: " . $e->getMessage());
                }
            }

            // Ordenar sucursales por monto de remate descendente
            usort($sucursalesList, function ($a, $b) {
                return $b['prestamo'] <=> $a['prestamo'];
            });

            // Ordenar artículos por fecha de remate descendente
            usort($articulosDetallados, function ($a, $b) {
                return strcmp($b['fecha_remate'], $a['fecha_remate']);
            });

            $margenMonto = $totalPrecio - $totalPrestamo;
            $margenPct = ($totalPrestamo > 0) ? round(($margenMonto / $totalPrestamo) * 100, 1) : 0.0;
            $totalContratos = count($contratosSet);

            return response()->json([
                'success' => true,
                'fecha_inicio' => $rawInicio,
                'fecha_fin' => $rawFin,
                'rango_fechas_label' => Carbon::parse($rawInicio)->format('d/m/Y') . ' al ' . Carbon::parse($rawFin)->format('d/m/Y'),
                'kpis' => [
                    'total_articulos' => $totalArticulos,
                    'total_contratos' => $totalContratos,
                    'total_prestamo' => $totalPrestamo,
                    'total_precio' => $totalPrecio,
                    'total_avaluo' => $totalAvaluo,
                    'margen_monto' => $margenMonto,
                    'margen_pct' => $margenPct,
                ],
                'por_categoria' => $categoriasMap,
                'por_estatus_actual' => $estatusActualMap,
                'por_sucursal' => $sucursalesList,
                'evolucion_diaria' => array_values($diarioMap),
                'articulos' => $articulosDetallados,
            ]);

        } catch (\Exception $e) {
            Log::error("Error in InventarioRematadoController@data: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
