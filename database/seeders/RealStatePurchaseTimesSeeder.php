<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RealStatePurchaseTimesSeeder extends Seeder
{
    public function run(): void
    {
        $times = [
            ['label' => '1 mes', 'order' => 1, 'active' => true],
            ['label' => '3 meses', 'order' => 2, 'active' => true],
            ['label' => '6 meses', 'order' => 3, 'active' => true],
            ['label' => 'Más de 6 meses', 'order' => 4, 'active' => true],
        ];

        DB::table('real_state_purchase_times')->insert($times);
    }
}