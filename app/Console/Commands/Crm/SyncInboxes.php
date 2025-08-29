<?php

namespace App\Console\Commands\Crm;

use Illuminate\Console\Command;
use App\Models\UserEmailAccount;
use App\Services\MailSyncService;

class SyncInboxes extends Command
{
    protected $signature = 'crm:sync-inboxes';
    protected $description = 'Sincroniza las bandejas de entrada de las cuentas de correo conectadas.';

    public function handle(MailSyncService $mailSyncService)
    {
        $this->info('Iniciando la sincronización de bandejas de entrada...');

        // Obtenemos todas las cuentas que están activas para sincronización
        $accountsToSync = UserEmailAccount::where('is_active', true)->get();

        if ($accountsToSync->isEmpty()) {
            $this->info('No hay cuentas de correo activas para sincronizar.');
            return 0;
        }

        $this->output->progressStart($accountsToSync->count());

        foreach ($accountsToSync as $account) {
            $this->line(" Sincronizando: {$account->email_address}...");
            
            $result = $mailSyncService->syncAccount($account);

            if ($result['status'] === 'success') {
                $this->info(" -> Éxito. {$result['synced']} nuevos mensajes procesados.");
            } else {
                $this->error(" -> Error: {$result['message']}");
            }
            
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->info('Sincronización de bandejas de entrada finalizada.');
        return 0;
    }
}