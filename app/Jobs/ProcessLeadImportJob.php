<?php

namespace App\Jobs;

use App\Imports\LeadsImport;
use App\Models\Crm\LeadImport;
use App\Models\User;
use App\Notifications\LeadImportCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;


class ProcessLeadImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de veces que el job puede ser reintentado.
     * @var int
     */
    public $tries = 3;

    /**
     * El número de segundos que el job puede ejecutar antes de expirar.
     * @var int
     */
    public $timeout = 1200; // 20 minutos

    protected string $filePath;
    protected array $mappings;
    protected int $userId;
    protected int $leadImportId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, array $mappings, int $userId, int $leadImportId)
    {
        $this->filePath = $filePath;
        $this->mappings = $mappings;
        $this->userId = $userId;
        $this->leadImportId = $leadImportId;

    }

    /**
     * Execute the job.
     */
     public function handle(): void
    {
        $leadImport = LeadImport::find($this->leadImportId);
        if (!$leadImport) {
            Log::error("No se encontró el lote de importación con ID {$this->leadImportId}.");
            return;
        }

        try {
            // 🔹 El job ahora es más simple. Solo crea la instancia y ejecuta la importación.
            $import = new LeadsImport($this->mappings, $leadImport);
            Excel::import($import, $this->filePath);

            // Actualiza el estado final y el conteo de importados
            $leadImport->update([
                'status' => 'completed',
                'imported_count' => $import->getLeadsCreatedCount(),
            ]);
            
            Log::info("Importación de leads completada. Lote ID: {$this->leadImportId}.");

            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new LeadImportCompletedNotification($leadImport));
            }

        } catch (\Throwable $e) {
            $leadImport->update(['status' => 'failed']);
            $this->failed($e); // Llama al método failed
        } finally {
            Storage::delete($this->filePath);
        }
    }

    /**
     * Manejar un fallo del job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Falló la importación de leads para el usuario {$this->userId}: " . $exception->getMessage());

        $leadImport = LeadImport::find($this->leadImportId);
        $user = User::find($this->userId);
        if ($user && $leadImport) {
            $user->notify(new LeadImportCompletedNotification($leadImport, $exception));
        }

        // Limpiar el archivo temporal incluso si falla
        Storage::delete($this->filePath);
    }
}