<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\RealState\Project;
use App\Models\Crm\Campaign;
use App\Models\Crm\ContactCustomField;
use App\Models\Crm\ContactStatus;
use App\Models\Crm\DisqualificationReason;
use App\Models\Crm\Origin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContactLookupController extends Controller
{
    public function projects()
    {
        $projects = Project::where('active', true)
            ->orderBy('order')
            ->get(['id as value', 'name as label']);

        return response()->json(['data' => $projects]);
    }

    public function campaigns()
    {
        $campaigns = Campaign::where('active', true)
            ->orderBy('order')
            ->get(['id as value', 'name as label']);

        return response()->json(['data' => $campaigns]);
    }

    public function origins()
    {
        $origins = Origin::where('active', true)
            ->orderBy('order')
            ->get(['id as value', 'name as label']);

        return response()->json(['data' => $origins]);
    }

    public function owners()
    {
        $owners = User::where('active', true)
            ->orderBy('first_name')
            ->get(['id as value', DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', first_name, last_name), ''), email) as label")]);

        return response()->json(['data' => $owners]);
    }

    public function status()
    {
        $status = ContactStatus::where('active', true)
            ->orderBy('order')
            ->get(['id as value', 'name as label']);

        return response()->json(['data' => $status]);
    }

    public function disqualificationReasons()
    {
        $reasons = DisqualificationReason::where('active', true)
            ->orderBy('order')
            ->get(['id as value', 'name as label']);

        return response()->json(['data' => $reasons]);
    }

    public function contactFields()
    {
        // 1. Definimos los campos estándar que permitiremos modificar
        $standardFields = [
            ['value' => 'first_name', 'label' => 'Nombre'],
            ['value' => 'last_name', 'label' => 'Apellido'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'cellphone', 'label' => 'Celular'],
            ['value' => 'phone', 'label' => 'Teléfono'],
            ['value' => 'occupation', 'label' => 'Ocupación'],
            ['value' => 'job_position', 'label' => 'Cargo'],
            ['value' => 'current_company', 'label' => 'Empresa Actual'],
            ['value' => 'birthdate', 'label' => 'Fecha de Nacimiento'],
            ['value' => 'address', 'label' => 'Dirección'],
            ['value' => 'country', 'label' => 'País']
        ];

        // 2. Obtenemos los campos personalizados activos
        $customFields = ContactCustomField::where('active', true)
            ->get()
            ->map(function ($field) {
                return [
                    // Usamos un prefijo 'cf_' para diferenciar los campos personalizados en el backend
                    'value' => 'cf_' . $field->slug, 
                    'label' => $field->name . ' (Personalizado)'
                ];
            });

        // 3. Combinamos ambas listas
        $allFields = array_merge($standardFields, $customFields->toArray());

        // 4. Ordenamos alfabéticamente por la etiqueta para una mejor UX
        usort($allFields, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return response()->json($allFields);
    }
}