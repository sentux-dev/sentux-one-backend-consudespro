<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Crm\DatabaseConnectorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DatabaseImportController extends Controller
{
    protected $connectorService;

    public function __construct(DatabaseConnectorService $connectorService)
    {
        $this->connectorService = $connectorService;
    }

    /**
     * Obtiene la lista de tablas de la base de datos externa.
     */
    public function getTables(Integration $integration)
    {
        try {
            /** @var \Illuminate\Database\Connection $connection */
            $connection = $this->connectorService->connect($integration);
            $driver = $connection->getDriverName();
            $database = $connection->getDatabaseName();
            $query = '';

            switch ($driver) {
                case 'mysql':
                    $query = "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME";
                    $rows = $connection->select($query, [$database]);
                    $tables = collect($rows)->pluck('TABLE_NAME')->values();
                    break;

                case 'pgsql':
                    $query = "SELECT tablename 
                              FROM pg_catalog.pg_tables 
                              WHERE schemaname NOT IN ('pg_catalog','information_schema')
                              ORDER BY tablename";
                    $rows = $connection->select($query);
                    $tables = collect($rows)->pluck('tablename')->values();
                    break;
                case 'sqlite':
                    $query = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
                    $rows = $connection->select($query);
                    $tables = collect($rows)->pluck('name')->values();
                    break;
                case 'sqlsrv':
                    $query = "SELECT TABLE_NAME 
                        FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_TYPE = 'BASE TABLE' 
                        AND TABLE_CATALOG = ? 
                        ORDER BY TABLE_NAME";
                    $rows = $connection->select($query, [$database]);
                    $tables = collect($rows)->pluck('TABLE_NAME')->values();
                    break;

                default:
                    return response()->json([
                        'message' => "Driver no soportado: {$driver}"
                    ], 422);
            }

            return response()->json($tables);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo conectar a la base de datos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getColumns(Request $request, Integration $integration)
    {
        $validated = $request->validate(['table' => 'required|string']);
        $tableName = $validated['table'];

        try {
            $connection = $this->connectorService->connect($integration);
            
            if (!$connection->getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['message' => 'La tabla especificada no existe.'], 404);
            }
            $columns = $connection->getSchemaBuilder()->getColumnListing($tableName);
            return response()->json($columns);

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al obtener las columnas: ' . $e->getMessage()], 500);
        }
    }


}