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
        // 🔹 --- LÓGICA DE VALIDACIÓN CORREGIDA --- 🔹

        // 1. Obtener los valores de email y teléfono según el mapeo del usuario.
        $emailColumn = $this->mapping['email'] ?? null;
        $phoneColumn = $this->mapping['phone'] ?? null;
        
        $email = $emailColumn ? ($row[strtolower(str_replace(' ', '_', $emailColumn))] ?? null) : null;
        $phone = $phoneColumn ? ($row[strtolower(str_replace(' ', '_', $phoneColumn))] ?? null) : null;

        // 2. Verificar si al menos uno de los dos campos es válido.
        $hasValidEmail = !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        $hasValidPhone = !empty(trim((string)$phone)); // Un simple check para ver si el teléfono no está vacío.

        // 3. Si no hay ni un email válido NI un teléfono, se ignora la fila.
        if (!$hasValidEmail && !$hasValidPhone) {
            return null;
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
        $customFieldsPayload = [];
        $standardFields = ['email', 'first_name', 'last_name', 'phone'];

        foreach ($this->mapping as $systemField => $userColumn) {
            $cleanedColumn = strtolower(str_replace(' ', '_', $userColumn));
            if (isset($row[$cleanedColumn])) {
                $value = $row[$cleanedColumn];
                
                if (in_array($systemField, $standardFields)) {
                    // Es un campo estándar
                    $payload[$systemField] = $value;
                } else {
                    // Es un campo personalizado (ej: 'custom_field_1')
                    $customFieldsPayload[$systemField] = $value;
                }
            }
        }
        
        // Guardamos los campos personalizados en una clave especial dentro del payload
        $payload['_custom_fields'] = $customFieldsPayload;
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