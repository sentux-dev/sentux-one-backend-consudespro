<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // Cambiado de MorphMany

class Deal extends Model
{
    use HasFactory;

    protected $table = 'crm_deals';

    protected $fillable = [
        'name',
        'amount',
        'close_date',
        'pipeline_id',
        'stage_id',
        'owner_id',
    ];

    protected $casts = [
        'close_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected $appends = ['contact'];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'owner_id'); // Asumiendo que el dueño es un Contact
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(DealCustomFieldValue::class, 'deal_id');
    }

    // CORRECCIÓN: Relación de un Deal con sus asociaciones (HasMany)
    // Un Deal tiene muchas DealAssociations, donde 'deal_id' es la clave foránea en DealAssociation.
    public function dealAssociations(): HasMany
    {
        return $this->hasMany(DealAssociation::class, 'deal_id');
    }

    // Método para asociar un contacto (u otro tipo) a este negocio
    public function associate(Model $model, ?string $relationType = null): DealAssociation
    {
        // Al llamar a create() en una relación HasMany, Eloquent automáticamente
        // establece la clave foránea ('deal_id' en este caso) al ID del modelo padre ($this->id).
        return $this->dealAssociations()->create([ // Usar la nueva relación HasMany
            'associable_id' => $model->id,
            'associable_type' => $model::class, // Usar $model::class para el nombre de clase completo
            'relation_type' => $relationType,
        ]);
    }

    public function getContactAttribute()
    {
        // Revisa si la relación dealAssociations ya está cargada
        if (! $this->relationLoaded('dealAssociations')) {
            // Si no está cargada, no podemos obtener el contacto, devolvemos null
            // El controller se encargará de cargarla con with()
            return null;
        }

        // Busca la primera asociación que sea de tipo Contacto
        $contactAssociation = $this->dealAssociations
            ->where('associable_type', Contact::class)
            ->first();

        // Si la encuentra, devuelve el modelo asociado (el contacto en sí)
        return $contactAssociation ? $contactAssociation->associable : null;
    }
}