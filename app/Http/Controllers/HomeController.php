<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class HomeController extends Controller
{
    public function index()
    {
        // List of database suffixes provided by the user (1-22, skipping 12)
        $suffixes = [
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
            11, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22
        ];

        $totalEmpeno = 0;

        // Default to current month if not specified
        // The user's query uses @fechaDel and @fechaAl.
        // We assume "This Month" unless specified otherwise.
        $fechaDel = Carbon::now()->startOfMonth()->toDateString();
        $fechaAl = Carbon::now()->endOfMonth()->toDateString();

        // Base connection configuration (using the default sqlsrv connection as a template)
        // Ensure 'sqlsrv' is defined in config/database.php
        $baseConfig = Config::get('database.connections.sqlsrv');

        if (!$baseConfig) {
            // Fallback or error if sqlsrv is not configured
            Log::error("SQL Server connection 'sqlsrv' is not configured.");
            return view('employees.home', ['totalEmpeno' => 0, 'error' => 'Database configuration missing']);
        }

        foreach ($suffixes as $suffix) {
            $dbName = 'sistema_prendario_' . $suffix;
            $connectionName = 'dynamic_sqlsrv';

            try {
                // Clone the base config and update the database name
                $config = $baseConfig;
                $config['database'] = $dbName;

                // Set the dynamic connection configuration
                Config::set("database.connections.{$connectionName}", $config);

                // Purge the connection to ensure fresh connection with new config
                DB::purge($connectionName);

                // Run the query
                // We use the simplified logic: Sum prestamo for contracts with movements 1,2,3,4 in the date range
                // and active contracts (f_cancelacion IS NULL)
                // Note: The user's query uses CAST(mo.f_alta AS DATE), which is T-SQL specific.

                // We fetch the sum directly.
                // The query filters:
                // - Active contracts (f_cancelacion IS NULL)
                // - Movements of type 1, 2, 3, 4
                // - Date range on movement creation date
                // - Pledge types 1, 2, 3 (Alhajas, Autos, Varios)
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
