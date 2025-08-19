<?php

namespace App\Services\Crm;

use App\Models\Integration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseConnectorService
{
    public function connect(Integration $integration): \Illuminate\Database\Connection
    {
        // Ahora el provider es más genérico: 'db_import'
        if ($integration->provider !== 'db_import') {
            throw new \Exception('La integración proporcionada no es del tipo Base de Datos.');
        }

        $credentials = $integration->credentials;
        $driver = $credentials['driver'] ?? 'mysql'; // Default a mysql si no está definido
        $connectionName = 'external_db_' . $integration->id;

        // Construimos la configuración de la conexión dinámicamente
        Config::set("database.connections.{$connectionName}", [
            'driver' => $driver,
            'host' => $credentials['host'],
            'port' => $credentials['port'],
            'database' => $credentials['database'],
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        DB::purge($connectionName);
        return DB::connection($connectionName);
    }
}