<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\RealState\Project;
use App\Models\Crm\Campaign;
use App\Models\Crm\ContactStatus;
use App\Models\Crm\DisqualificationReason;
use App\Models\Crm\Origin;
use App\Models\User;

class ContactLookupController extends Controller
{
    /**
     * Obtener lista de proyectos activos (Real State).
     */
    public function projects()
    {
        $projects = Project::where('active', true)
            ->orderBy('order')
            ->get(['id as value', 'name as label']);

        return response()->json($projects);
    }

    /**
     * Obtener lista de campañas activas.
     */
    public function campaigns()
    {
        $campaigns = Campaign::where('active', true)
            ->orderBy('order')
            ->get(['id as value', 'name as label']);

        return response()->json($campaigns);
    }

    /**
     * Obtener lista de orígenes activos.
     */
    public function origins()
    {
        $origins = Origin::where('active', true)
            ->orderBy('order')
            ->get(['id as value', 'name as label']);

        return response()->json($origins);
    }

    /**
     * Obtener lista de usuarios activos.
     */
    public function owners()
    {
        $owners = User::where('active', true)
            ->orderBy('name')
            ->get(['id as value', 'name as label']);

        return response()->json($owners);
    }

    /**
     * Obtener lista de estados de contacto activos.
     */
    public function status()
    {
        $status = ContactStatus::where('active', true)
            ->orderBy('name')
            ->get(['id as value', 'name as label']);

        return response()->json($status);
    }

    /**
     * Obtener lista de razones de descalificación activas.
     */
    public function disqualificationReasons()
    {
        $reasons = DisqualificationReason::where('active', true)
            ->orderBy('name')
            ->get(['id as value', 'name as label']);
        return response()->json($reasons);
    }
}