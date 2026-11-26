<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClienteTarjeta;
use App\Models\MovimientoTarjeta;
use App\Models\PromocionMonedero;
use App\Models\StockTarjeta;
use App\Models\TipoAccion;
use App\Models\Sucursal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TarjetasLealtadController extends Controller
{
    /**
     * Tasa de conversión oficial: 3 Puntos = $1.00 Peso MXN
     */
    private const PUNTOS_POR_PESO = 3.0;

    /**
     * Verifica acceso al módulo Power BI de Tarjetas de Lealtad (Rol 22).
     */
    private function checkAccess()
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'No autorizado.');
        }

        if ($user->hasPowerBiRole(22) || $user->is_admin || (method_exists($user, 'getPbiRoleType') && $user->getPbiRoleType() === 'super_admin')) {
            return;
        }

        if (method_exists($user, 'hasPermission') && ($user->hasPermission(30) || $user->hasPermission(31))) {
            return;
        }

        abort(403, 'No tienes permiso para acceder al módulo de Tarjetas de Lealtad.');
    }

    /**
     * Pantalla principal del módulo de Tarjetas de Lealtad.
     */
    public function index(Request $request)
    {
        $this->checkAccess();

        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();

        $sucursales = Sucursal::whereNotNull('id_valora_mas')
            ->where('id_valora_mas', '!=', 5)
            ->where('nombre', 'not like', '%OFICINA%')
            ->orderBy('nombre', 'asc')
            ->get();

        return view('tarjetas-lealtad.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    /**
     * Endpoint AJAX con todos los KPIs, series temporales, rankings y directorio de clientes.
     */
    public function data(Request $request)
    {
        $this->checkAccess();

        try {
            $rawInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
            $fechaInicio = (strlen($rawInicio) <= 10 ? $rawInicio . ' 00:00:00' : $rawInicio);

            $rawFin = $request->input('fecha_fin', now()->toDateString());
            $fechaFin = (strlen($rawFin) <= 10 ? $rawFin . ' 23:59:59' : $rawFin);

            $sucursalId = $request->input('sucursal_id');

            // 1. Consulta base de movimientos activos en el periodo
            $queryPeriodo = MovimientoTarjeta::query()
                ->where('status', 'Activo')
                ->whereBetween('created_at', [$fechaInicio, $fechaFin]);

            if (!empty($sucursalId)) {
                $queryPeriodo->where('sucursal_id', $sucursalId);
            }

            $movimientosPeriodo = $queryPeriodo->get();

            // Puntos acumulados en el periodo (positivos, excluyendo canjes)
            $puntosAcumuladosPeriodo = (float) $movimientosPeriodo
                ->where('tipo_accion_id', '!=', 12)
                ->where('puntos_generados', '>', 0)
                ->sum('puntos_generados');

            // Canjes en el periodo (tipo_accion_id = 12 o puntos negativos)
            $movsCanjePeriodo = $movimientosPeriodo->filter(function ($m) {
                return $m->tipo_accion_id == 12 || $m->puntos_generados < 0;
            });

            $puntosCanjeadosPeriodo = abs((float) $movsCanjePeriodo->sum('puntos_generados'));
            $totalCanjesPeriodo = $movsCanjePeriodo->count();

            $pesosAcumuladosPeriodo = round($puntosAcumuladosPeriodo / self::PUNTOS_POR_PESO, 2);
            $pesosCanjeadosPeriodo = round($puntosCanjeadosPeriodo / self::PUNTOS_POR_PESO, 2);

            // 2. Saldo activo histórico global
            $querySaldo = MovimientoTarjeta::query()->where('status', 'Activo');
            if (!empty($sucursalId)) {
                $querySaldo->where('sucursal_id', $sucursalId);
            }
            $saldoActivoPuntos = (float) $querySaldo->sum('puntos_generados');
            $saldoActivoPuntos = max(0, round($saldoActivoPuntos, 2));
            $saldoActivoPesos = round($saldoActivoPuntos / self::PUNTOS_POR_PESO, 2);

            // 3. Campañas y promociones
            $campanasQuery = PromocionMonedero::query();
            $campanasActivasCount = PromocionMonedero::where('status', 'Activa')
                ->where('fecha_vencimiento', '>=', now())
                ->count();

            $campanasEnRango = PromocionMonedero::where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
                  ->orWhereBetween('fecha_vencimiento', [$fechaInicio, $fechaFin])
                  ->orWhere(function ($sub) use ($fechaInicio, $fechaFin) {
                      $sub->where('fecha_inicio', '<=', $fechaInicio)
                          ->where('fecha_vencimiento', '>=', $fechaFin);
                  });
            })->get();

            $campanasTotalRango = $campanasEnRango->count();

            $campanasList = [];
            foreach ($campanasEnRango as $camp) {
                $ptsGenCamp = (float) MovimientoTarjeta::where('promocion_id', $camp->id)
                    ->where('status', 'Activo')
                    ->whereBetween('created_at', [$fechaInicio, $fechaFin])
                    ->sum('puntos_generados');

                $campanasList[] = [
                    'id' => $camp->id,
                    'nombre' => $camp->nombre,
                    'tipo_campana' => $camp->tipo_campana ?: 'General',
                    'factor_multiplicador' => (float) $camp->factor_multiplicador,
                    'monto_promocional' => (float) $camp->monto_promocional,
                    'fecha_inicio' => $camp->fecha_inicio ? Carbon::parse($camp->fecha_inicio)->format('d/m/Y') : '-',
                    'fecha_vencimiento' => $camp->fecha_vencimiento ? Carbon::parse($camp->fecha_vencimiento)->format('d/m/Y') : '-',
                    'puntos_generados' => max(0, $ptsGenCamp),
                    'status' => $camp->status === 'Activa' ? 'Activa' : 'Inactiva',
                ];
            }

            // 4. Clientes totales
            $totalClientesRegistrados = ClienteTarjeta::count();

            // 5. Desglose de Canjes por Operación
            $tiposAccionMap = TipoAccion::pluck('nombre', 'id')->toArray();
            $canjesPorOpMap = [];
            foreach ($movsCanjePeriodo as $m) {
                $nombreOp = 'Venta de Contado';
                if (!empty($m->codigo_movimiento) && stripos($m->codigo_movimiento, 'DESEMP') !== false) {
                    $nombreOp = 'Desempeño';
                } elseif (!empty($m->codigo_movimiento) && stripos($m->codigo_movimiento, 'REF') !== false) {
                    $nombreOp = 'Refrendo';
                } elseif (!empty($m->codigo_movimiento) && stripos($m->codigo_movimiento, 'EMP') !== false) {
                    $nombreOp = 'Empeño';
                } elseif (!empty($m->codigo_movimiento) && stripos($m->codigo_movimiento, 'APAR') !== false) {
                    $nombreOp = 'Apartado';
                } elseif ($m->tipo_accion_id && isset($tiposAccionMap[$m->tipo_accion_id]) && $m->tipo_accion_id != 12) {
                    $nombreOp = mb_convert_case($tiposAccionMap[$m->tipo_accion_id], MB_CASE_TITLE, 'UTF-8');
                }

                if (!isset($canjesPorOpMap[$nombreOp])) {
                    $canjesPorOpMap[$nombreOp] = [
                        'operacion_origen' => $nombreOp,
                        'total_canjes' => 0,
                        'puntos_canjeados' => 0.0,
                        'pesos_descuento' => 0.0,
                    ];
                }
                $pts = abs((float) $m->puntos_generados);
                $canjesPorOpMap[$nombreOp]['total_canjes']++;
                $canjesPorOpMap[$nombreOp]['puntos_canjeados'] += $pts;
                $canjesPorOpMap[$nombreOp]['pesos_descuento'] += round($pts / self::PUNTOS_POR_PESO, 2);
            }

            $canjesPorOperacion = array_values($canjesPorOpMap);
            usort($canjesPorOperacion, fn($a, $b) => $b['puntos_canjeados'] <=> $a['puntos_canjeados']);

            foreach ($canjesPorOperacion as &$op) {
                $op['porcentaje'] = $puntosCanjeadosPeriodo > 0 
                    ? round(($op['puntos_canjeados'] / $puntosCanjeadosPeriodo) * 100, 1) 
                    : 0.0;
            }
            unset($op);

            $topOperacionCanje = !empty($canjesPorOperacion) ? $canjesPorOperacion[0] : null;

            // 6. Evolución diaria dentro del periodo
            $evolucionDiaria = [];
            $curDate = Carbon::parse(substr($fechaInicio, 0, 10));
            $endDate = Carbon::parse(substr($fechaFin, 0, 10));
            $diasEspanol = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom'];

            $movsGroupedDay = $movimientosPeriodo->groupBy(fn($m) => Carbon::parse($m->created_at)->toDateString());

            while ($curDate->lte($endDate)) {
                $dStr = $curDate->toDateString();
                $dayMovs = $movsGroupedDay->get($dStr, collect());

                $ptsGanados = (float) $dayMovs->where('tipo_accion_id', '!=', 12)->where('puntos_generados', '>', 0)->sum('puntos_generados');
                $ptsCanje = abs((float) $dayMovs->filter(fn($m) => $m->tipo_accion_id == 12 || $m->puntos_generados < 0)->sum('puntos_generados'));

                $diaIng = $curDate->format('D');
                $evolucionDiaria[] = [
                    'fecha' => $dStr,
                    'fecha_formato' => $curDate->format('d/m'),
                    'dia_semana' => $diasEspanol[$diaIng] ?? $diaIng,
                    'puntos_ganados' => round($ptsGanados, 2),
                    'puntos_canje' => round($ptsCanje, 2),
                    'pesos_canje' => round($ptsCanje / self::PUNTOS_POR_PESO, 2),
                ];

                $curDate->addDay();
            }

            // 7. Directorio de clientes con saldos y métricas
            $clientesData = DB::table('clientes_tarjeta as c')
                ->leftJoin('stocktarjetas as st', 'st.id', '=', 'c.tarjeta_id')
                ->leftJoin('sucursals as s', 's.id', '=', 'st.id_sucursal')
                ->select(
                    'c.id',
                    'c.nombre',
                    'c.primer_apellido',
                    'c.segundo_apellido',
                    'c.telefono',
                    'c.correo',
                    'st.codigo_tarjeta',
                    's.nombre as sucursal_nombre'
                )
                ->get();

            // Agrupar movimientos históricos por cliente
            $movsHistPorCliente = DB::table('movimientos_tarjeta')
                ->where('status', 'Activo')
                ->select(
                    'cliente_tarjeta_id',
                    DB::raw("SUM(CASE WHEN tipo_accion_id != 12 AND puntos_generados > 0 THEN puntos_generados ELSE 0 END) as total_acumulados"),
                    DB::raw("SUM(CASE WHEN tipo_accion_id = 12 OR puntos_generados < 0 THEN ABS(puntos_generados) ELSE 0 END) as total_canjeados"),
                    DB::raw("SUM(puntos_generados) as saldo_activo"),
                    DB::raw("MAX(CASE WHEN tipo_accion_id = 12 OR puntos_generados < 0 THEN created_at ELSE NULL END) as ultimo_canje")
                )
                ->groupBy('cliente_tarjeta_id')
                ->get()
                ->keyBy('cliente_tarjeta_id');

            // Canjes específicos en el periodo para el ranking del Top 10
            $canjesPeriodoPorCliente = $movsCanjePeriodo->groupBy('cliente_tarjeta_id');

            $clientesList = [];
            $topClientesList = [];

            foreach ($clientesData as $c) {
                $mHist = $movsHistPorCliente->get($c->id);

                $nombreCompleto = trim("{$c->nombre} {$c->primer_apellido} {$c->segundo_apellido}");
                $puntosAcumulados = $mHist ? round((float)$mHist->total_acumulados, 2) : 0.0;
                $puntosCanjeados = $mHist ? round((float)$mHist->total_canjeados, 2) : 0.0;
                $saldoPuntos = $mHist ? max(0, round((float)$mHist->saldo_activo, 2)) : 0.0;
                $saldoPesos = round($saldoPuntos / self::PUNTOS_POR_PESO, 2);
                $hasCanje = $mHist && !empty($mHist->ultimo_canje);
                $ultimaFechaCanje = $hasCanje ? Carbon::parse($mHist->ultimo_canje)->format('d/m/Y') : '-';

                $clientesList[] = [
                    'id' => $c->id,
                    'nombre' => $nombreCompleto ?: 'Cliente sin nombre',
                    'telefono' => $c->telefono ?: '-',
                    'correo' => $c->correo ?: '-',
                    'codigo_tarjeta' => $c->codigo_tarjeta ?: 'S/T',
                    'sucursal_registro' => $c->sucursal_nombre ?: 'Matriz',
                    'puntos_acumulados' => $puntosAcumulados,
                    'puntos_canjeados' => $puntosCanjeados,
                    'saldo_activo_puntos' => $saldoPuntos,
                    'saldo_activo_pesos' => $saldoPesos,
                    'has_canje' => $hasCanje,
                    'ultima_fecha_canje' => $ultimaFechaCanje,
                ];

                // Evaluar para el Top 10 si tuvo canjes en el periodo
                $canjesCliPeriodo = $canjesPeriodoPorCliente->get($c->id);
                if ($canjesCliPeriodo && $canjesCliPeriodo->count() > 0) {
                    $ptsCanjeadosPer = abs((float) $canjesCliPeriodo->sum('puntos_generados'));
                    $ultimoCanjePer = $canjesCliPeriodo->max('created_at');

                    $topClientesList[] = [
                        'cliente_id' => $c->id,
                        'cliente_nombre' => $nombreCompleto ?: 'Cliente sin nombre',
                        'telefono' => $c->telefono ?: '-',
                        'codigo_tarjeta' => $c->codigo_tarjeta ?: 'S/T',
                        'sucursal_nombre' => $c->sucursal_nombre ?: 'General',
                        'total_canjes' => $canjesCliPeriodo->count(),
                        'puntos_canjeados' => round($ptsCanjeadosPer, 2),
                        'pesos_canjeados' => round($ptsCanjeadosPer / self::PUNTOS_POR_PESO, 2),
                        'saldo_activo_puntos' => $saldoPuntos,
                        'saldo_activo_pesos' => $saldoPesos,
                        'ultimo_canje_formato' => $ultimoCanjePer ? Carbon::parse($ultimoCanjePer)->format('d/m/Y') : '-',
                    ];
                }
            }

            // Ordenar Top 10 por puntos canjeados descendente
            usort($topClientesList, fn($a, $b) => $b['puntos_canjeados'] <=> $a['puntos_canjeados']);
            $topClientesList = array_slice($topClientesList, 0, 10);

            // Ordenar directorio de clientes por saldo de puntos activo descendente
            usort($clientesList, fn($a, $b) => $b['saldo_activo_puntos'] <=> $a['saldo_activo_puntos']);

            return response()->json([
                'success' => true,
                'configuracion' => [
                    'puntos_por_peso' => self::PUNTOS_POR_PESO,
                ],
                'kpis' => [
                    'puntos_acumulados_periodo' => $puntosAcumuladosPeriodo,
                    'pesos_acumulados_periodo' => $pesosAcumuladosPeriodo,
                    'puntos_canjeados_periodo' => $puntosCanjeadosPeriodo,
                    'pesos_canjeados_periodo' => $pesosCanjeadosPeriodo,
                    'total_canjes_periodo' => $totalCanjesPeriodo,
                    'saldo_activo_puntos' => $saldoActivoPuntos,
                    'saldo_activo_pesos' => $saldoActivoPesos,
                    'campanas_activas_count' => $campanasActivasCount,
                    'campanas_total_rango' => $campanasTotalRango,
                    'total_clientes_registrados' => $totalClientesRegistrados,
                    'top_operacion_canje' => $topOperacionCanje,
                ],
                'evolucion_diaria' => $evolucionDiaria,
                'canjes_por_operacion' => $canjesPorOperacion,
                'top_clientes' => $topClientesList,
                'campanas' => $campanasList,
                'clientes' => $clientesList,
            ]);

        } catch (\Exception $e) {
            Log::error("Error en TarjetasLealtadController@data: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Historial pormenorizado de movimientos para el modal de un cliente específico.
     */
    public function clienteHistorial($id)
    {
        $this->checkAccess();

        try {
            $cliente = ClienteTarjeta::with('stockTarjeta')->findOrFail($id);

            $tiposAccionMap = TipoAccion::pluck('nombre', 'id')->toArray();
            $sucursalesMap = Sucursal::pluck('nombre', 'id')->toArray();

            $movimientos = MovimientoTarjeta::with('promocion')
                ->where('cliente_tarjeta_id', $id)
                ->where('status', 'Activo')
                ->orderBy('created_at', 'desc')
                ->get();

            $movsFormatted = [];
            foreach ($movimientos as $m) {
                $esCanje = $m->tipo_accion_id == 12 || $m->puntos_generados < 0;
                $accionNombre = $tiposAccionMap[$m->tipo_accion_id] ?? 'MOVIMIENTO';

                $movsFormatted[] = [
                    'fecha' => Carbon::parse($m->created_at)->format('d/m/Y H:i'),
                    'tipo_accion' => $accionNombre,
                    'codigo_movimiento' => $m->codigo_movimiento ?: '-',
                    'contrato' => $m->contrato ?: '-',
                    'monto' => (float) $m->monto,
                    'puntos' => (float) $m->puntos_generados,
                    'es_canje' => $esCanje,
                    'sucursal' => $sucursalesMap[$m->sucursal_id] ?? 'General',
                    'promocion' => $m->promocion ? $m->promocion->nombre : '-',
                ];
            }

            return response()->json([
                'success' => true,
                'cliente' => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre_completo,
                    'telefono' => $cliente->telefono ?: 'Sin teléfono',
                    'codigo_tarjeta' => $cliente->stockTarjeta ? $cliente->stockTarjeta->codigo_tarjeta : 'S/T',
                ],
                'movimientos' => $movsFormatted,
            ]);

        } catch (\Exception $e) {
            Log::error("Error en TarjetasLealtadController@clienteHistorial: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'No fue posible obtener el historial del cliente.',
            ], 500);
        }
    }
}
