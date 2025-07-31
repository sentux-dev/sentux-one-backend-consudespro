<?php

namespace App\Imports;

use App\Models\Crm\ExternalLead;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class LeadsImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    protected array $mapping;
    private int $leadsCreated = 0;

    // El constructor ahora acepta las reglas de mapeo
    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function model(array $row)
    {
        // Obtiene el valor del email usando la columna que el usuario mapeó
        $emailColumn = $this->mapping['email'] ?? null;
        $email = $emailColumn ? ($row[strtolower($emailColumn)] ?? null) : null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null; // Ignorar fila si no hay un email válido
        }
        
        $this->leadsCreated++;

        return new ExternalLead([
            'source'      => 'csv_import',
            'payload'     => $this->mapRow($row), // Usamos un método para construir un payload limpio
            'status'      => 'pendiente',
            'received_at' => now(),
        ]);
    }

    // Construye un payload estandarizado usando el mapeo del usuario
    protected function mapRow(array $row): array
    {
        $payload = [];
        foreach ($this->mapping as $systemField => $userColumn) {
            if (isset($row[strtolower($userColumn)])) {
                $payload[$systemField] = $row[strtolower($userColumn)];
            }
        }
        // También guardamos la fila original por si acaso
        $payload['_original_row'] = $row;
        return $payload;
    }
    
    public function getLeadsCreatedCount(): int
    {
        return $this->leadsCreated;
    }

    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 200; }
}