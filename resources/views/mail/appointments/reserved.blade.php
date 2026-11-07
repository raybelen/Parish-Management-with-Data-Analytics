<x-mail::message>
# Your appointment slot is reserved

Hello {{ $appointment->client_full_name }},

Your requested schedule has no conflict, so the parish has reserved the slot while your physical documents are checked.

**Reference number:** {{ $appointment->reference_number }}  
**Service:** {{ str($appointment->service_type)->headline() }}  
**Reserved schedule:** {{ $appointment->starts_at->timezone(config('office.timezone'))->format('F j, Y \a\t g:i A') }}  
**Church:** {{ $appointment->confirmed_church }}  
**Submit documents by:** {{ $appointment->documents_due_at->timezone(config('office.timezone'))->format('F j, Y \a\t g:i A') }}
**Payment preference:** {{ $appointment->payment_option === 'online' ? 'Online via Xendit Sandbox (test only)' : 'Walk-in at the parish office' }}

Please bring every required physical document to the church office within three days. The appointment will be confirmed and shown on the public parish calendar after all required documents are verified. If the deadline passes first, the reserved slot will be released.

The service fee can be paid separately. Payment is not required to confirm your appointment and does not affect document verification or the reservation.

<x-mail::button :url="$manageUrl">
View appointment
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
