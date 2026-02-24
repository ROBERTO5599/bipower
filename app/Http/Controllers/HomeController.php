<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use App\Models\Sucursal;

class HomeController extends Controller
{
    public function index()
    {
        // Get active branches (sucursales) with valid DB suffix
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

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

        return view('employees.home', compact(
            'totalEmpeno', 'totalRefrendo', 'totalDesempeno',
            'sucursalesDetalleEmpeno', 'sucursalesDetalleRefrendo', 'sucursalesDetalleDesempeno',
            'fechaDel', 'fechaAl'
        ));
    }
}
