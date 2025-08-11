<?php

namespace App\Models\Marketing;

use App\Models\Crm\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Segment extends Model
{
    use HasFactory;
    protected $table = 'marketing_segments';
    protected $fillable = ['name', 'description', 'filters'];
    protected $casts = ['filters' => 'array'];

    /**
     * Construye y devuelve una consulta de Eloquent para obtener los contactos
     * que coinciden con los filtros guardados en este segmento.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getContactsQuery(): Builder
    {
        $query = Contact::query();
        $filters = $this->filters ?? [];

        // Aplicar cada filtro guardado a la consulta
        foreach ($filters as $key => $value) {
            if (empty($value)) {
                continue; // Omitir filtros vacíos
            }

            // --- Lógica para Campos Nativos ---
            switch ($key) {
                case 'status_id':
                    $query->where('contact_status_id', $value);
                    break;
                case 'owner_id':
                    $query->where('owner_id', $value);
                    break;
                case 'campaign_id':
                    $query->whereHas('campaigns', fn($q) => $q->where('crm_campaigns.id', $value));
                    break;
                case 'origin_id':
                    $query->whereHas('origins', fn($q) => $q->where('crm_origins.id', $value));
                    break;
                case 'search':
                    $query->where(function ($q) use ($value) {
                        $q->where('first_name', 'like', "%{$value}%")
                          ->orWhere('last_name', 'like', "%{$value}%")
                          ->orWhere('email', 'like', "%{$value}%");
                    });
                    break;
            }

            // --- Lógica para Campos Personalizados ---
            // Buscamos filtros que sigan el patrón "cf_{nombre_del_campo}"
            if (str_starts_with($key, 'cf_')) {
                $customFieldName = substr($key, 3); // Elimina el prefijo "cf_"
                
                $query->whereHas('customFieldValues', function ($q) use ($customFieldName, $value) {
                    $q->where('value', $value)
                      ->whereHas('field', fn($qf) => $qf->where('name', $customFieldName));
                });
            }
        }

        return $query;
    }
}