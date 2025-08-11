<?php

namespace App\Console\Commands\Crm;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\TaskDigestEmail;

class SendTaskDigests extends Command
{
    protected $signature = 'crm:send-task-digests';
    protected $description = 'Envía un email de resumen diario con las tareas pendientes a cada usuario.';

    public function handle()
    {
        $this->info('Iniciando envío de resúmenes de tareas...');

        // Obtenemos todos los usuarios activos
        $users = User::where('active', true)->get();

        foreach ($users as $user) {
            // Buscamos las tareas vencidas para este usuario
            $overdueTasks = $user->tasks()
                ->where('status', 'vencida')
                ->with('contact') // Precargar el contacto para eficiencia
                ->get();
            
            // Buscamos las tareas para hoy
            $dueTodayTasks = $user->tasks()
                ->where('status', 'pendiente')
                ->whereDate('schedule_date', today())
                ->with('contact')
                ->get();
                
            // Solo enviamos el correo si el usuario tiene al menos una tarea pendiente
            if ($overdueTasks->isNotEmpty() || $dueTodayTasks->isNotEmpty()) {
                Mail::to($user->email)->send(new TaskDigestEmail($user, $overdueTasks, $dueTodayTasks));
                $this->info("-> Resumen enviado a: {$user->email}");
            } else {
                $this->line("-> Sin tareas pendientes para: {$user->email}. No se envía correo.");
            }
        }

        $this->info('Envío de resúmenes de tareas finalizado.');
        return 0;
    }
}