<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB; 

// 1. Obtener la zona horaria desde la base de datos.
//    Usamos un 'try-catch' como medida de seguridad por si la tabla no existe aún.
try {
    $timezone = DB::table('settings')->where('key', 'app_timezone')->value('value');
} catch (\Exception $e) {
    $timezone = null;
}

// 2. Usar la zona horaria encontrada o un valor por defecto seguro (UTC).
$appTimezone = $timezone ?? 'UTC';

// 3. Programar los comandos usando la zona horaria dinámica.
Schedule::command('crm:send-task-digests')->dailyAt('7:00')->timezone($appTimezone);
Schedule::command('crm:update-overdue-tasks')->hourly()->timezone($appTimezone);
Schedule::command('crm:process-sequences')->everyMinute(); // Este no necesita timezone porque se ejecuta constantemente.
Schedule::command('crm:send-task-reminders')->everyMinute();