<x-mail::message>
# Your appointment is confirmed

Hello {{ $appointment->client_full_name }},

The parish office has confirmed your appointment.

**Reference number:** {{ $appointment->reference_number }}  
**Service:** {{ str($appointment->service_type)->headline() }}  
**Confirmed schedule:** {{ $appointment->starts_at->timezone(config('office.timezone'))->format('F j, Y \a\t g:i A') }}  
**Church:** {{ $appointment->confirmed_church }}

Please bring the required physical documents and arrive as instructed by the parish office.

<x-mail::button :url="$manageUrl">
View appointment
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
