<x-mail::message>
# Your appointment is confirmed

Hello {{ $appointment->client_full_name }},

The parish office verified all required physical documents. Your appointment is confirmed and shown on the public parish calendar.

**Reference number:** {{ $appointment->reference_number }}  
**Service:** {{ str($appointment->service_type)->headline() }}  
**Confirmed schedule:** {{ $appointment->starts_at->timezone(config('office.timezone'))->format('F j, Y \a\t g:i A') }}  
**Church:** {{ $appointment->confirmed_church }}
**Payment preference:** {{ $appointment->payment_option === 'online' ? 'Online via Xendit Sandbox (test only)' : 'Walk-in at the parish office' }}

Payment may be made before the scheduled service, but it is optional and is not required to keep this appointment confirmed.

<x-mail::button :url="$manageUrl">
View appointment
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
