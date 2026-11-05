<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

use App\Http\Requests\CustomRequest;
use App\Services\PatientEvolutionService;
use Illuminate\Validation\Rule;

class EvolutionRequest extends CustomRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required'],
            'period' => ['required', 'integer', Rule::in(PatientEvolutionService::PERIODS)],
        ];
    }

    public function getPeriod(): int
    {
        return (int) $this->input('period');
    }
}
