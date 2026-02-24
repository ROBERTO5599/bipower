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
        // Get database suffixes from Sucursal table where id_valora_mas is not null
        $suffixes = Sucursal::whereNotNull('id_valora_mas')
            ->pluck('id_valora_mas')
            ->toArray();

        $totalEmpeno = 0;

        // Default to current month if not specified
        $fechaDel = Carbon::now()->startOfMonth()->toDateString();
        $fechaAl = Carbon::now()->endOfMonth()->toDateString();

        // Base connection configuration (using the default mysql connection as a template)
        $baseConfig = Config::get('database.connections.mysql');

        if (!$baseConfig) {
            Log::error("MySQL connection 'mysql' is not configured.");
            return view('employees.home', ['totalEmpeno' => 0, 'error' => 'Database configuration missing']);
        }

        foreach ($suffixes as $suffix) {
            $dbName = 'sistema_prendario_' . $suffix;
            $connectionName = 'dynamic_mysql'; // Changed from dynamic_sqlsrv

            try {
                // Clone the base config and update the database name
                // We assume the secondary databases are on the same MySQL host with same credentials
                $config = $baseConfig;
                $config['database'] = $dbName;

                // Set the dynamic connection configuration
                Config::set("database.connections.{$connectionName}", $config);

                // Purge the connection to ensure fresh connection with new config
                DB::purge($connectionName);

                // Run the query
                // MySQL syntax check: CAST(x AS DATE) is valid in MySQL as well.

                $query = "
                    SELECT SUM(con.prestamo) as total
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
                    INNER JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
                    WHERE con.f_cancelacion IS NULL
                    AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)
                    AND CAST(mo.f_alta AS DATE) BETWEEN ? AND ?
                    AND con.cod_tipo_prenda IN (1, 2, 3)
                ";

                // We use selectOne to get a single row result
                $result = DB::connection($connectionName)->selectOne($query, [$fechaDel, $fechaAl]);

                if ($result && isset($result->total)) {
                    $totalEmpeno += $result->total;
                }

            } catch (Exception $e) {
                // Log the error
                Log::error("Error connecting to or querying {$dbName}: " . $e->getMessage());
                // Continue to next database
            }
        }

        return view('employees.home', compact('totalEmpeno'));
    }
}
