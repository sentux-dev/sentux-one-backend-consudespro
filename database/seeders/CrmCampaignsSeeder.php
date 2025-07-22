<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Crm\Campaign;

class CrmCampaignsSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            ['name' => 'Mundial de Clubes 2025', 'order' => 1],
            ['name' => 'Black Friday 2025', 'order' => 2],
            ['name' => 'Lanzamiento Proyecto Vista Real', 'order' => 3],
        ];

        foreach ($campaigns as $campaign) {
            Campaign::create($campaign);
        }
    }
}