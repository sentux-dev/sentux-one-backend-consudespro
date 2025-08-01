<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Crm\Deal;

class CrmDealsSeeder extends Seeder
{
    public function run(): void
    {
        $deals = [
            ['name' => 'Negociación inicial', 'amount' => 15000],
            ['name' => 'Oferta especial de preventa', 'amount' => 25000],
            ['name' => 'Cierre avanzado', 'amount' => 30000],
        ];

        foreach ($deals as $deal) {
            Deal::create($deal);
        }
    }
}