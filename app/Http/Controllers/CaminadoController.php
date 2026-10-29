<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;
use App\Models\Caminado;
use App\Models\CaminadoDetalle;
use App\Services\PowerBIPermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CaminadoController extends Controller
{
    /**
     * Establece la conexión de lectura dinámica con la base de datos de la sucursal (sistema_prendario_X).
     */
    private function setDynamicConnection($id_valora_mas)
    {
        $databaseName = 'sistema_prendario_' . $id_valora_mas;
        $baseConfig = Config::get('database.connections.mysql');
        $baseConfig['database'] = $databaseName;

        Config::set('database.connections.prendario_dinamico', $baseConfig);
        DB::purge('prendario_dinamico');
    }

    /**
     * Normaliza cualquier formato de fecha (DD/MM/YYYY o YYYY-MM-DD) al estándar SQL YYYY-MM-DD.
     */
    private function normalizarFecha($val)
    {
        if (empty($val)) {
            return null;
        }
        $val = trim($val);
        if (strpos($val, '/') !== false) {
            $parts = explode('/', $val);
            if (count($parts) === 3) {
                if (strlen($parts[2]) === 4) {
                    return sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
                }
                if (strlen($parts[0]) === 4) {
                    return sprintf('%04d-%02d-%02d', (int)$parts[0], (int)$parts[1], (int)$parts[2]);
                }
            }
        }
        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Exception $e) {
            return $val;
        }
    }

    /**
     * Muestra la vista principal del módulo de Caminado en Power BI / BiPower.
     */
    public function index(Request $request)
    {
        $regionalUserId = $request->input('regional_user_id');
        $regionales = PowerBIPermissionService::getRegionales();

        $sucursales = PowerBIPermissionService::getPermittedSucursales(null, $regionalUserId);
        if ($sucursales->isEmpty()) {
            $sucursales = Sucursal::where('activo', 1)
                ->whereNotNull('id_valora_mas')
                ->where('id_valora_mas', '!=', 5)
                ->where('nombre', 'not like', '%OFICINA%')
                ->orderBy('nombre', 'asc')
                ->get();
        } else {
            $sucursales = $sucursales->filter(function($s) {
                return $s->activo == 1 && $s->id_valora_mas != 5 && !str_contains(strtoupper($s->nombre), 'OFICINA');
            })->sortBy('nombre')->values();
        }

        // Métricas analíticas globales de Power BI para el tablero superior
        $totalCaminados = Caminado::count();
        $totalArticulosAuditados = Caminado::sum('total_articulos');
        $totalArticulosEvaluados = Caminado::sum('total_revisados');
        $totalSobrevaluados = Caminado::sum('total_sobrevaluados');
        $montoPrestamoTotal = (float)Caminado::sum('monto_total_prestamo');
        $montoSobrevaluoTotal = (float)Caminado::sum('monto_total_sobrevaluo');
        
        $pctSobrevaluoGlobal = $totalArticulosAuditados > 0 
            ? round(($totalSobrevaluados / $totalArticulosAuditados) * 100, 1) 
            : 0;

        // Catálogos para filtros de información guardada
        $valuadores = CaminadoDetalle::whereNotNull('usuario')
            ->where('usuario', '!=', '')
            ->distinct()
            ->orderBy('usuario')
            ->pluck('usuario');

        $auditoresIds = Caminado::select('user_id')->whereNotNull('user_id')->distinct()->pluck('user_id');
        $auditores = \App\Models\User::whereIn('id', $auditoresIds)->get()->map(function($u) {
            return [
                'id' => $u->id,
                'nombre' => $u->nombre_completo ?? $u->nick_name,
            ];
        });

        $fechaInicio = now()->subMonths(6)->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();

        return view('caminado.index', compact(
            'sucursales',
            'regionales',
            'regionalUserId',
            'totalCaminados',
            'totalArticulosAuditados',
            'totalArticulosEvaluados',
            'totalSobrevaluados',
            'montoPrestamoTotal',
            'montoSobrevaluoTotal',
            'pctSobrevaluoGlobal',
            'valuadores',
            'auditores',
            'fechaInicio',
            'fechaFin'
        ));
    }

    /**
     * Consulta y filtra la información guardada de caminados para el tablero Power BI.
     */
    public function data(Request $request)
    {
        try {
            $sucursalId = $request->input('sucursal_id');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');
            $caminadoId = $request->input('caminado_id');
            $auditorId = $request->input('auditor_id');
            $valuadorFiltro = $request->input('valuador');
            $estatus = $request->input('estatus');
            $soloSobrevaluo = $request->boolean('solo_sobrevaluo');

            $query = Caminado::with(['sucursal', 'usuario', 'detalles']);

            if (!empty($sucursalId) && $sucursalId !== 'all') {
                $query->where('sucursal_id', $sucursalId);
            }

            if (!empty($fechaInicio) && !empty($fechaFin)) {
                $inicioSql = $this->normalizarFecha($fechaInicio);
                $finSql = $this->normalizarFecha($fechaFin);
                if ($inicioSql && $finSql) {
                    $query->whereBetween('fecha_revision', [$inicioSql . ' 00:00:00', $finSql . ' 23:59:59']);
                }
            } elseif (!empty($fechaInicio)) {
                $inicioSql = $this->normalizarFecha($fechaInicio);
                if ($inicioSql) {
                    $query->where('fecha_revision', '>=', $inicioSql . ' 00:00:00');
                }
            }

            if (!empty($caminadoId) && $caminadoId !== 'all') {
                $query->where('id', $caminadoId);
            }

            if (!empty($auditorId) && $auditorId !== 'all') {
                $query->where('user_id', $auditorId);
            }

            if (!empty($estatus) && $estatus !== 'all') {
                $query->where('estatus', $estatus);
            }

            $caminados = $query->orderBy('fecha_revision', 'desc')->orderBy('id', 'desc')->get();

            // Métricas y desglose
            $totalCaminados = $caminados->count();
            $totalArticulos = 0;
            $totalRevisados = 0;
            $totalSobrevaluados = 0;
            $montoTotalPrestamo = 0;
            $montoTotalSobrevaluo = 0;

            $sesiones = [];
            $prendas = [];
            $porValuador = [];
            $porCategoria = ['ORO / METAL' => 0, 'VARIOS' => 0, 'AUTOS' => 0];
            $porEstado = ['Excelente' => 0, 'Bueno' => 0, 'Regular' => 0, 'Malo' => 0, 'Sin Calificar' => 0];
            $porSucursalSobrevaluo = [];

            foreach ($caminados as $c) {
                $sucNombre = $c->sucursal->nombre ?? 'N/A';
                $auditorNombre = $c->usuario ? ($c->usuario->nombre_completo ?? $c->usuario->nick_name) : 'N/A';
                $montoPrestamoCaminado = (float)$c->monto_total_prestamo;
                $montoSobrevaluoCaminado = (float)$c->monto_total_sobrevaluo;

                $montoTotalPrestamo += $montoPrestamoCaminado;
                $montoTotalSobrevaluo += $montoSobrevaluoCaminado;

                if (!isset($porSucursalSobrevaluo[$sucNombre])) {
                    $porSucursalSobrevaluo[$sucNombre] = [
                        'sucursal' => $sucNombre,
                        'caminados' => 0,
                        'articulos' => 0,
                        'sobrevaluados' => 0,
                        'monto_sobrevaluo' => 0,
                        'monto_prestamo' => 0,
                    ];
                }
                $porSucursalSobrevaluo[$sucNombre]['caminados']++;
                $porSucursalSobrevaluo[$sucNombre]['articulos'] += (int)$c->total_articulos;
                $porSucursalSobrevaluo[$sucNombre]['sobrevaluados'] += (int)$c->total_sobrevaluados;
                $porSucursalSobrevaluo[$sucNombre]['monto_sobrevaluo'] += $montoSobrevaluoCaminado;
                $porSucursalSobrevaluo[$sucNombre]['monto_prestamo'] += $montoPrestamoCaminado;

                $sesiones[] = [
                    'id' => $c->id,
                    'fecha_revision' => $c->fecha_revision ? $c->fecha_revision->format('Y-m-d') : '-',
                    'fecha_recepcion' => $c->fecha_recepcion ? $c->fecha_recepcion->format('Y-m-d') : '-',
                    'sucursal' => $sucNombre,
                    'auditor' => $auditorNombre,
                    'estatus' => $c->estatus,
                    'total_articulos' => (int)$c->total_articulos,
                    'total_revisados' => (int)$c->total_revisados,
                    'total_sobrevaluados' => (int)$c->total_sobrevaluados,
                    'monto_prestamo' => $montoPrestamoCaminado,
                    'monto_sobrevaluo' => $montoSobrevaluoCaminado,
                    'pct_sobrevaluo' => $montoPrestamoCaminado > 0 ? round(($montoSobrevaluoCaminado / $montoPrestamoCaminado) * 100, 1) : 0,
                ];

                foreach ($c->detalles as $d) {
                    $totalArticulos++;
                    if (!empty($d->estado_articulo) || $d->descripcion_correcta !== null || $d->sobrevaluo == 1) {
                        $totalRevisados++;
                    }

                    $esSobre = ($d->sobrevaluo == 1 || (float)$d->monto_sobrevaluo > 0);
                    if ($esSobre) {
                        $totalSobrevaluados++;
                    }

                    // Filtros adicionales por fila
                    if ($soloSobrevaluo && !$esSobre) {
                        continue;
                    }
                    if (!empty($valuadorFiltro) && $valuadorFiltro !== 'all' && stripos($d->usuario, $valuadorFiltro) === false) {
                        continue;
                    }

                    // Categoría conteo
                    $cat = strtoupper(trim($d->categoria ?: 'VARIOS'));
                    if (str_contains($cat, 'METAL') || str_contains($cat, 'ORO') || str_contains($cat, 'ALHAJA')) {
                        $porCategoria['ORO / METAL']++;
                    } elseif (str_contains($cat, 'AUTO')) {
                        $porCategoria['AUTOS']++;
                    } else {
                        $porCategoria['VARIOS']++;
                    }

                    // Estado físico
                    $est = ucfirst(strtolower(trim($d->estado_articulo ?: 'Sin Calificar')));
                    if (isset($porEstado[$est])) {
                        $porEstado[$est]++;
                    } else {
                        $porEstado['Sin Calificar']++;
                    }

                    // Concentrado por Valuador
                    $val = trim($d->usuario ?: 'SIN ASIGNAR');
                    if (!isset($porValuador[$val])) {
                        $porValuador[$val] = [
                            'usuario' => $val,
                            'sucursal' => $sucNombre,
                            'total_operaciones' => 0,
                            'monto_empeno' => 0,
                            'desc_correctas' => 0,
                            'desc_incorrectas' => 0,
                            'sobrevaluados' => 0,
                            'monto_sobrevaluo' => 0,
                        ];
                    }
                    $porValuador[$val]['total_operaciones']++;
                    $porValuador[$val]['monto_empeno'] += (float)$d->monto;
                    if ($d->descripcion_correcta === 1 || $d->descripcion_correcta === true) {
                        $porValuador[$val]['desc_correctas']++;
                    } elseif ($d->descripcion_correcta === 0 || $d->descripcion_correcta === false) {
                        $porValuador[$val]['desc_incorrectas']++;
                    }
                    if ($esSobre) {
                        $porValuador[$val]['sobrevaluados']++;
                        $porValuador[$val]['monto_sobrevaluo'] += (float)$d->monto_sobrevaluo;
                    }

                    $prendas[] = [
                        'id' => $d->id,
                        'caminado_id' => $c->id,
                        'sucursal' => $sucNombre,
                        'fecha_revision' => $c->fecha_revision ? $c->fecha_revision->format('Y-m-d') : '-',
                        'folio' => $d->folio ?: '-',
                        'contrato' => $d->contrato ?: '-',
                        'cod_contrato' => $d->cod_contrato ?: '-',
                        'categoria' => $d->categoria ?: 'VARIOS',
                        'articulo' => $d->articulo ?: '-',
                        'descripcion' => $d->descripcion ?: '-',
                        'usuario' => $d->usuario ?: '-',
                        'monto' => (float)$d->monto,
                        'ubicacion' => $d->ubicacion ?: '-',
                        'pct_adicional' => (float)$d->pct_adicional,
                        'prestamo_real' => (float)$d->prestamo_real,
                        'estado_articulo' => $d->estado_articulo ?: '-',
                        'descripcion_correcta' => $d->descripcion_correcta,
                        'accesorios_completos' => $d->accesorios_completos,
                        'sobrevaluo' => $esSobre ? 1 : 0,
                        'monto_sobrevaluo' => (float)$d->monto_sobrevaluo,
                        'pct_sobrevaluo' => (float)$d->pct_sobrevaluo,
                        'observaciones' => $d->observaciones ?: '',
                    ];
                }
            }

            // Formatear concentrado por valuador con indicadores de efectividad
            $concentrado = [];
            foreach ($porValuador as $k => $v) {
                $totalOps = $v['total_operaciones'];
                $pctPart = $montoTotalPrestamo > 0 ? round(($v['monto_empeno'] / $montoTotalPrestamo) * 100, 1) : 0;
                $efecDesc = $totalOps > 0 ? round(($v['desc_correctas'] / $totalOps) * 100, 1) : 100;
                $efecVal = $totalOps > 0 ? round((($totalOps - $v['sobrevaluados']) / $totalOps) * 100, 1) : 100;
                $efecGen = round(($efecDesc * 0.4) + ($efecVal * 0.6), 1);
                $pctSobre = $v['monto_empeno'] > 0 ? round(($v['monto_sobrevaluo'] / $v['monto_empeno']) * 100, 1) : 0;

                $concentrado[] = [
                    'usuario' => $v['usuario'],
                    'sucursal' => $v['sucursal'],
                    'total_operaciones' => $totalOps,
                    'monto_empeno' => $v['monto_empeno'],
                    'pct_participacion' => $pctPart,
                    'desc_correctas' => $v['desc_correctas'],
                    'desc_incorrectas' => $v['desc_incorrectas'],
                    'efectividad_desc' => $efecDesc,
                    'sobrevaluados' => $v['sobrevaluados'],
                    'monto_sobrevaluo' => $v['monto_sobrevaluo'],
                    'pct_sobrevaluo' => $pctSobre,
                    'efectividad_valuando' => $efecVal,
                    'efectividad_general' => $efecGen,
                ];
            }

            usort($concentrado, fn($a, $b) => $b['monto_empeno'] <=> $a['monto_empeno']);

            $sucursalesRanking = array_values($porSucursalSobrevaluo);
            usort($sucursalesRanking, fn($a, $b) => $b['monto_sobrevaluo'] <=> $a['monto_sobrevaluo']);

            $tasaSobrevaluoGlobal = $totalArticulos > 0 ? round(($totalSobrevaluados / $totalArticulos) * 100, 1) : 0;
            $tasaEfectividadGlobal = $totalArticulos > 0 ? round((($totalArticulos - $totalSobrevaluados) / $totalArticulos) * 100, 1) : 100;

            return response()->json([
                'success' => true,
                'kpis' => [
                    'total_caminados' => $totalCaminados,
                    'total_articulos' => $totalArticulos,
                    'total_revisados' => $totalRevisados,
                    'total_sobrevaluados' => $totalSobrevaluados,
                    'monto_total_prestamo' => $montoTotalPrestamo,
                    'monto_total_sobrevaluo' => $montoTotalSobrevaluo,
                    'tasa_sobrevaluo' => $tasaSobrevaluoGlobal,
                    'tasa_efectividad' => $tasaEfectividadGlobal,
                ],
                'sesiones' => $sesiones,
                'prendas' => $prendas,
                'concentrado' => $concentrado,
                'charts' => [
                    'top_sucursales' => array_slice($sucursalesRanking, 0, 7),
                    'categorias' => $porCategoria,
                    'estados' => $porEstado,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en CaminadoController@data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al consultar la información guardada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consulta los artículos de contratos de la sucursal prendaria especificada.
     */
    public function getArticulos(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required',
        ]);

        $sucursal = Sucursal::find($request->sucursal_id);

        if (!$sucursal || !$sucursal->id_valora_mas) {
            return response()->json([
                'success' => false,
                'message' => 'La sucursal seleccionada no tiene asignado un identificador de base de datos prendaria (id_valora_mas).'
            ], 422);
        }

        try {
            $this->setDynamicConnection($sucursal->id_valora_mas);

            $fechaInicio = $this->normalizarFecha($request->input('fecha_recepcion_inicio') ?: $request->input('fecha_recepcion'));
            $fechaFin = $this->normalizarFecha($request->input('fecha_recepcion_fin') ?: $fechaInicio);
            $contratoFiltro = trim($request->input('contrato', ''));

            $whereSql = '';
            $subParams = [];

            if (!empty($fechaInicio) && !empty($fechaFin)) {
                $whereSql .= " AND DATE(c.f_contrato) BETWEEN ? AND ? ";
                $subParams[] = $fechaInicio;
                $subParams[] = $fechaFin;
            } elseif (!empty($fechaInicio)) {
                $whereSql .= " AND DATE(c.f_contrato) = ? ";
                $subParams[] = $fechaInicio;
            }

            if (!empty($contratoFiltro)) {
                $whereSql .= " AND (c.contrato = ? OR c.bolsa = ?) ";
                $subParams[] = $contratoFiltro;
                $subParams[] = $contratoFiltro;
            }

            $params = array_merge($subParams, $subParams, $subParams);

            // Construir Query unificada para Varios, Alhajas (Metal) y Autos
            $sql = "
                SELECT 
                    c.cod_contrato,
                    c.contrato,
                    c.bolsa,
                    v.id as item_id,
                    'VARIOS' as categoria,
                    COALESCE(p.prenda, 'VARIOS') as articulo,
                    COALESCE(v.observaciones, '') as descripcion,
                    COALESCE(u.nombre, u.usuario, 'N/A') as usuario,
                    COALESCE(u.usuario, u.nombre, 'N/A') as usuario_codigo,
                    COALESCE(v.prestamo, 0) as monto,
                    COALESCE(c.prestamo, v.prestamo, 0) as prestamo_inicial,
                    COALESCE(c.abono, 0) as abono,
                    0.00 as interes_cobrado,
                    COALESCE(c.cod_plazo, '') as periodo,
                    COALESCE(c.descuento, 0) as descuento,
                    COALESCE(v.venta, 0) as venta,
                    COALESCE(v.prestamo, c.prestamo, 0) as total,
                    COALESCE(v.folio_bolsa, c.bolsa, '') as ubicacion,
                    COALESCE(c.descuento, 0) as pct_adicional,
                    COALESCE(c.prestamo, 0) as prestamo_real,
                    '' as folio_cupon,
                    COALESCE(v.id_intranet, 0) as id_intranet,
                    DATE(c.f_contrato) as fecha_contrato
                FROM contratos c
                INNER JOIN varios v ON v.cod_contrato = c.cod_contrato
                LEFT JOIN prendas p ON p.cod_prenda = v.cod_prenda
                LEFT JOIN usuarios u ON u.cod_usuario = c.cod_usuario
                WHERE c.cod_estatus != 5 {$whereSql}

                UNION ALL

                SELECT 
                    c.cod_contrato,
                    c.contrato,
                    c.bolsa,
                    a.id as item_id,
                    'METAL' as categoria,
                    COALESCE(p.prenda, 'ALHAJA') as articulo,
                    COALESCE(a.observaciones, '') as descripcion,
                    COALESCE(u.nombre, u.usuario, 'N/A') as usuario,
                    COALESCE(u.usuario, u.nombre, 'N/A') as usuario_codigo,
                    COALESCE(a.prestamo, 0) as monto,
                    COALESCE(c.prestamo, a.prestamo, 0) as prestamo_inicial,
                    COALESCE(c.abono, 0) as abono,
                    0.00 as interes_cobrado,
                    COALESCE(c.cod_plazo, '') as periodo,
                    COALESCE(c.descuento, 0) as descuento,
                    COALESCE(a.venta, 0) as venta,
                    COALESCE(a.prestamo, c.prestamo, 0) as total,
                    COALESCE(a.folio_bolsa, c.bolsa, '') as ubicacion,
                    COALESCE(c.descuento, 0) as pct_adicional,
                    COALESCE(c.prestamo, 0) as prestamo_real,
                    '' as folio_cupon,
                    0 as id_intranet,
                    DATE(c.f_contrato) as fecha_contrato
                FROM contratos c
                INNER JOIN alhajas a ON a.cod_contrato = c.cod_contrato
                LEFT JOIN prendas p ON p.cod_prenda = a.cod_prenda
                LEFT JOIN usuarios u ON u.cod_usuario = c.cod_usuario
                WHERE c.cod_estatus != 5 {$whereSql}

                UNION ALL

                SELECT 
                    c.cod_contrato,
                    c.contrato,
                    c.bolsa,
                    au.id as item_id,
                    'AUTOS' as categoria,
                    COALESCE(p.prenda, 'AUTO') as articulo,
                    CONCAT(COALESCE(au.observaciones, ''), ' ', COALESCE(au.placas, '')) as descripcion,
                    COALESCE(u.nombre, u.usuario, 'N/A') as usuario,
                    COALESCE(u.usuario, u.nombre, 'N/A') as usuario_codigo,
                    COALESCE(au.prestamo, 0) as monto,
                    COALESCE(c.prestamo, au.prestamo, 0) as prestamo_inicial,
                    COALESCE(c.abono, 0) as abono,
                    0.00 as interes_cobrado,
                    COALESCE(c.cod_plazo, '') as periodo,
                    COALESCE(c.descuento, 0) as descuento,
                    COALESCE(au.venta, 0) as venta,
                    COALESCE(au.prestamo, c.prestamo, 0) as total,
                    COALESCE(c.bolsa, au.placas, '') as ubicacion,
                    COALESCE(c.descuento, 0) as pct_adicional,
                    COALESCE(c.prestamo, 0) as prestamo_real,
                    '' as folio_cupon,
                    0 as id_intranet,
                    DATE(c.f_contrato) as fecha_contrato
                FROM contratos c
                INNER JOIN autos au ON au.cod_contrato = c.cod_contrato
                LEFT JOIN prendas p ON p.cod_prenda = au.cod_prenda
                LEFT JOIN usuarios u ON u.cod_usuario = c.cod_usuario
                WHERE c.cod_estatus != 5 {$whereSql}

                ORDER BY fecha_contrato DESC, contrato ASC
            ";

            if (empty($fechaInicio) && empty($contratoFiltro)) {
                $sql .= " LIMIT 300 ";
            }

            $articulos = DB::connection('prendario_dinamico')->select($sql, $params);

            // Cruzar con el catálogo de Artículos si existe id_intranet
            $idsIntranet = [];
            foreach ($articulos as $art) {
                if (!empty($art->id_intranet) && is_numeric($art->id_intranet) && intval($art->id_intranet) > 0) {
                    $idsIntranet[] = intval($art->id_intranet);
                }
            }
            $idsIntranet = array_values(array_unique($idsIntranet));

            $articulosIntranet = [];
            if (!empty($idsIntranet) && DB::getSchemaBuilder()->hasTable('articulos')) {
                $articulosIntranet = DB::table('articulos')
                    ->whereIn('id', $idsIntranet)
                    ->get()
                    ->keyBy('id');
            }

            foreach ($articulos as $item) {
                $idInt = intval($item->id_intranet ?? 0);
                $artModel = ($idInt > 0 && isset($articulosIntranet[$idInt])) ? $articulosIntranet[$idInt] : null;

                $esFamiliaAbierta = false;
                $nombreFamilia = '';

                if ($item->categoria === 'VARIOS') {
                    $esFamiliaAbierta = true;
                    $nombreFamilia = $item->articulo ?: 'VARIOS';
                }

                $precioIntranet = 0;
                if ($artModel) {
                    $precioIntranet = floatval($artModel->prestamo_excelente ?: ($artModel->precio_base_prestamo ?: $artModel->avaluo));
                }

                $item->id_intranet = $idInt > 0 ? $idInt : null;
                $item->familia_nombre = $nombreFamilia;
                $item->es_familia_abierta = $esFamiliaAbierta;
                $item->precio_intranet = $precioIntranet;

                // Monto otorgado en sucursal
                $monto = floatval($item->monto ?? 0);

                if ($precioIntranet > 0 && !$esFamiliaAbierta) {
                    $item->prestamo_real = $precioIntranet;
                } else {
                    $item->prestamo_real = $precioIntranet > 0 ? $precioIntranet : $monto;
                }

                $precioReal = floatval($item->prestamo_real);

                // Fórmula oficial: Diferencia = Monto - Precio Real; % = (Diferencia / Precio Real) * 100
                if ($precioReal > 0 && $monto > $precioReal) {
                    $dif = $monto - $precioReal;
                    $pct = ($dif / $precioReal) * 100;
                    $item->sobrevaluo = 1;
                    $item->monto_sobrevaluo = round($dif, 2);
                    $item->pct_sobrevaluo = round($pct, 1);
                } else {
                    $item->sobrevaluo = 0;
                    $item->monto_sobrevaluo = 0;
                    $item->pct_sobrevaluo = 0;
                }

                // Incremento por estrategia (% adicional de campaña)
                $pctAdic = floatval($item->pct_adicional ?? 0);
                $item->monto_incremento = $pctAdic > 0 ? round($monto * ($pctAdic / 100), 2) : 0;
            }

            return response()->json([
                'success' => true,
                'total' => count($articulos),
                'sucursal_nombre' => $sucursal->nombre,
                'articulos' => $articulos,
            ]);

        } catch (\Exception $e) {
            Log::error("Error querying articulos caminado for sucursal {$sucursal->nombre}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "No se pudo conectar a la base de datos de la sucursal {$sucursal->nombre} (sistema_prendario_{$sucursal->id_valora_mas}): " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guarda la evaluación y resultados del caminado.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursals,id',
            'fecha_revision' => 'required|date',
            'articulos' => 'required|array|min:1',
        ]);

        DB::beginTransaction();

        try {
            $caminadoId = $request->input('caminado_id');

            if ($caminadoId) {
                $caminado = Caminado::findOrFail($caminadoId);
                // Limpiar detalles anteriores para reemplazar con los actualizados
                $caminado->detalles()->delete();
                $caminado->update([
                    'sucursal_id' => $request->sucursal_id,
                    'fecha_recepcion' => $request->fecha_recepcion ?: null,
                    'fecha_revision' => $request->fecha_revision,
                    'total_articulos' => count($request->articulos),
                    'observaciones' => $request->observaciones ?: null,
                    'estatus' => $request->input('estatus', 'completado'),
                ]);
            } else {
                $caminado = Caminado::create([
                    'sucursal_id' => $request->sucursal_id,
                    'user_id' => Auth::id() ?? 1,
                    'fecha_recepcion' => $request->fecha_recepcion ?: null,
                    'fecha_revision' => $request->fecha_revision,
                    'total_articulos' => count($request->articulos),
                    'observaciones' => $request->observaciones ?: null,
                    'estatus' => $request->input('estatus', 'completado'),
                ]);
            }

            $totalRevisados = 0;
            $totalSobrevaluados = 0;
            $montoTotalPrestamo = 0;
            $montoTotalSobrevaluo = 0;

            foreach ($request->articulos as $art) {
                $monto = floatval($art['monto'] ?? 0);
                $montoTotalPrestamo += $monto;

                $sobrevaluo = !empty($art['sobrevaluo']) && ($art['sobrevaluo'] === '1' || $art['sobrevaluo'] === 1 || $art['sobrevaluo'] === true);
                $montoSobrevaluo = floatval($art['monto_sobrevaluo'] ?? 0);
                $pctSobrevaluo = floatval($art['pct_sobrevaluo'] ?? 0);

                if ($sobrevaluo) {
                    $totalSobrevaluados++;
                    $montoTotalSobrevaluo += $montoSobrevaluo;
                }

                $estadoArticulo = $art['estado_articulo'] ?? null;
                $descCorrecta = isset($art['descripcion_correcta']) && $art['descripcion_correcta'] !== '' ? ($art['descripcion_correcta'] === '1' || $art['descripcion_correcta'] === 1 || $art['descripcion_correcta'] === true) : null;
                $accesorios = isset($art['accesorios_completos']) && $art['accesorios_completos'] !== '' ? ($art['accesorios_completos'] === '1' || $art['accesorios_completos'] === 1 || $art['accesorios_completos'] === true) : null;

                if (!empty($estadoArticulo) || $descCorrecta !== null || $accesorios !== null || $sobrevaluo) {
                    $totalRevisados++;
                }

                CaminadoDetalle::create([
                    'caminado_id' => $caminado->id,
                    'folio' => $art['folio'] ?? ($art['bolsa'] ?? null),
                    'contrato' => $art['contrato'] ?? null,
                    'cod_contrato' => $art['cod_contrato'] ?? null,
                    'item_id' => $art['item_id'] ?? null,
                    'id_intranet' => !empty($art['id_intranet']) ? intval($art['id_intranet']) : null,
                    'descripcion' => $art['descripcion'] ?? null,
                    'usuario' => $art['usuario'] ?? null,
                    'articulo' => $art['articulo'] ?? null,
                    'categoria' => $art['categoria'] ?? null,
                    'familia' => $art['familia_nombre'] ?? ($art['familia'] ?? null),
                    'es_familia_abierta' => !empty($art['es_familia_abierta']),
                    'monto' => $monto,
                    'ubicacion' => $art['ubicacion'] ?? null,
                    'pct_adicional' => floatval($art['pct_adicional'] ?? 0),
                    'prestamo_real' => floatval($art['prestamo_real'] ?? $monto),
                    'folio_cupon' => $art['folio_cupon'] ?? null,
                    'estado_articulo' => $estadoArticulo,
                    'descripcion_correcta' => $descCorrecta,
                    'accesorios_completos' => $accesorios,
                    'sobrevaluo' => $sobrevaluo,
                    'monto_sobrevaluo' => $montoSobrevaluo,
                    'pct_sobrevaluo' => $pctSobrevaluo,
                    'observaciones' => $art['observaciones'] ?? null,
                ]);
            }

            $caminado->update([
                'total_revisados' => $totalRevisados,
                'total_sobrevaluados' => $totalSobrevaluados,
                'monto_total_prestamo' => $montoTotalPrestamo,
                'monto_total_sobrevaluo' => $montoTotalSobrevaluo,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Caminado guardado exitosamente en Power BI.',
                'caminado_id' => $caminado->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saving caminado: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el caminado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna el historial de caminados guardados.
     */
    public function historial(Request $request)
    {
        $sucursalId = $request->input('sucursal_id');
        $query = Caminado::with(['sucursal', 'usuario']);

        if (!empty($sucursalId) && $sucursalId !== 'all') {
            $query->where('sucursal_id', $sucursalId);
        }

        $caminados = $query->orderBy('fecha_revision', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $caminados,
        ]);
    }

    /**
     * Muestra el detalle de un caminado guardado.
     */
    public function show($id)
    {
        $caminado = Caminado::with(['sucursal', 'usuario', 'detalles'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'caminado' => $caminado,
        ]);
    }

    /**
     * Carga un caminado previamente guardado para continuarlo o editarlo en la tabla interactiva.
     */
    public function cargar($id)
    {
        $caminado = Caminado::with(['sucursal', 'detalles'])->findOrFail($id);

        $articulos = $caminado->detalles->map(function ($d) use ($caminado) {
            return [
                'folio' => $d->folio,
                'bolsa' => $d->folio,
                'contrato' => $d->contrato,
                'cod_contrato' => $d->cod_contrato,
                'item_id' => $d->item_id,
                'id_intranet' => $d->id_intranet,
                'descripcion' => $d->descripcion,
                'usuario' => $d->usuario,
                'articulo' => $d->articulo,
                'categoria' => $d->categoria,
                'familia_nombre' => $d->familia,
                'es_familia_abierta' => (bool)$d->es_familia_abierta,
                'monto' => floatval($d->monto),
                'ubicacion' => $d->ubicacion,
                'pct_adicional' => floatval($d->pct_adicional),
                'prestamo_real' => floatval($d->prestamo_real),
                'precio_intranet' => floatval($d->prestamo_real),
                'monto_incremento' => floatval($d->pct_adicional) > 0 ? round(floatval($d->monto) * (floatval($d->pct_adicional) / 100), 2) : 0,
                'folio_cupon' => $d->folio_cupon,
                'fecha_contrato' => $caminado->fecha_recepcion ? $caminado->fecha_recepcion->format('Y-m-d') : null,
                'estado_articulo' => $d->estado_articulo,
                'descripcion_correcta' => $d->descripcion_correcta !== null ? ($d->descripcion_correcta ? 1 : 0) : null,
                'accesorios_completos' => $d->accesorios_completos !== null ? ($d->accesorios_completos ? 1 : 0) : null,
                'sobrevaluo' => $d->sobrevaluo ? 1 : 0,
                'monto_sobrevaluo' => floatval($d->monto_sobrevaluo),
                'pct_sobrevaluo' => floatval($d->pct_sobrevaluo),
                'observaciones' => $d->observaciones,
            ];
        });

        return response()->json([
            'success' => true,
            'caminado_id' => $caminado->id,
            'sucursal_id' => $caminado->sucursal_id,
            'sucursal_nombre' => $caminado->sucursal->nombre ?? 'N/A',
            'fecha_recepcion' => $caminado->fecha_recepcion ? $caminado->fecha_recepcion->format('Y-m-d') : '',
            'fecha_revision' => $caminado->fecha_revision ? $caminado->fecha_revision->format('Y-m-d') : '',
            'estatus' => $caminado->estatus,
            'total' => count($articulos),
            'articulos' => $articulos,
        ]);
    }

    /**
     * Elimina un caminado guardado.
     */
    public function destroy($id)
    {
        $caminado = Caminado::findOrFail($id);
        $caminado->delete();

        return response()->json([
            'success' => true,
            'message' => 'Caminado eliminado exitosamente.'
        ]);
    }

    /**
     * Exporta el caminado en formato CSV compatible con Excel.
     */
    public function exportarCsv($id)
    {
        $caminado = Caminado::with(['sucursal', 'usuario', 'detalles'])->findOrFail($id);
        $sucursalNombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $caminado->sucursal->nombre ?? 'Sucursal');
        $fileName = "Caminado_{$sucursalNombre}_{$caminado->fecha_revision->format('Ymd')}.csv";

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$fileName}",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function() use ($caminado) {
            $file = fopen('php://output', 'w');
            // Escribir BOM para que Excel abra UTF-8 con acentos correctamente
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Folio / Bolsa',
                'Contrato',
                'ID Prenda',
                'Categoría',
                'Artículo / Prenda',
                'Descripción',
                'Valuador',
                'Monto Empeño ($)',
                'Ubicación',
                '% Adicional Campaña',
                'Préstamo Real / Catálogo ($)',
                'Folio Cupón',
                'Estado Artículo',
                'Descripción Correcta',
                'Accesorios Completos',
                'Sobrevalúo',
                'Monto Sobrevalúo ($)',
                '% Sobrevalúo',
                'Observaciones'
            ]);

            foreach ($caminado->detalles as $det) {
                fputcsv($file, [
                    $det->folio,
                    $det->contrato,
                    $det->item_id,
                    $det->categoria,
                    $det->articulo,
                    $det->descripcion,
                    $det->usuario,
                    number_format((float)$det->monto, 2, '.', ''),
                    $det->ubicacion,
                    $det->pct_adicional . '%',
                    number_format((float)$det->prestamo_real, 2, '.', ''),
                    $det->folio_cupon,
                    $det->estado_articulo ?: '-',
                    $det->descripcion_correcta === true ? 'SI' : ($det->descripcion_correcta === false ? 'NO' : '-'),
                    $det->accesorios_completos === true ? 'SI' : ($det->accesorios_completos === false ? 'NO' : '-'),
                    $det->sobrevaluo ? 'SI' : 'NO',
                    number_format((float)$det->monto_sobrevaluo, 2, '.', ''),
                    $det->pct_sobrevaluo . '%',
                    $det->observaciones
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
