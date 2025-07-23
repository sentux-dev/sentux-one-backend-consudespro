<?php

namespace App\Http\Resources\CRM;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'cellphone' => $this->cellphone,
            'phone' => $this->phone,
            'active' => $this->active,

            // ✅ Estado del lead
            'status' => $this->status ? [
                'id' => $this->status->id,
                'name' => $this->status->name
            ] : null,

            // ✅ Propietario
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email ?? null
            ] : null,

            // ✅ Razón de descalificación
            'disqualification_reason' => $this->disqualificationReason ? [
                'id' => $this->disqualificationReason->id,
                'name' => $this->disqualificationReason->name
            ] : null,

            // ✅ Arrays de nombres para que el frontend no rompa
            'deals_names' => $this->deals->pluck('name')->toArray(),
            'campaigns_names' => $this->campaigns->pluck('name')->toArray(),
            'origins_names' => $this->origins->pluck('name')->toArray(),
            'projects_names' => $this->projects->pluck('name')->toArray(),

            // ✅ Contadores
            'deals_count' => $this->whenCounted('deals'),
            'campaigns_count' => $this->whenCounted('campaigns'),
            'origins_count' => $this->whenCounted('origins'),
            'projects_count' => $this->whenCounted('projects'),

            // ✅ Fechas
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
        ];
    }
}