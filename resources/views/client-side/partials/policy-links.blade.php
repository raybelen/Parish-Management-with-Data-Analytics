@foreach (['terms' => 'Terms and Conditions', 'privacy' => 'Privacy Policy', 'booking-policy' => 'Booking, Cancellation and Refunds', 'cookies' => 'Cookies and Website Use'] as $policyRoute => $policyLabel)
    <a href="{{ route($policyRoute) }}" @if (request()->routeIs($policyRoute)) aria-current="page" @endif class="inline-flex min-h-11 items-center underline-offset-4 hover:underline focus-visible:underline">{{ $policyLabel }}</a>
@endforeach
