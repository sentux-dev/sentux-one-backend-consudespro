<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Crm\Contact;
use App\Models\Crm\ContactStatus;
use App\Models\Crm\DisqualificationReason;
use App\Models\Crm\Deal;
use App\Models\Crm\Campaign;
use App\Models\Crm\Origin;
use App\Models\RealState\Project;
use App\Models\User;
use Faker\Factory as Faker;

class CrmContactsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $statuses = ContactStatus::pluck('id')->toArray();
        $reasons = DisqualificationReason::pluck('id')->toArray();
        $users = User::pluck('id')->toArray();
        $deals = Deal::pluck('id')->toArray();
        $campaigns = Campaign::pluck('id')->toArray();
        $origins = Origin::pluck('id')->toArray();
        $projects = Project::pluck('id')->toArray();

        for ($i = 1; $i <= 50; $i++) {
            $contact = Contact::create([
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'cellphone' => $faker->phoneNumber,
                'phone' => $faker->phoneNumber,
                'email' => $faker->unique()->safeEmail,
                'contact_status_id' => $faker->randomElement($statuses),
                'disqualification_reason_id' => $faker->optional()->randomElement($reasons),
                'owner_id' => $faker->optional()->randomElement($users),
                'occupation' => $faker->jobTitle,
                'birthdate' => $faker->optional()->date(),
                'address' => $faker->address,
                'country' => $faker->country,
                'active' => $faker->boolean(90),
            ]);

            // Deals
            $contact->deals()->attach($faker->randomElements($deals, rand(0, 2)));

            // Campaigns
            if (!empty($campaigns)) {
                $originalCampaign = $faker->randomElement($campaigns);
                $contact->campaigns()->attach($originalCampaign, ['is_original' => true, 'is_last' => true]);

                if ($faker->boolean(30)) {
                    $lastCampaign = $faker->randomElement($campaigns);
                    $contact->campaigns()->attach($lastCampaign, ['is_original' => false, 'is_last' => true]);
                }
            }

            // Origins
            if (!empty($origins)) {
                $originalOrigin = $faker->randomElement($origins);
                $contact->origins()->attach($originalOrigin, ['is_original' => true, 'is_last' => true]);

                if ($faker->boolean(30)) {
                    $lastOrigin = $faker->randomElement($origins);
                    $contact->origins()->attach($lastOrigin, ['is_original' => false, 'is_last' => true]);
                }
            }

            // Projects (Real State)
            $contact->projects()->attach($faker->randomElements($projects, rand(0, 2)));
        }
    }
}