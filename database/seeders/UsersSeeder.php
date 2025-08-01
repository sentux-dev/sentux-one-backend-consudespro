<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // No tocamos el ID 1 (usuario Edwin)
        if (!User::where('id', 1)->exists()) {
            // Esto solo sería necesario si no existiera, pero lo maneja MainUserSeeder
            return;
        }

        // Creamos otros usuarios de prueba (del 2 en adelante)
        User::factory()->count(5)->create();
    }
}