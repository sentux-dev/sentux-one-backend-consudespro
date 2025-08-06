<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Crm\Contact;
use Illuminate\Support\Str;

class PopulateContactUuids extends Command
{
    protected $signature = 'app:populate-contact-uuids';
    protected $description = 'Genera UUIDs para los contactos existentes que no tengan uno.';

    public function handle()
    {
        $this->info('Buscando contactos sin UUID...');
        $contactsToUpdate = Contact::whereNull('uuid')->get();

        if ($contactsToUpdate->isEmpty()) {
            $this->info('¡Perfecto! Todos los contactos ya tienen un UUID.');
            return 0;
        }

        $progressBar = $this->output->createProgressBar($contactsToUpdate->count());
        $progressBar->start();

        foreach ($contactsToUpdate as $contact) {
            $contact->uuid = (string) Str::uuid();
            $contact->save();
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\nSe han actualizado {$contactsToUpdate->count()} contactos.");
        return 0;
    }
}