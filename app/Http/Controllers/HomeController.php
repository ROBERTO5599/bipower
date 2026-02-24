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
        $sucursalesDetalle = []; // To store name and total per branch

        // Default to current month with precise time boundaries
        $fechaDel = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
        $fechaAl = Carbon::now()->endOfMonth()->format('Y-m-d H:i:s');

        // Base connection configuration (using the default mysql connection as a template)
        $baseConfig = Config::get('database.connections.mysql');

        if (!$baseConfig) {
            Log::error("MySQL connection 'mysql' is not configured.");
            return view('employees.home', [
                'totalEmpeno' => 0,
                'error' => 'Database configuration missing',
                'sucursalesDetalle' => [],
                'fechaDel' => $fechaDel,
                'fechaAl' => $fechaAl
            ]);
        }

        foreach ($sucursales as $sucursal) {
            $suffix = $sucursal->id_valora_mas;
            $dbName = 'sistema_prendario_' . $suffix;
            $connectionName = 'dynamic_mysql';

            $sucursalTotal = 0;

            try {
                // Clone the base config and update the database name
                $config = $baseConfig;
                $config['database'] = $dbName;

                // Set the dynamic connection configuration
                Config::set("database.connections.{$connectionName}", $config);

                // Purge the connection to ensure fresh connection with new config
                DB::purge($connectionName);

                // Updated Query:
                // - Only movement type 1 (Empeño)
                // - Filter on mo.f_alta using precise DATETIME range using CAST AS DATE as requested
                $query = "
                    SELECT SUM(con.prestamo) as total
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE con.f_cancelacion IS NULL
                    AND mo.cod_tipo_movimiento IN (1)
                    AND CAST(mo.f_alta AS DATE) BETWEEN ? AND ?
                    AND con.cod_tipo_prenda IN (1, 2, 3)
                ";

                $result = DB::connection($connectionName)->selectOne($query, [$fechaDel, $fechaAl]);

                if ($result && isset($result->total)) {
                    $sucursalTotal = $result->total;
                    $totalEmpeno += $sucursalTotal;
                }

            } catch (Exception $e) {
                // Log the error
                Log::error("Error connecting to or querying {$dbName}: " . $e->getMessage());
                // Set total to 0 for this branch on error
                $sucursalTotal = 0;
            }

            // Add to detail list
            $sucursalesDetalle[] = [
                'nombre' => $sucursal->nombre,
                'total' => $sucursalTotal
            ];
        }

        // Sort details by total descending
        usort($sucursalesDetalle, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return view('employees.home', compact('totalEmpeno', 'sucursalesDetalle', 'fechaDel', 'fechaAl'));
    }
}
