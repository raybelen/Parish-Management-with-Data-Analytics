<x-mail::message>
# We received your appointment request

Hello {{ $appointment->client_full_name }},

Your request has been received by the parish office and remains pending document and payment verification.

**Reference number:** {{ $appointment->reference_number }}  
**Service:** {{ str($appointment->service_type)->headline() }}  
**Requested date:** {{ $appointment->preferred_date->format('F j, Y') }}  
**Requested time:** {{ $appointment->preferred_time }}  
**Preferred church:** {{ $appointment->preferred_church }}

@if($appointment->starts_at)
The requested date, time, and venue were available and have been reserved. This reservation does not yet confirm the appointment.
@else
The requested slot could not be reserved automatically. The parish office will review an alternative schedule.
@endif

Keep your reference number. You will need it together with your email address or phone number to check the request later.

<x-mail::button :url="$manageUrl">
Manage appointment request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
