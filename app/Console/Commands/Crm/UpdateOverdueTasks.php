<?php

namespace App\Console\Commands\Crm;

use Illuminate\Console\Command;
use App\Models\Crm\Task;
use Illuminate\Support\Carbon;

class UpdateOverdueTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:update-overdue-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marca como "vencidas" las tareas pendientes cuya fecha programada ya pasó.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Buscando tareas pendientes y vencidas...');

        // 1. Buscamos todas las tareas que:
        //    - Tienen el estado 'pendiente'.
        //    - Su fecha de realización (schedule_date) es anterior a la fecha y hora actual.
        $overdueTasksCount = Task::where('status', 'pendiente')
                                 ->where('schedule_date', '<', now())
                                 ->update(['status' => 'vencida']);

        if ($overdueTasksCount > 0) {
            $this->info("Se han actualizado {$overdueTasksCount} tareas a estado 'Vencida'.");
        } else {
            $this->info('No se encontraron tareas para actualizar.');
        }

        return 0;
    }
}