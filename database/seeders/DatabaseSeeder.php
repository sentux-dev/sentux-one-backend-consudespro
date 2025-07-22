<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
{
    $this->call([
        MainUserSeeder::class, // crea o mantiene tu usuario Edwin
        CrmContactStatusSeeder::class,
        CrmDisqualificationReasonSeeder::class,
        CrmOriginsSeeder::class,
        CrmCampaignsSeeder::class,
        RealStateProjectsSeeder::class,
        RealStatePurchaseTimesSeeder::class, 
        CrmDealsSeeder::class,
        UsersSeeder::class,
        CrmContactsSeeder::class,
    ]);
}
}
