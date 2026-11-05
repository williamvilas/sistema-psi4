@extends('layouts.app')
@section('title', 'Relatório Financeiro')

@section('header')
    @include('reports.partials.reports-css')
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-chart-line me-2"></i> Relatório Financeiro
        </h3>
    </div>

    @include('reports.partials.period-filter', ['form' => 'form-report-financial', 'load' => 'loadFinancialReport'])

    <div id="report-summary">
        @include('reports.partials.skeleton')
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            loadFinancialReport(false);
        });
    </script>
@endsection
