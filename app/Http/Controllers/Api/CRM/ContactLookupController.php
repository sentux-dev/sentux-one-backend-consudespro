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
            ->orderBy('name')
            ->get(['id as value', 'name as label']);

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
            ->orderBy('name')
            ->get(['id as value', 'name as label']);

        return response()->json(['data' => $reasons]);
    }
}