<x-mail::message>
# Invitación a Reunión

Hola,

Has sido invitado a la siguiente reunión:

**Título:** {{ $activity->meeting_title }}
**Fecha y Hora:** {{ $activity->schedule_date->format('d/m/Y h:i A') }}
**Contacto Principal:** {{ $activity->contact->first_name }} {{ $activity->contact->last_name }}

**Descripción:**
{{ $activity->description }}

Gracias,
<br>
{{ config('app.name') }}
</x-mail::message>