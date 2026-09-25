@extends('client-side.layouts.parish')

@section('content')
    @include('client-side.partials.hero')
    @include('client-side.partials.about')
    @include('client-side.partials.services')
    @include('client-side.partials.announcements')
    @include('client-side.partials.gallery')
    @include('client-side.partials.contact')
@endsection

@include('client-side.partials.gallery-lightbox')
