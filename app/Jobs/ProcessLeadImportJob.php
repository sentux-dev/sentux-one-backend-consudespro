<?php

namespace App\Jobs;

use App\Imports\LeadsImport;
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

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, array $mappings, int $userId)
    {
        $this->filePath = $filePath;
        $this->mappings = $mappings;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Importar el archivo usando nuestra clase LeadsImport
            $import = new LeadsImport($this->mappings);
            Excel::import($import, $this->filePath);

            // (Opcional) Enviar una notificación al usuario cuando termine.
            // Por ahora, registramos en el log.
            Log::info("Importación de leads completada para el usuario {$this->userId}. Se crearon " . $import->getLeadsCreatedCount() . " leads.");

        } finally {
            // Limpiar el archivo temporal después de procesarlo
            Storage::delete($this->filePath);
        }
    }

    /**
     * Manejar un fallo del job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Falló la importación de leads para el usuario {$this->userId}: " . $exception->getMessage());
        // Limpiar el archivo temporal incluso si falla
        Storage::delete($this->filePath);
    }
}