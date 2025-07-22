<?php

namespace App\Http\Resources\CRM;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'cellphone' => $this->cellphone,
            'phone' => $this->phone,
            'occupation' => $this->occupation,
            'job_position' => $this->job_position,
            'current_company' => $this->current_company,
            'birthdate' => $this->birthdate ? $this->birthdate->format('Y-m-d') : null,
            'address' => $this->address,
            'country' => $this->country,
            'active' => (bool) $this->active,

            // Relaciones
            'status' => $this->whenLoaded('status'),
            'disqualification_reason' => $this->whenLoaded('disqualificationReason'),
            'owner' => $this->whenLoaded('owner'),

             // ✅ Nombres para tags (sólo si vienen cargadas las relaciones)
            'deals_names' => $this->whenLoaded('deals', fn () => $this->deals->pluck('name')),
            'campaigns_names' => $this->whenLoaded('campaigns', fn () => $this->campaigns->pluck('name')),
            'origins_names' => $this->whenLoaded('origins', fn () => $this->origins->pluck('name')),
            'projects_names' => $this->whenLoaded('projects', fn () => $this->projects->pluck('name')),


            // Contadores
            'deals_count' => $this->deals_count ?? 0,
            'projects_count' => $this->projects_count ?? 0,
            'campaigns_count' => $this->campaigns_count ?? 0,
            'origins_count' => $this->origins_count ?? 0,

            // Fechas
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}