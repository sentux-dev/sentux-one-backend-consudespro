<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MainUserSeeder extends Seeder
{
    public function run(): void
    {
        // Verificamos si el usuario con ID 1 ya existe
        $user = User::find(1);

        if (!$user) {
            DB::table('users')->insert([
                'id' => 1,
                'first_name' => 'Edwin',
                'last_name' => 'Villalobos',
                'name' => 'Edwin Villalobos',
                'email' => 'edwin@sentux.com',
                'phone' => '87041136',
                'language' => 'en',
                'date_format' => 'dd/MM/yyyy',
                'time_format' => 'hh:mm a',
                'number_format' => 'american',
                'timezone' => 'America/Costa_Rica',
                'last_login_at' => now(),
                'password' => '$2y$12$KSC4G86I9pSGgN9rFgsD7O5QE9H.RP/if0llXySK5jdcggPzj3hUe', // Tu hash ya generado
                'active' => 1,
                'created_at' => '2025-06-19 23:12:17',
                'updated_at' => now(),
                'mfa_enabled' => 1,
                'mfa_type' => 'app',
                'mfa_secret' => 'KCWUKO3SB2GSZJWE',
            ]);
        }
    }
}