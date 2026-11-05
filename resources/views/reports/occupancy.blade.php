@extends('layouts.app')
@section('title', 'Ocupação da Agenda')
@section('header')
    @include('reports.partials.reports-css')
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-calendar-check me-2"></i> Ocupação da Agenda
        </h3>
    </div>

    @include('reports.partials.period-filter', ['form' => 'form-report-occupancy', 'load' => 'loadOccupancyReport', 'future' => true])

    <div id="report-summary">
        @include('reports.partials.skeleton')
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            loadOccupancyReport(false);
        });
    </script>
@endsection
