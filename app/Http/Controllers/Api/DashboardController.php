<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Importar los modelos necesarios
use App\Models\CRM\Task;
use App\Models\Crm\Deal;
use App\Models\Crm\Contact;
use App\Models\Crm\ExternalLead;
use App\Models\Crm\Pipeline;
use App\Models\RealState\Project;
use App\Models\RealState\Lot;
use App\Models\Marketing\Campaign;

class DashboardController extends Controller
{
    /**
     * Recopila y devuelve todos los datos para el dashboard general.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        return response()->json([
            'today_section' => $this->getTodaySectionData($user),
            'crm_pulse_section' => $this->getCrmPulseData(),
            'real_estate_section' => $this->getRealEstateData(),
            'marketing_section' => $this->getMarketingData(),
        ]);
    }

    /**
     * Sección 1: Para ti Hoy
     */
    private function getTodaySectionData($user)
    {
        return [
            // Corregido: Usa 'owner_id' del modelo Task
            'my_tasks_due_today' => Task::where('owner_id', $user->id)
                ->whereDate('schedule_date', '=', Carbon::today())
                ->where('status', '!=', 'completada')
                ->with('contact:id,first_name,last_name')
                ->get(),

            // Validado: usa 'contract_due_date' y la relación 'project' del modelo Lot
            'contracts_expiring_soon' => Lot::where('contract_due_date', '>=', Carbon::today())
                ->where('contract_due_date', '<=', Carbon::today()->addDays(7))
                ->with('project:id,slug,name')
                ->select('id', 'slug', 'lot_number', 'contract_due_date', 'project_id')
                ->get(),
        ];
    }

    /**
     * Sección 2: Pulso del CRM y Ventas
     */
    private function getCrmPulseData()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();

        // 🔹 --- LÓGICA DE EMBUDO CORREGIDA --- 🔹
        $pipelines = Pipeline::with(['stages' => function ($query) {
            // 1. Para cada pipeline, carga sus etapas ordenadas
            $query->orderBy('order');
        }, 'deals' => function ($query) {
            // 2. Y también carga los deals asociados a ese pipeline
            $query->select('id', 'pipeline_id', 'stage_id');
        }])->get();

        // 3. Procesamos los datos para construir los embudos
        $dealsFunnels = $pipelines->map(function ($pipeline) {
            $dealsByStage = $pipeline->deals->groupBy('stage_id');
            
            return [
                'pipeline_id' => $pipeline->id,
                'pipeline_name' => $pipeline->name,
                'stages' => $pipeline->stages->map(function ($stage) use ($dealsByStage) {
                    return [
                        'stage_id' => $stage->id,
                        'stage_name' => $stage->name,
                        'total' => $dealsByStage->get($stage->id, collect())->count(),
                    ];
                }),
            ];
        });

        return [
            'deals_funnels' => $dealsFunnels, // Renombrado a plural para mayor claridad
            'new_contacts_this_month' => Contact::where('created_at', '>=', $startOfMonth)->count(),
            'new_contacts_last_month' => Contact::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count(),
        ];
    }

    /**
     * Sección 3: Estado de Proyectos Inmobiliarios
     */
    private function getRealEstateData()
    {
        return [
            // Validado: Usa la relación 'lots' y el campo 'status' del modelo Project
            'lot_availability' => Project::withCount(['lots as available_lots' => function ($query) {
                $query->where('status', 'Disponible');
            }, 'lots as reserved_lots' => function ($query) {
                $query->where('status', 'Reservado');
            }, 'lots as sold_lots' => function ($query) {
                $query->where('status', 'Vendido');
            }])
            ->whereIn('status', ['En Venta', 'En Desarrollo'])
            ->get(),

            // Validado: usa 'status' y 'base_price' del modelo Lot
            'reserved_lots_value' => Lot::where('status', 'Reservado')->sum('base_price'),
            
            // Validado: usa el campo 'status' del modelo Project
            'active_projects_count' => Project::whereIn('status', ['En Venta', 'En Desarrollo'])->count(),
        ];
    }

    /**
     * Sección 4: Rendimiento de Marketing y Leads
     */
    private function getMarketingData()
    {
        return [
            // Validado: usa 'received_at' y 'source' del modelo ExternalLead
            'leads_today_by_source' => ExternalLead::select('source', DB::raw('count(*) as total'))
                ->where('received_at', '>=', Carbon::today())
                ->groupBy('source')
                ->get(),

            // Validado: usa el campo 'status' del modelo ExternalLead
            'inbox_status_counts' => ExternalLead::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status'),

            // Validado: usa 'status' y 'sent_at' del modelo Campaign
            'last_campaign_stats' => Campaign::where('status', 'enviada')
                ->withCount(['emailLogs as opens' => function ($query) {
                    $query->whereIn('status', ['abierto', 'clic']);
                }, 'emailLogs as clicks' => function ($query) {
                    $query->where('status', 'clic');
                }])
                ->latest('sent_at')
                ->first(),
        ];
    }
}