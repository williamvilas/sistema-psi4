@extends('layouts.app')
@section('title', 'Faltas e Cancelamentos')
@section('header')
    @include('reports.partials.reports-css')
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-user-times me-2"></i> Faltas e Cancelamentos
        </h3>
    </div>

    @include('reports.partials.period-filter', ['form' => 'form-report-attendance', 'load' => 'loadAttendanceReport', 'export' => 'exportAttendanceReport'])

    <div id="report-summary">
        @include('reports.partials.skeleton')
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            loadAttendanceReport(false);
        });
    </script>
@endsection
