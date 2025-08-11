<x-mail::message>
# Tu Resumen de Tareas para Hoy

Hola **{{ $user->first_name }}**,

Aquí tienes un resumen de tus tareas pendientes en el CRM.

@if($overdueTasks->isNotEmpty())
## ⚠️ Tareas Vencidas

@foreach($overdueTasks as $task)
- **{{ $task->description }}** (Venció: {{ $task->schedule_date->format('d/m/Y') }})
_Contacto: {{ $task->contact->first_name ?? '' }} {{ $task->contact->last_name ?? '' }}_
@endforeach
@endif

@if($dueTodayTasks->isNotEmpty())
## ✅ Tareas para Hoy

@foreach($dueTodayTasks as $task)
- **{{ $task->description }}** (Vence: Hoy a las {{ $task->schedule_date->format('h:i A') }})
_Contacto: {{ $task->contact->first_name ?? '' }} {{ $task->contact->last_name ?? '' }}_
@endforeach
@endif

@if($overdueTasks->isEmpty() && $dueTodayTasks->isEmpty())
¡Felicidades! No tienes tareas pendientes para hoy.
@endif

<x-mail::button :url="config('app.frontend_url') . '/crm/tasks'">
Ver Todas Mis Tareas
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>