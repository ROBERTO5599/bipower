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
        if ($sucursales->isEmpty() && auth()->check() && auth()->user()->getPbiRoleType() === 'super_admin') {
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

            // Catálogo oficial de Estatus Actual para desplegar todos sin agrupar en "Otros"
            $estatusCatalogo = [
                9  => ['label' => 'Piso de Venta',        'color' => '#f59e0b'],
                3  => ['label' => 'Vendida',              'color' => '#10b981'],
                12 => ['label' => 'Venta Crédito',        'color' => '#059669'],
                4  => ['label' => 'Apartada',             'color' => '#6366f1'],
                10 => ['label' => 'Salida de Inventario', 'color' => '#f87171'],
                5  => ['label' => 'Baja',                 'color' => '#ef4444'],
                14 => ['label' => 'Incobrable',           'color' => '#991b1b'],
                6  => ['label' => 'Desempeño Ext.',       'color' => '#06b6d4'],
                1  => ['label' => 'Vigente',              'color' => '#3b82f6'],
                2  => ['label' => 'Vencida',              'color' => '#f97316'],
                7  => ['label' => 'Cancelada',            'color' => '#64748b'],
                8  => ['label' => 'Pre Venta',            'color' => '#14b8a6'],
                11 => ['label' => 'Solicitud de Crédito', 'color' => '#8b5cf6'],
                13 => ['label' => 'Dev. Garantía',        'color' => '#ec4899'],
            ];
            $estatusActualMap = [];

            // Ranking de Familias
            $familiasMap = [];

            // Por Sucursal
            $sucursalesList = [];

            // Serie temporal día a día
            $diarioMap = [];
            $cCur = Carbon::parse($rawInicio);
            $cEnd = Carbon::parse($rawFin);
            $diasEspanol = [
                'Mon' => 'Lun',
                'Tue' => 'Mar',
                'Wed' => 'Mié',
                'Thu' => 'Jue',
                'Fri' => 'Vie',
                'Sat' => 'Sáb',
                'Sun' => 'Dom',
            ];
            while ($cCur->lte($cEnd)) {
                $key = $cCur->toDateString();
                $diaIng = $cCur->format('D');
                $diarioMap[$key] = [
                    'fecha' => $key,
                    'fecha_formato' => $cCur->format('d/m'),
                    'dia_semana' => $diasEspanol[$diaIng] ?? $diaIng,
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
                                COALESCE(NULLIF(TRIM(c.categoria), ''), '') as categoria_prenda,
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
                            LEFT JOIN categorias c ON c.cod_categoria = pre.cod_categoria
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
                                COALESCE(NULLIF(TRIM(c.categoria), ''), '') as categoria_prenda,
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
                            LEFT JOIN categorias c ON c.cod_categoria = pre.cod_categoria
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
                                COALESCE(NULLIF(TRIM(c.categoria), ''), '') as categoria_prenda,
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
                            LEFT JOIN categorias c ON c.cod_categoria = pre.cod_categoria
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

                        // Estatus Actual - Despliegue individual sin agrupar en "Otros"
                        if (isset($estatusCatalogo[$codEstatus])) {
                            $eLabel = $estatusCatalogo[$codEstatus]['label'];
                            $eColor = $estatusCatalogo[$codEstatus]['color'];
                        } else {
                            $rawNombre = trim($row->estatus_actual_nombre ?? '');
                            $eLabel = (!empty($rawNombre) && $rawNombre !== 'DESCONOCIDO')
                                ? mb_convert_case($rawNombre, MB_CASE_TITLE, 'UTF-8')
                                : ('Estatus ' . $codEstatus);
                            $eColor = '#64748b';
                        }

                        if (!isset($estatusActualMap[$eLabel])) {
                            $estatusActualMap[$eLabel] = [
                                'codigo' => $codEstatus,
                                'label' => $eLabel,
                                'articulos' => 0,
                                'prestamo' => 0.0,
                                'precio' => 0.0,
                                'color' => $eColor,
                            ];
                        }
                        $estatusActualMap[$eLabel]['articulos']++;
                        $estatusActualMap[$eLabel]['prestamo'] += $prestamoVal;
                        $estatusActualMap[$eLabel]['precio'] += $precioVal;

                        // Evolución diaria
                        $fDate = substr($row->fecha_remate, 0, 10);
                        if (isset($diarioMap[$fDate])) {
                            $diarioMap[$fDate]['articulos']++;
                            $diarioMap[$fDate]['prestamo'] += $prestamoVal;
                            $diarioMap[$fDate]['precio'] += $precioVal;
                        }

                        // Normalización de Familia de Prenda para Ranking
                        $tipoP = $row->tipo_prenda;
                        $cCat = $row->categoria;
                        $cPrendaRaw = strtoupper(trim($row->categoria_prenda ?? ''));
                        $pDescRaw = strtoupper(trim($row->descripcion ?? ''));

                        if ($tipoP === 'Oro') {
                            $nombreFam = ($cCat === 'Plata') ? "Plata: {$pDescRaw}" : "Oro: {$pDescRaw}";
                        } elseif ($tipoP === 'Autos') {
                            $nombreFam = (!empty($cPrendaRaw) && $cPrendaRaw !== 'AUTOS') ? "Vehículos: {$cPrendaRaw}" : "Vehículos: {$pDescRaw}";
                        } else {
                            $fClean = $cPrendaRaw;
                            if (in_array($fClean, ['CELULAR', 'CELULARES', 'CELURARES'])) $fClean = 'CELULARES';
                            elseif (in_array($fClean, ['PANTALLA', 'PANTALLAS'])) $fClean = 'PANTALLAS';
                            elseif (in_array($fClean, ['EQUIPO DE COMPTO', 'EQUIPO DE COMPUTO', 'COMPUTADORA', 'COMPUTADORAS'])) $fClean = 'EQUIPO DE CÓMPUTO';
                            elseif (in_array($fClean, ['HERRAMIENTA', 'HERRAMIENTAS'])) $fClean = 'HERRAMIENTAS';
                            elseif (in_array($fClean, ['LINEA BLANCA'])) $fClean = 'LÍNEA BLANCA';
                            elseif (in_array($fClean, ['ELECTRONICA', 'ELECTRONICOS'])) $fClean = 'ELECTRÓNICA';
                            elseif (in_array($fClean, ['AUDIO', 'EQUIPO DE SONIDO'])) $fClean = 'AUDIO Y SONIDO';
                            elseif (in_array($fClean, ['BICICLETA', 'BICICLETAS'])) $fClean = 'BICICLETAS';
                            elseif (in_array($fClean, ['ELECTRODOMESTICO', 'ELECTRODOMESTICOS'])) $fClean = 'ELECTRODOMÉSTICOS';
                            elseif (in_array($fClean, ['INSTRUMENTO MUSICAL', 'INSTRUMENTOS MUSICALES'])) $fClean = 'INSTRUMENTOS MUSICALES';
                            elseif (in_array($fClean, ['CAMARA', 'CAMARAS'])) $fClean = 'CÁMARAS';
                            elseif (in_array($fClean, ['VIDEOJUEGOS', 'VIDEOJUEGO'])) $fClean = 'VIDEOJUEGOS';
                            elseif (in_array($fClean, ['RELOJ', 'RELOJES'])) $fClean = 'RELOJES';
                            elseif (empty($fClean) || $fClean === 'VARIOS' || $fClean === 'ARTICULOS VARIOS') {
                                $fClean = (!empty($pDescRaw) && $pDescRaw !== 'VARIOS') ? "VARIOS: {$pDescRaw}" : 'ARTÍCULOS VARIOS';
                            }
                            $nombreFam = $fClean;
                        }

                        if (!isset($familiasMap[$nombreFam])) {
                            $familiasMap[$nombreFam] = [
                                'familia' => $nombreFam,
                                'categoria' => $cat,
                                'tipo_prenda' => $tipoP,
                                'articulos' => 0,
                                'prestamo' => 0.0,
                                'precio' => 0.0,
                                'avaluo' => 0.0,
                            ];
                        }
                        $familiasMap[$nombreFam]['articulos']++;
                        $familiasMap[$nombreFam]['prestamo'] += $prestamoVal;
                        $familiasMap[$nombreFam]['precio'] += $precioVal;
                        $familiasMap[$nombreFam]['avaluo'] += $avaluoVal;

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
                                'familia' => $nombreFam,
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

            // Desplegar todos los estatus reales presentes (articulos > 0) sin agrupar en "Otros"
            $estatusActualMap = array_filter($estatusActualMap, fn($e) => $e['articulos'] > 0);
            uasort($estatusActualMap, fn($a, $b) => $b['prestamo'] <=> $a['prestamo']);

            // Procesar y ordenar Ranking de Familias
            $familiasList = [];
            foreach ($familiasMap as $fam) {
                $fPrestamo = $fam['prestamo'];
                $fPrecio = $fam['precio'];
                $fArts = $fam['articulos'];
                $fMargenMonto = $fPrecio - $fPrestamo;
                $fMargenPct = ($fPrestamo > 0) ? round(($fMargenMonto / $fPrestamo) * 100, 1) : 0.0;
                $fTicketPromedio = ($fArts > 0) ? round($fPrestamo / $fArts, 2) : 0.0;
                $fPorcentajeTotal = ($totalPrestamo > 0) ? round(($fPrestamo / $totalPrestamo) * 100, 2) : 0.0;

                $familiasList[] = [
                    'familia' => $fam['familia'],
                    'categoria' => $fam['categoria'],
                    'tipo_prenda' => $fam['tipo_prenda'],
                    'articulos' => $fArts,
                    'prestamo' => $fPrestamo,
                    'precio' => $fPrecio,
                    'avaluo' => $fam['avaluo'],
                    'margen_monto' => $fMargenMonto,
                    'margen_pct' => $fMargenPct,
                    'ticket_promedio' => $fTicketPromedio,
                    'porcentaje_total' => $fPorcentajeTotal,
                ];
            }

            usort($familiasList, function ($a, $b) {
                return $b['prestamo'] <=> $a['prestamo'];
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
                'ranking_familias' => array_values($familiasList),
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
