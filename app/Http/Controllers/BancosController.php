<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class BancosController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('bancos.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
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

        $baseConfig = Config::get('database.connections.mysql');
        
        $saldoTotalBancos = 0; // Proxy = Total Tarjeta Reclutado
        $saldoEfectivo = 0; // Proxy = Efectivo Reclutado
        
        $flujoNetoMensual = 0;
        $totalEntradas = 0;
        $totalSalidas = 0;

        $chartEvolucionSaldos = [
            'labels' => [], // Sucursales
            'flujo_efectivo' => [],
            'flujo_bancos' => []
        ];

        $chartFlujosMensuales = [
            'labels' => ['Entradas (Efectivo+Banco)', 'Salidas (Empeños+Gastos)', 'Flujo Libre Neto'],
            'data' => [0, 0, 0]
        ];

        $totalIngresoEf = 0;
        $totalIngresoTar = 0;
        
        $totalGastoOp = 0;
        $totalGastoEmpeno = 0;

        $detalleCuentas = []; 
        $detalleMovimientos = []; 

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'bancos_dynamic';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // 1. Entradas (Asumimos que la tabla movimientos agrupa los ingresos en Efectivo vs Tarjeta)
                // Ignoramos cod_tipo_movimiento = 1 porque suele ser Empeño (que es salida, aunque no siempre se guarda como positivo ahí, mejor lo scaneamos de contratos)
                $ingresosQ = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(monto_efectivo), 0) as efectivo,
                        COALESCE(SUM(monto_tarjeta), 0) as tarjeta
                    FROM movimientos
                    WHERE f_cancela IS NULL AND f_alta BETWEEN ? AND ?
                      AND cod_tipo_movimiento <> 1
                ", [$fechaInicio, $fechaFin]);

                $sucEf = (float) $ingresosQ->efectivo;
                $sucTar = (float) $ingresosQ->tarjeta;

                $totalIngresoEf += $sucEf;
                $totalIngresoTar += $sucTar;

                $saldoEfectivo += $sucEf;
                $saldoTotalBancos += $sucTar; // proxy para Bancos
                
                $sucEntradas = $sucEf + $sucTar;
                $totalEntradas += $sucEntradas;

                // 2. Salidas: Gastos Operativos
                $gastosQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(COALESCE(autorizado, solicitado, 0)), 0) as total_gasto
                    FROM gastos 
                    WHERE f_cancelacion IS NULL AND activo = 1 
                      AND COALESCE(f_aplicacion, f_autorizado, f_solicitado) BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);
                
                $sucGastos = (float) $gastosQ->total_gasto;
                $totalGastoOp += $sucGastos;

                // 3. Salidas: Colocación Empeños (Inversión en Cartera)
                $empenosQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(prestamo), 0) as inversion_empenos
                    FROM contratos
                    WHERE f_cancelacion IS NULL AND f_contrato BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);

                $sucEmpenos = (float) $empenosQ->inversion_empenos;
                $totalGastoEmpeno += $sucEmpenos;

                $sucSalidas = $sucGastos + $sucEmpenos;
                $totalSalidas += $sucSalidas;

                $chartEvolucionSaldos['labels'][] = $sucursal->nombre;
                $chartEvolucionSaldos['flujo_efectivo'][] = $sucEf - $sucSalidas; // Flujo libre fisico
                $chartEvolucionSaldos['flujo_bancos'][] = $sucTar; // Se asume que no pagan gastos con TPV

                $detalleCuentas[] = [
                    'banco' => 'Bancos / TPV',
                    'cuenta' => $sucursal->nombre,
                    'entradas' => $sucTar,
                    'salidas' => 0,
                    'flujo' => $sucTar
                ];

            } catch (\Exception $e) {
                Log::error("Error Bancos en {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        $flujoNetoMensual = $totalEntradas - $totalSalidas;

        $chartFlujosMensuales['data'] = [$totalEntradas, $totalSalidas, $flujoNetoMensual];

        $chartEntradasPorOrigen = [
            'labels' => ['Ingreso Efectivo Cajas', 'Ingreso Tarjetas TPV'],
            'data' => [$totalIngresoEf, $totalIngresoTar]
        ];
        
        $chartSalidasPorTipo = [
            'labels' => ['Inversión en Empeños Nuevos', 'Gastos Operativos'],
            'data' => [$totalGastoEmpeno, $totalGastoOp]
        ];

        return response()->json([
            'saldoTotalBancos' => $saldoTotalBancos,
            'saldoEfectivo' => $saldoEfectivo, // Agregado para el front
            'flujoNetoMensual' => $flujoNetoMensual,
            'totalEntradas' => $totalEntradas,
            'totalSalidas' => $totalSalidas,
            'saldoMinimo' => 0,
            'saldoMaximo' => 0,
            'saldoPromedio' => 0,
            'comisionesTotales' => 0,
            
            'chartEvolucionSaldos' => $chartEvolucionSaldos,
            'chartFlujosMensuales' => $chartFlujosMensuales,
            'chartEntradasPorOrigen' => $chartEntradasPorOrigen,
            'chartSalidasPorTipo' => $chartSalidasPorTipo,
            
            'detalleCuentas' => $detalleCuentas,
            'detalleMovimientos' => $detalleMovimientos
        ]);
    }
}
