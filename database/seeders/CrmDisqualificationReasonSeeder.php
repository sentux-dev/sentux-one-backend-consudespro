<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Crm\DisqualificationReason;

class CrmDisqualificationReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['name' => 'Formulario llenado por error', 'order' => 1],
            ['name' => 'No tiene presupuesto', 'order' => 2],
            ['name' => 'No está interesado actualmente', 'order' => 3],
            ['name' => 'Datos incorrectos', 'order' => 4],
        ];

        foreach ($reasons as $reason) {
            DisqualificationReason::create($reason);
        }
    }
}