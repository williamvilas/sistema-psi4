<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Enums\AgendaStatusEnum;
use App\Enums\AgendaTypeEnum;
use App\Helpers\StringHelper;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class PaginateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'string'],
            'status' => ['nullable', 'in:'.implode(',', AgendaStatusEnum::values())],
            'pending_attendance' => ['nullable', 'boolean'],
            'type' => ['nullable', 'in:'.implode(',', AgendaTypeEnum::values())],
            'date' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $dates = explode(' - ', (string) $value);

                    if (count($dates) !== 2) {
                        $fail('O período informado é inválido.');

                        return;
                    }

                    try {
                        $start = Carbon::createFromFormat('d/m/Y', trim($dates[0]));
                        $end = Carbon::createFromFormat('d/m/Y', trim($dates[1]));

                        if ($start->gt($end)) {
                            $fail('A data inicial deve ser menor ou igual à data final.');
                        }
                    } catch (\Exception $e) {
                        $fail('As datas informadas são inválidas.');
                    }
                },
            ],
        ];
    }

    public function getPatientId(): ?int
    {
        $id = StringHelper::decrypt($this->input('patient_id'));

        return $id ? (int) $id : null;
    }

    public function isPendingAttendance(): bool
    {
        return $this->boolean('pending_attendance');
    }

    public function getStart(string $timezone): ?Carbon
    {
        $dates = $this->getPeriod();

        return $dates
            ? Carbon::createFromFormat('d/m/Y', $dates[0], $timezone)->startOfDay()->utc()
            : null;
    }

    public function getEnd(string $timezone): ?Carbon
    {
        $dates = $this->getPeriod();

        return $dates
            ? Carbon::createFromFormat('d/m/Y', $dates[1], $timezone)->endOfDay()->utc()
            : null;
    }

    private function getPeriod(): ?array
    {
        if (! $this->filled('date')) {
            return null;
        }

        return array_map('trim', explode(' - ', $this->input('date'), 2));
    }
}
