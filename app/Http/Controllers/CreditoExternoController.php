<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CreditoExterno;
use App\Models\Sucursal;
use App\Models\RazonSocial;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CreditoExternoController extends Controller
{
    /**
     * Verifica acceso al módulo Power BI de Crédito Externo (Atrato).
     */
    private function checkAccess()
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'No autorizado.');
        }

        if ($user->hasPowerBiRole(21) || $user->is_admin) {
            return;
        }

        if (method_exists($user, 'hasPermission') && ($user->hasPermission(84) || $user->hasPermission(85))) {
            return;
        }

        abort(403, 'No tienes permiso para acceder al módulo de Crédito Externo (Atrato).');
    }

    /**
     * Pantalla principal del módulo Power BI de Crédito Externo (Atrato)
     */
    public function index(Request $request)
    {
        $this->checkAccess();

        // Fechas por defecto: Mes en curso
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();

        // Zonas / Razones Sociales de referencia
        $zonas = RazonSocial::whereIn('id', [1, 4, 6, 7])->orderBy('nombre', 'asc')->get();

        // Sucursales activas para selector
        $sucursales = Sucursal::with('razonSocial')
            ->whereNotNull('id_valora_mas')
            ->where('id_valora_mas', '!=', 5)
            ->where('nombre', 'not like', '%OFICINA%')
            ->orderBy('nombre', 'asc')
            ->get();

        $plazos = [
            'Semanal',
            'Catorcenal',
            'Quincenal',
            'Mensual',
            'Efectivo / Diario',
        ];

        $metodosPago = [
            'Tarjeta',
            'Efectivo',
            'Transferencia',
        ];

        return view('credito-externo.index', compact(
            'fechaInicio',
            'fechaFin',
            'zonas',
            'sucursales',
            'plazos',
            'metodosPago'
        ));
    }

    /**
     * Endpoint AJAX con todos los KPIs, series temporales, rankings y tabla detallada
     */
    public function data(Request $request)
    {
        $this->checkAccess();

        $user = Auth::user();
        $isSoloUsuario = method_exists($user, 'hasPermission') && $user->hasPermission(84) && !$user->hasPermission(85) && !$user->is_admin && !$user->hasPowerBiRole(21);

        // Filtro Fechas
        $rawInicio = $request->input('fecha_inicio');
        $rawFin = $request->input('fecha_fin');

        // ========================================================
        // 1. KPIs POR ZONA EN EL PERIODO SELECCIONADO
        // ========================================================
        $globalQuery = CreditoExterno::query();
        if ($isSoloUsuario) {
            $globalQuery->where('user_id', $user->id);
        }

        // Totales históricos acumulados de referencia (sin fechas)
        $totalHistoricoMonto = (float) (clone $globalQuery)->sum('monto_credito');
        $totalHistoricoCreditos = (int) (clone $globalQuery)->count();

        // Aplicar fechas del periodo a los KPIs de zonas para consistencia con el dashboard
        if (!empty($rawInicio)) {
            $globalQuery->whereDate('creditos_externos.created_at', '>=', $rawInicio);
        }
        if (!empty($rawFin)) {
            $globalQuery->whereDate('creditos_externos.created_at', '<=', $rawFin);
        }

        $granTotalMontoGlobal = (float) (clone $globalQuery)->sum('monto_credito');
        $granTotalCreditosGlobal = (int) (clone $globalQuery)->count();

        $zonasDefinidas = [
            [
                'id'          => 7,
                'alias'       => 'Gapos',
                'subtitulo'   => 'GAPOS VM',
                'color'       => '#4f46e5',
                'bg_gradient' => 'linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%)',
                'bg_icon'     => 'rgba(79, 70, 229, 0.12)',
                'border'      => '#818cf8',
                'icon'        => 'bi-building',
            ],
            [
                'id'          => 4,
                'alias'       => 'Manuel',
                'subtitulo'   => 'José Manuel G.P.',
                'color'       => '#0284c7',
                'bg_gradient' => 'linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%)',
                'bg_icon'     => 'rgba(2, 132, 199, 0.12)',
                'border'      => '#38bdf8',
                'icon'        => 'bi-person-badge',
            ],
            [
                'id'          => 1,
                'alias'       => 'Héctor',
                'subtitulo'   => 'Héctor Eduardo G.P.',
                'color'       => '#059669',
                'bg_gradient' => 'linear-gradient(135deg, #ffffff 0%, #ecfdf5 100%)',
                'bg_icon'     => 'rgba(5, 150, 105, 0.12)',
                'border'      => '#34d399',
                'icon'        => 'bi-person-badge',
            ],
            [
                'id'          => 6,
                'alias'       => 'Yeni',
                'subtitulo'   => 'Yeni Posadas N.',
                'color'       => '#d97706',
                'bg_gradient' => 'linear-gradient(135deg, #ffffff 0%, #fffbeb 100%)',
                'bg_icon'     => 'rgba(217, 119, 6, 0.12)',
                'border'      => '#fbbf24',
                'icon'        => 'bi-person-badge',
            ],
        ];

        $totalesPorZonaRaw = (clone $globalQuery)
            ->join('sucursals', 'creditos_externos.sucursal_id', '=', 'sucursals.id')
            ->whereIn('sucursals.zona', [1, 4, 6, 7])
            ->select(
                'sucursals.zona',
                DB::raw('COUNT(creditos_externos.id) as total_creditos'),
                DB::raw('COALESCE(SUM(creditos_externos.monto_credito), 0) as total_monto'),
                DB::raw('COALESCE(SUM(creditos_externos.precio_venta), 0) as total_venta'),
                DB::raw('COALESCE(SUM(creditos_externos.precio_prestamo), 0) as total_prestamo')
            )
            ->groupBy('sucursals.zona')
            ->get()
            ->keyBy('zona');

        $kpisZonas = [];
        foreach ($zonasDefinidas as $z) {
            $row = $totalesPorZonaRaw->get($z['id']);
            $montoZona = $row ? (float) $row->total_monto : 0.0;
            $creditosZona = $row ? (int) $row->total_creditos : 0;
            $pct = $granTotalMontoGlobal > 0 ? round(($montoZona / $granTotalMontoGlobal) * 100, 1) : 0;

            $kpisZonas[] = array_merge($z, [
                'total_monto'    => $montoZona,
                'total_creditos' => $creditosZona,
                'porcentaje'     => $pct,
            ]);
        }

        $kpiTotalGeneral = [
            'total_monto'            => $granTotalMontoGlobal,
            'total_creditos'         => $granTotalCreditosGlobal,
            'ticket_promedio'        => $granTotalCreditosGlobal > 0 ? round($granTotalMontoGlobal / $granTotalCreditosGlobal, 2) : 0,
            'total_historico_monto'  => $totalHistoricoMonto,
            'total_historico_creds'  => $totalHistoricoCreditos,
        ];

        // ========================================================
        // 2. QUERY FILTRADA POR PARÁMETROS DEL DASHBOARD
        // ========================================================
        $query = CreditoExterno::with(['sucursal.razonSocial', 'user']);
        if ($isSoloUsuario) {
            $query->where('user_id', $user->id);
        }

        // Filtro Fechas
        $rawInicio = $request->input('fecha_inicio');
        $rawFin = $request->input('fecha_fin');
        if (!empty($rawInicio)) {
            $query->whereDate('created_at', '>=', $rawInicio);
        }
        if (!empty($rawFin)) {
            $query->whereDate('created_at', '<=', $rawFin);
        }

        // Filtro Zona
        if ($request->filled('zona')) {
            $zonaId = $request->input('zona');
            $query->whereHas('sucursal', function ($q) use ($zonaId) {
                $q->where('zona', $zonaId);
            });
        }

        // Filtro Sucursal
        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->input('sucursal_id'));
        }

        // Filtro Plazo / Forma de pago
        if ($request->filled('plazo')) {
            $p = $request->input('plazo');
            $query->where(function ($q) use ($p) {
                $q->where('plazo', $p)->orWhere('forma_pago', $p);
            });
        }

        // Filtro Método de Pago
        if ($request->filled('metodo_pago')) {
            $query->where('metodo_pago', $request->input('metodo_pago'));
        }

        // Filtro Búsqueda
        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('colaborador_nombre', 'LIKE', "%{$s}%")
                  ->orWhere('comprador', 'LIKE', "%{$s}%")
                  ->orWhere('articulo', 'LIKE', "%{$s}%")
                  ->orWhere('articulo_id', 'LIKE', "%{$s}%")
                  ->orWhere('id', 'LIKE', "%{$s}%");
            });
        }

        $creditos = $query->orderBy('created_at', 'desc')->get();

        // ========================================================
        // 3. MÉTRICAS / KPIS DEL PERIODO FILTRADO
        // ========================================================
        $totalCreditos = $creditos->count();
        $montoTotal = (float) $creditos->sum('monto_credito');
        $precioVentaTotal = (float) $creditos->sum('precio_venta');
        $precioPrestamoTotal = (float) $creditos->sum('precio_prestamo');
        $diferenciaFinanciera = $precioVentaTotal - $precioPrestamoTotal;
        $margenPorcentaje = $precioVentaTotal > 0 ? round(($diferenciaFinanciera / $precioVentaTotal) * 100, 1) : 0;
        $ticketPromedio = $totalCreditos > 0 ? round($montoTotal / $totalCreditos, 2) : 0;

        // Rankings
        $colaboradoresRanking = $creditos->groupBy('colaborador_nombre')->map(function ($items, $nombre) {
            $cant = $items->count();
            $monto = (float) $items->sum('monto_credito');
            $venta = (float) $items->sum('precio_venta');
            return [
                'nombre'      => $nombre ?: 'Sin especificar',
                'total'       => $cant,
                'monto_total' => $monto,
                'venta_total' => $venta,
                'promedio'    => $cant > 0 ? round($monto / $cant, 2) : 0,
            ];
        })->sortByDesc('total')->values();

        $topColaborador = $colaboradoresRanking->first();

        $sucursalesRanking = $creditos->groupBy(function ($item) {
            return $item->sucursal->nombre ?? 'Sin Sucursal';
        })->map(function ($items, $sucursal) {
            $cant = $items->count();
            $monto = (float) $items->sum('monto_credito');
            return [
                'sucursal'    => $sucursal,
                'total'       => $cant,
                'monto_total' => $monto,
            ];
        })->sortByDesc('monto_total')->values();

        $topSucursal = $sucursalesRanking->first();

        // Plazos Ranking
        $plazosRanking = $creditos->groupBy(function ($item) {
            return $item->plazo ?: ($item->forma_pago ?: 'No especificado');
        })->map(function ($items, $plazo) {
            return [
                'plazo'       => $plazo,
                'total'       => $items->count(),
                'monto_total' => (float) $items->sum('monto_credito'),
            ];
        })->sortByDesc('monto_total')->values();

        // Zonas del filtro actual
        $zonasRanking = $creditos->groupBy(function ($item) {
            return $item->sucursal && $item->sucursal->razonSocial 
                ? $item->sucursal->razonSocial->nombre 
                : 'Sin Zona';
        })->map(function ($items, $zName) {
            return [
                'zona'        => $zName,
                'total'       => $items->count(),
                'monto_total' => (float) $items->sum('monto_credito'),
            ];
        })->sortByDesc('monto_total')->values();

        // ========================================================
        // 4. EVOLUCIÓN TEMPORAL DÍA A DÍA (Días en español)
        // ========================================================
        $diasEspanol = [
            'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom'
        ];

        $evolucionDiaria = [];
        if (!empty($rawInicio) && !empty($rawFin)) {
            $cCur = Carbon::parse($rawInicio);
            $cEnd = Carbon::parse($rawFin);
            if ($cCur->diffInDays($cEnd) <= 62) {
                while ($cCur->lte($cEnd)) {
                    $key = $cCur->toDateString();
                    $diaIng = $cCur->format('D');
                    $evolucionDiaria[$key] = [
                        'fecha' => $key,
                        'fecha_formato' => $cCur->format('d/m'),
                        'dia_semana' => $diasEspanol[$diaIng] ?? $diaIng,
                        'creditos' => 0,
                        'monto' => 0.0,
                    ];
                    $cCur->addDay();
                }

                foreach ($creditos as $c) {
                    $f = Carbon::parse($c->created_at)->toDateString();
                    if (isset($evolucionDiaria[$f])) {
                        $evolucionDiaria[$f]['creditos']++;
                        $evolucionDiaria[$f]['monto'] += (float) $c->monto_credito;
                    }
                }
            } else {
                $mesesAgrupados = $creditos->groupBy(function ($item) {
                    return Carbon::parse($item->created_at)->format('Y-m');
                })->sortKeys();

                foreach ($mesesAgrupados as $mesKey => $items) {
                    $cMes = Carbon::parse($mesKey . '-01');
                    $evolucionDiaria[$mesKey] = [
                        'fecha' => $mesKey,
                        'fecha_formato' => $cMes->format('m/Y'),
                        'dia_semana' => $cMes->translatedFormat('M'),
                        'creditos' => $items->count(),
                        'monto' => (float) $items->sum('monto_credito'),
                    ];
                }
            }
        } else {
            $agrupados = $creditos->groupBy(function ($item) {
                return Carbon::parse($item->created_at)->toDateString();
            })->sortKeys();

            foreach ($agrupados as $fKey => $items) {
                $cDate = Carbon::parse($fKey);
                $diaIng = $cDate->format('D');
                $evolucionDiaria[$fKey] = [
                    'fecha' => $fKey,
                    'fecha_formato' => $cDate->format('d/m'),
                    'dia_semana' => $diasEspanol[$diaIng] ?? $diaIng,
                    'creditos' => $items->count(),
                    'monto' => (float) $items->sum('monto_credito'),
                ];
            }
        }

        // ========================================================
        // 5. LISTADO DETALLADO PARA TABLA
        // ========================================================
        $itemsTabla = $creditos->map(function ($item) {
            $sucNombre = $item->sucursal ? $item->sucursal->nombre : 'Sin sucursal';
            $zonaNombre = $item->sucursal && $item->sucursal->razonSocial ? $item->sucursal->razonSocial->nombre : 'Sin zona';
            $hasEvidencia = !empty($item->evidencia_path);

            return [
                'id'                 => $item->id,
                'created_at'         => $item->created_at ? $item->created_at->format('Y-m-d H:i') : '',
                'fecha_formato'      => $item->created_at ? $item->created_at->format('d/m/Y') : '',
                'sucursal'           => $sucNombre,
                'zona'               => $zonaNombre,
                'colaborador'        => $item->colaborador_nombre ?: 'Sin especificar',
                'comprador'          => $item->comprador ?: 'Sin nombre',
                'articulo_id'        => $item->articulo_id ?: '-',
                'articulo'           => $item->articulo ?: 'Sin descripción',
                'precio_prestamo'    => (float) $item->precio_prestamo,
                'precio_venta'       => (float) $item->precio_venta,
                'monto_credito'      => (float) $item->monto_credito,
                'plazo'              => $item->plazo ?: ($item->forma_pago ?: '-'),
                'metodo_pago'        => $item->metodo_pago ?: 'Tarjeta',
                'has_evidencia'      => $hasEvidencia,
                'evidencia_url'      => route('credito-externo.evidencia', $item->id),
                'observaciones'      => $item->observaciones ?: '',
                'estado'             => $item->estado ?: 'activo',
            ];
        })->values();

        return response()->json([
            'kpis_zonas'           => $kpisZonas,
            'kpi_total_general'    => $kpiTotalGeneral,
            'metricas_periodo'     => [
                'total_creditos'        => $totalCreditos,
                'monto_total'           => $montoTotal,
                'ticket_promedio'       => $ticketPromedio,
                'precio_venta_total'    => $precioVentaTotal,
                'precio_prestamo_total' => $precioPrestamoTotal,
                'diferencia_financiera' => $diferenciaFinanciera,
                'margen_porcentaje'     => $margenPorcentaje,
                'top_colaborador'       => $topColaborador,
                'top_sucursal'          => $topSucursal,
            ],
            'evolucion_diaria'     => array_values($evolucionDiaria),
            'ranking_sucursales'   => $sucursalesRanking->values()->take(10),
            'ranking_colaboradores'=> $colaboradoresRanking->values()->take(10),
            'ranking_plazos'       => $plazosRanking->values(),
            'ranking_zonas'        => $zonasRanking->values(),
            'creditos'             => $itemsTabla,
        ]);
    }

    /**
     * Descarga / Visualización de evidencia física adjunta
     */
    public function evidencia($id)
    {
        $this->checkAccess();

        $credito = CreditoExterno::findOrFail($id);
        if (empty($credito->evidencia_path)) {
            abort(404, 'No hay evidencia adjunta para este crédito.');
        }

        $cleanPath = str_replace(['public/', 'public\\'], '', $credito->evidencia_path);

        $possiblePaths = [
            storage_path('app/' . $credito->evidencia_path),
            storage_path('app/public/' . $cleanPath),
            public_path('storage/' . $cleanPath),
            'C:/Users/Programador/Desktop/intranet_valoramas/storage/app/' . $credito->evidencia_path,
            'C:/Users/Programador/Desktop/intranet_valoramas/storage/app/public/' . $cleanPath,
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                $mimeType = File::mimeType($path) ?: 'application/octet-stream';
                return response()->file($path, [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="' . ($credito->evidencia_nombre_original ?: basename($path)) . '"',
                ]);
            }
        }

        abort(404, 'El archivo físico de evidencia no fue encontrado en el servidor.');
    }
}

