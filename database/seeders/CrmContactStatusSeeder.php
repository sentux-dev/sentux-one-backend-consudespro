<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Crm\ContactStatus;

class CrmContactStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Nuevo', 'order' => 1],
            ['name' => 'Lead', 'order' => 2],
            ['name' => 'En seguimiento', 'order' => 3],
            ['name' => 'Cita agendada', 'order' => 4],
            ['name' => 'Visita realizada', 'order' => 5],
            ['name' => 'Reserva', 'order' => 6],
            ['name' => 'Cerrado', 'order' => 7],
        ];

        foreach ($statuses as $status) {
            ContactStatus::create($status);
        }
    }
}