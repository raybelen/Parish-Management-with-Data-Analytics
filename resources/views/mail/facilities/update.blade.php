<x-mail::message>
# {{ $heading }}

Hello {{ $details['organizer'] }},

Facility: **{{ $details['facility'] }}**

Schedule: **{{ $details['schedule'] }}**

Reference: **{{ $details['reference'] }}**

@if($event === 'submitted')
Your request is pending parish staff approval. The time is reserved only after approval; other requests may be submitted for the same schedule.
@elseif($event === 'approved')
Parish staff approved your facility request.
@elseif($event === 'cancelled')
The booking was cancelled.{{ $details['reason'] ? ' Reason: '.$details['reason'] : '' }}
@elseif($event === 'expired')
The request expired because it was not verified or approved before its deadline. You may submit a new request if the time is still available.
@else
Use the secure link below to review your request.
@endif

<x-mail::button :url="$accessUrl">
View request
</x-mail::button>

If you did not make this request, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
