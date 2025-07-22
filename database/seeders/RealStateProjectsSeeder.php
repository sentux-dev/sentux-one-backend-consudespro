<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RealState\Project;

class RealStateProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            ['name' => 'Vista Real', 'order' => 1],
            ['name' => 'Torres del Sol', 'order' => 2],
            ['name' => 'Jardines del Este', 'order' => 3],
        ];

        foreach ($projects as $project) {
            Project::create($project);
        }
    }
}