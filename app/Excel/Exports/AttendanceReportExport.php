<?php

declare(strict_types=1);

namespace App\Excel\Exports;

use App\Models\Agenda;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Agenda $agenda,
        private readonly int $userId,
        private readonly Carbon $startUtc,
        private readonly Carbon $endUtc,
        private readonly string $timezone,
    ) {
    }

    public function query(): Builder
    {
        return $this->agenda->queryBetweenWithPatient(
            $this->userId,
            $this->startUtc,
            $this->endUtc,
            ['id', 'patient_id', 'type', 'scheduled_on', 'status', 'price'],
        );
    }

    public function headings(): array
    {
        return array_values(__('reports.attendance_export_headings'));
    }

    public function map($row): array
    {
        return [
            $row->scheduled_on->copy()->setTimezone($this->timezone)->format('d/m/Y H:i'),
            $row->patient?->name,
            __('agenda.'.$row->type),
            __('agenda_status.'.$row->status->value),
            (float) $row->price,
        ];
    }
}
