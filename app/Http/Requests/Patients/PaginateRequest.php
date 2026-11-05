<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

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
            'q' => ['nullable', 'string', 'max:100'],
            'birthday_celebrants' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $dates = explode(' - ', $value);

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
}
