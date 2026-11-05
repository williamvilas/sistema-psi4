@extends('layouts.app')
@section('title', 'Dashboard')

@section('header')
    @include('components.css-dash')
    <style>
        .dash-welcome h2 { font-size: 2rem; font-weight: 700; color: var(--dash-ink); margin-bottom: 2px; }
        .dash-welcome p { color: var(--dash-muted); margin: 0; font-size: 1.4rem; }
        .fas.fa-home {font-size: 1.6rem;}
        .dash-tile-icon .fa-solid, .dash-tile-icon .fas {font-size: 1.65rem;}
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-dashboard', ['css' => 'me-2']) Dashboard
        </h3>
    </div>

    <div class="dash-welcome mb-4">
        <h2>Olá, {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}!</h2>
        <p>{{ now(auth()->user()->timezone)->translatedFormat('l, d \d\e F \d\e Y') }} · veja como está o seu consultório hoje.</p>
    </div>

    <div class="list-paginate">
        <div class="dash-skeleton"></div>
        <div class="dash-skeleton"></div>
        <div class="dash-skeleton"></div>
    </div>

@endsection

@section('footer')
    <x-funnel-tag tag="dashboard" />
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            registerScreenResolution();
            reportDashboard();
        });
    </script>
@endsection
