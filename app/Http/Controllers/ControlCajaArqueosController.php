<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ControlCajaArqueosController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('control-caja-arqueos.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        ini_set('max_execution_time', 240);

        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        $fechaFin = $request->input('fecha_fin', now()->toDateString());
        $sucursalId = $request->input('sucursal_id');

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');

        $cierres = [];
        $arqueos = [];

        // Dynamic date queries
        $fechaInicioQuery = $fechaInicio . ' 00:00:00';
        $fechaFinQuery = $fechaFin . ' 23:59:59';

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'dynamic_caja_arqueos_' . $sucursal->id_valora_mas;

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // 1. Fetch Cierres
                $cierresRows = DB::connection($connectionName)->select("
                    SELECT 
                        c.cod_cierre,
                        c.cod_caja,
                        caja.caja as caja_nombre,
                        c.fecha,
                        c.incio,
                        c.termino,
                        c.total_general,
                        c.diferencia,
                        c.f_cierre,
                        c.cod_usuario,
                        u.nombre as usuario_nombre,
                        c.cod_usuario_valida,
                        uv.nombre as usuario_valida_nombre,
                        c.total_boveda,
                        c.transferencias,
                        c.clip,
                        c.depositos,
                        c.terminal
                    FROM cierre c
                    LEFT JOIN cajas caja ON c.cod_caja = caja.cod_caja
                    LEFT JOIN usuarios u ON c.cod_usuario = u.cod_usuario
                    LEFT JOIN usuarios uv ON c.cod_usuario_valida = uv.cod_usuario
                    WHERE c.fecha BETWEEN ? AND ?
                    ORDER BY c.f_cierre DESC
                ", [$fechaInicioQuery, $fechaFinQuery]);

                foreach ($cierresRows as $row) {
                    $cierres[] = [
                        'id' => $row->cod_cierre,
                        'cod_caja' => $row->cod_caja,
                        'caja_nombre' => $row->caja_nombre ?: 'Caja ' . $row->cod_caja,
                        'fecha' => Carbon::parse($row->fecha)->toDateString(),
                        'incio' => (float)$row->incio,
                        'termino' => (float)$row->termino,
                        'total_general' => (float)$row->total_general,
                        'diferencia' => (float)$row->diferencia,
                        'f_cierre' => $row->f_cierre ? Carbon::parse($row->f_cierre)->toDateTimeString() : null,
                        'usuario_nombre' => $row->usuario_nombre ?: 'Sistema',
                        'usuario_valida_nombre' => $row->usuario_valida_nombre ?: 'N/A',
                        'total_boveda' => (float)$row->total_boveda,
                        'transferencias' => (float)$row->transferencias,
                        'clip' => (float)$row->clip,
                        'depositos' => (float)$row->depositos,
                        'terminal' => (float)$row->terminal,
                        'sucursal_nombre' => $sucursal->nombre,
                        'id_valora_mas' => $sucursal->id_valora_mas
                    ];
                }

                // 2. Fetch Arqueos
                $arqueosRows = DB::connection($connectionName)->select("
                    SELECT 
                        a.cod_arqueo,
                        a.cod_caja,
                        caja.caja as caja_nombre,
                        a.fecha,
                        a.incio,
                        a.termino,
                        a.total_general,
                        a.diferencia,
                        a.f_cierre,
                        a.cod_usuario,
                        u.nombre as usuario_nombre,
                        a.cod_usuario_valida,
                        uv.nombre as usuario_valida_nombre,
                        a.total_boveda,
                        a.transferencias,
                        a.clip,
                        a.depositos,
                        a.terminal
                    FROM arqueo_historial a
                    LEFT JOIN cajas caja ON a.cod_caja = caja.cod_caja
                    LEFT JOIN usuarios u ON a.cod_usuario = u.cod_usuario
                    LEFT JOIN usuarios uv ON a.cod_usuario_valida = uv.cod_usuario
                    WHERE a.fecha BETWEEN ? AND ?
                    ORDER BY a.f_cierre DESC
                ", [$fechaInicioQuery, $fechaFinQuery]);

                foreach ($arqueosRows as $row) {
                    $arqueos[] = [
                        'id' => $row->cod_arqueo,
                        'cod_caja' => $row->cod_caja,
                        'caja_nombre' => $row->caja_nombre ?: 'Caja ' . $row->cod_caja,
                        'fecha' => Carbon::parse($row->fecha)->toDateString(),
                        'incio' => (float)$row->incio,
                        'termino' => (float)$row->termino,
                        'total_general' => (float)$row->total_general,
                        'diferencia' => (float)$row->diferencia,
                        'f_cierre' => $row->f_cierre ? Carbon::parse($row->f_cierre)->toDateTimeString() : null,
                        'usuario_nombre' => $row->usuario_nombre ?: 'Sistema',
                        'usuario_valida_nombre' => $row->usuario_valida_nombre ?: 'N/A',
                        'total_boveda' => (float)$row->total_boveda,
                        'transferencias' => (float)$row->transferencias,
                        'clip' => (float)$row->clip,
                        'depositos' => (float)$row->depositos,
                        'terminal' => (float)$row->terminal,
                        'sucursal_nombre' => $sucursal->nombre,
                        'id_valora_mas' => $sucursal->id_valora_mas
                    ];
                }

            } catch (\Exception $e) {
                Log::warning("Error fetching caja/arqueos for Sucursal {$sucursal->nombre} ({$sucursal->id_valora_mas}): " . $e->getMessage());
            }
        }

        // Sort combined results by f_cierre DESC
        usort($cierres, function($a, $b) {
            return strcmp($b['f_cierre'] ?? '', $a['f_cierre'] ?? '');
        });
        usort($arqueos, function($a, $b) {
            return strcmp($b['f_cierre'] ?? '', $a['f_cierre'] ?? '');
        });

        // Compute KPIs for Cierres
        $kpisCierres = $this->calculateKpis($cierres);

        // Compute KPIs for Arqueos
        $kpisArqueos = $this->calculateKpis($arqueos);

        // Group data for line charts
        $chartCierres = $this->prepareChartData($cierres);
        $chartArqueos = $this->prepareChartData($arqueos);

        return response()->json([
            'cierres' => $cierres,
            'arqueos' => $arqueos,
            'kpisCierres' => $kpisCierres,
            'kpisArqueos' => $kpisArqueos,
            'chartCierres' => $chartCierres,
            'chartArqueos' => $chartArqueos
        ]);
    }

    private function calculateKpis($records)
    {
        $total = count($records);
        $sumDiferencia = 0.0;
        $frecuenciaDiferencias = 0;
        $cuadrados = 0;

        $ultimoSaldo = 0.0;
        $ultimoSaldoFecha = null;
        $ultimoSaldoBoveda = 0.0;

        // Group by date to find latest date for the "last day balance"
        $datesData = [];
        $datesBovedaData = [];

        foreach ($records as $row) {
            $sumDiferencia += $row['diferencia'];
            if (abs($row['diferencia']) > 0.01) {
                $frecuenciaDiferencias++;
            } else {
                $cuadrados++;
            }

            $fecha = $row['fecha'];
            $sucId = $row['id_valora_mas'] ?? 'default';

            if (!isset($datesData[$fecha])) {
                $datesData[$fecha] = 0.0;
            }
            $datesData[$fecha] += $row['total_general'];

            // Save the latest total_boveda per sucursal on that date
            if (!isset($datesBovedaData[$fecha])) {
                $datesBovedaData[$fecha] = [];
            }
            if (!isset($datesBovedaData[$fecha][$sucId])) {
                $datesBovedaData[$fecha][$sucId] = (float)($row['total_boveda'] ?? 0);
            }
        }

        $pctCuadrado = $total > 0 ? round(($cuadrados / $total) * 100, 1) : 100.0;

        // Find latest date and sum
        if (!empty($datesData)) {
            krsort($datesData); // Sort by date descending
            $ultimoSaldoFecha = array_key_first($datesData);
            $ultimoSaldo = $datesData[$ultimoSaldoFecha];

            if (isset($datesBovedaData[$ultimoSaldoFecha])) {
                $ultimoSaldoBoveda = array_sum($datesBovedaData[$ultimoSaldoFecha]);
            }

            $ultimoSaldoFechaFormatted = Carbon::parse($ultimoSaldoFecha)->format('d/m/Y');
        } else {
            $ultimoSaldoFechaFormatted = null;
        }

        return [
            'total_registros' => $total,
            'diferencia_acumulada' => $sumDiferencia,
            'frecuencia_diferencias' => $frecuenciaDiferencias,
            'pct_cuadrado' => $pctCuadrado,
            'ultimo_saldo' => $ultimoSaldo,
            'ultimo_saldo_boveda' => $ultimoSaldoBoveda,
            'ultimo_saldo_fecha' => $ultimoSaldoFechaFormatted
        ];
    }

    private function prepareChartData($records)
    {
        $grouped = [];

        foreach ($records as $row) {
            $fecha = $row['fecha'];
            if (!isset($grouped[$fecha])) {
                $grouped[$fecha] = [
                    'fisico' => 0.0,
                    'sistema' => 0.0
                ];
            }
            $grouped[$fecha]['fisico'] += $row['total_general'];
            $grouped[$fecha]['sistema'] += $row['termino'];
        }

        // Sort by date ascending
        ksort($grouped);

        $labels = [];
        $fisico = [];
        $sistema = [];

        foreach ($grouped as $fecha => $data) {
            $labels[] = Carbon::parse($fecha)->format('d/m/Y');
            $fisico[] = round($data['fisico'], 2);
            $sistema[] = round($data['sistema'], 2);
        }

        return [
            'labels' => $labels,
            'fisico' => $fisico,
            'sistema' => $sistema
        ];
    }
}
