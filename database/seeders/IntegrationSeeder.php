<?php

namespace Database\Seeders;

use App\Models\Integration;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IntegrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usamos firstOrCreate para evitar duplicados si el seeder se ejecuta varias veces.
        Integration::firstOrCreate(
            [
                'provider' => 'mandrill' // El identificador único
            ],
            [
                'name' => 'Mandrill (Marketing)',
                'credentials' => [
                    'secret' => '',
                    'webhook_key' => '',
                ],
                'is_active' => false // Inicia inactivo por seguridad
            ]
        );
    }
}
