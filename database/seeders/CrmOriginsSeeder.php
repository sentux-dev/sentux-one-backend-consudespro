<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Crm\Origin;

class CrmOriginsSeeder extends Seeder
{
    public function run(): void
    {
        $origins = [
            ['name' => 'Website', 'order' => 1],
            ['name' => 'Landing Page', 'order' => 2],
            ['name' => 'Facebook Ads', 'order' => 3],
            ['name' => 'Google Ads', 'order' => 4],
            ['name' => 'Referido', 'order' => 5],
        ];

        foreach ($origins as $origin) {
            Origin::create($origin);
        }
    }
}