<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class ReportePagosTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('reporte-pagos-tarjeta.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        
        $sucursalId = $request->input('sucursal_id');
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        $sucursalesSeleccionadas = $sucursalId
            ? $sucursales->where('id_valora_mas', $sucursalId)
            : $sucursales;

        $baseConfig = Config::get('database.connections.mysql');
        
        $detalleMovimientos = []; 
        $totalMonto = 0;
        $totalComision = 0;
        $totalGeneral = 0;

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'dynamic_report_pagos_tarjeta';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // We query transactions where card amount > 0 OR card/mix payment method OR transaction type is set
                $rows = DB::connection($connectionName)->select("
                    SELECT 
                        m.cod_movimiento,
                        m.contrato,
                        m.f_alta,
                        tm.tipo_movimiento AS concepto,
                        m.referencia,
                        m.monto_tarjeta,
                        m.monto_efectivo,
                        m.cod_tipo_pago,
                        tp.tipo_pago AS tipo_pago_nombre,
                        m.cod_tipo_transaccion,
                        tt.tipo_transaccion AS tipo_transaccion_nombre,
                        m.f_cancela,
                        m.cod_estatus
                    FROM movimientos m
                    LEFT JOIN tipo_movimiento tm ON m.cod_tipo_movimiento = tm.cod_tipo_movimiento
                    LEFT JOIN tipo_pago tp ON m.cod_tipo_pago = tp.cod_tipo_pago
                    LEFT JOIN tipo_transaccion tt ON m.cod_tipo_transaccion = tt.cod_tipo_transaccion
                    WHERE m.f_alta BETWEEN ? AND ?
                      AND (m.monto_tarjeta > 0 OR m.cod_tipo_pago IN (2, 3) OR m.cod_tipo_transaccion IS NOT NULL)
                    ORDER BY m.f_alta ASC
                ", [$fechaInicio, $fechaFin]);

                foreach ($rows as $row) {
                    $monto = (float) $row->monto_tarjeta;
                    $comision = 0.00; // As per receipt, commission default is 0
                    $total = $monto;

                    $esCancelado = !empty($row->f_cancela) || $row->cod_estatus == 3;
                    
                    // Format concept to match receipt format (uppercase)
                    $concepto = $row->concepto ? strtoupper($row->concepto) : 'OTRO';

                    // Map tipo_pago
                    $tipoPago = 'NO DEFINIDO';
                    if (!empty($row->tipo_pago_nombre)) {
                        $tipoPago = strtoupper($row->tipo_pago_nombre);
                    } else {
                        if ($row->cod_tipo_pago == 1) {
                            $tipoPago = 'EFECTIVO';
                        } elseif ($row->cod_tipo_pago == 2) {
                            $tipoPago = 'TARJETA';
                        } elseif ($row->cod_tipo_pago == 3) {
                            $tipoPago = 'MIXTO';
                        }
                    }

                    // Map tipo_transaccion
                    $tipoTransaccion = 'NO DEFINIDO';
                    if (!empty($row->tipo_transaccion_nombre)) {
                        $tipoTransaccion = strtoupper($row->tipo_transaccion_nombre);
                    }

                    $detalleMovimientos[] = [
                        'sucursal' => $sucursal->nombre,
                        'fecha' => date('Y-m-d H:i:s', strtotime($row->f_alta)),
                        'contrato' => $row->contrato,
                        'concepto' => $concepto,
                        'voucher' => $esCancelado ? 'CANCELADA' : '',
                        'referencia' => $row->referencia ?? '',
                        'monto' => $monto,
                        'comision' => $comision,
                        'total' => $total,
                        'tipo_pago' => $tipoPago,
                        'transaccion' => $tipoTransaccion,
                        'status' => $esCancelado ? 'CANCELADO' : 'CONFIRMADO'
                    ];

                    if (!$esCancelado) {
                        $totalMonto += $monto;
                        $totalComision += $comision;
                        $totalGeneral += $total;
                    }
                }

            } catch (\Exception $e) {
                Log::error("Error reporte pagos tarjeta en {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // Sort movements by date descending
        usort($detalleMovimientos, function($a, $b) {
            return strtotime($b['fecha']) <=> strtotime($a['fecha']);
        });

        return response()->json([
            'detalleMovimientos' => $detalleMovimientos,
            'totalMonto' => $totalMonto,
            'totalComision' => $totalComision,
            'totalGeneral' => $totalGeneral,
        ]);
    }
}
