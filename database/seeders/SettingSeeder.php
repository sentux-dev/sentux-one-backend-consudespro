<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Inserta la configuración de zona horaria solo si no existe
        DB::table('settings')->updateOrInsert(
            ['key' => 'app_timezone'],
            ['value' => 'America/Costa_Rica'] // Puedes cambiar este valor por defecto
        );
    }
}