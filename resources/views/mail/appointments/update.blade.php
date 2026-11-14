<x-mail::message>
# {{ $heading }}

Hello {{ $details['name'] }},

@switch($event)
@case('upcoming')
Your confirmed appointment is within the next 24 hours. Please check the schedule and venue below.
@break
@case('documents')
Your reserved appointment is still awaiting physical document verification. Please bring the required physical documents to the parish office and have them verified before the deadline below. Contact the office if you have already submitted them.

Your reservation will expire if verification is not completed before the deadline.
@break
@case('rescheduled')
Your appointment schedule has been updated. Please use the new schedule and venue below.

@if($details['status'] === 'pending')
@if($details['requires_office_approval'] ?? false)
Your revised request awaits office approval and manual priest assignment. It will appear on the calendars after approval.
@else
Your slot is reserved while the required physical documents await verification; it is not yet confirmed.
@endif
@else
Your appointment remains confirmed.
@endif
@break
@case('cancelled')
Your appointment has been cancelled and its reserved slot has been released. Contact the parish office if you need assistance or wish to arrange another appointment.
@break
@case('declined')
The parish office could not approve your appointment. Its reserved slot has been released. Please contact the office for clarification and help with the requirements before booking again.
@break
@case('expired')
@if($details['requires_office_approval'] ?? false)
Your requested service time passed before office approval. The slot has been released. Please contact the parish office to arrange another date.
@else
Your reservation expired because physical document verification was not completed before the document deadline or scheduled start, whichever came first. The slot has been released. Please contact the parish office before making a new booking.
@endif
@break
@endswitch

**Reference number:** {{ $details['reference'] }}

**Service:** {{ $details['service'] }}

**Status:** {{ str($details['status'])->headline() }}

@if($details['schedule'])
**{{ in_array($event, ['cancelled', 'declined', 'expired'], true) ? 'Previous schedule' : 'Schedule' }}:** {{ $details['schedule'] }} ({{ config('office.timezone') }})

@endif
**Church:** {{ $details['church'] }}

@if($details['deadline'] && in_array($event, ['documents', 'rescheduled'], true) && $details['status'] === 'pending')
**Document verification deadline:** {{ $details['deadline'] }} ({{ config('office.timezone') }})
@endif

<x-mail::button :url="$manageUrl">
View appointment
</x-mail::button>

Use your appointment reference and contact details to view the latest information securely.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
