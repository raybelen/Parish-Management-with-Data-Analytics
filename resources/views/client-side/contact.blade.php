@extends('client-side.layouts.parish')

@section('content')
    <div class="section-wrap border-b border-navy/10 pt-9 pb-9"><nav aria-label="Breadcrumb" class="flex items-center gap-3 text-sm text-muted"><a href="{{ route('home') }}" class="min-h-11 inline-flex items-center hover:text-navy">Home</a><span aria-hidden="true">/</span><span aria-current="page">{{ $pageTitle }}</span></nav><h1 class="mt-4 font-display text-4xl sm:text-5xl">{{ $pageTitle }}</h1></div>
    @include('client-side.partials.contact')
@endsection
