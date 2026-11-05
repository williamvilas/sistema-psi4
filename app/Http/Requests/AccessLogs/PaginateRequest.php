<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessLogs;

use App\Enums\AccessLogActionEnum;
use App\Helpers\StringHelper;
use App\Helpers\UserHelper;
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
        return [];
    }

    public function getParams(): array
    {
        $params = $this->only(['action', 'medical_record_id', 'date']);
        $params = array_filter($params, fn ($v) => is_string($v) && $v !== '');

        if (! empty($params['action']) && ! AccessLogActionEnum::tryFrom($params['action'])) {
            unset($params['action']);
        }

        if (! empty($params['medical_record_id'])) {
            $id = StringHelper::decrypt($params['medical_record_id']);

            if ($id) {
                $params['medical_record_id'] = (int) $id;
            } else {
                unset($params['medical_record_id']);
            }
        }

        // O painel lateral é sempre de um prontuário: sem id válido, não lista a conta inteira.
        if ($this->isPanel() && empty($params['medical_record_id'])) {
            $params['medical_record_id'] = 0;
        }

        // O período vem no fuso do usuário; os registros são gravados em UTC.
        if (! empty($params['date'])) {
            $dates = explode(' - ', $params['date']);

            try {
                if (count($dates) === 2) {
                    $timezone = UserHelper::getTimezone();
                    $params['start_date'] = Carbon::createFromFormat('d/m/Y', trim($dates[0]), $timezone)->startOfDay()->utc()->toDateTimeString();
                    $params['end_date'] = Carbon::createFromFormat('d/m/Y', trim($dates[1]), $timezone)->endOfDay()->utc()->toDateTimeString();
                }
            } catch (\Throwable) {
                unset($params['start_date'], $params['end_date']);
            }
        }

        return $params;
    }

    /** Lista compacta do painel lateral das telas do prontuário. */
    public function isPanel(): bool
    {
        return $this->boolean('panel');
    }

    public function hasFilters(): bool
    {
        return $this->filled('action') || $this->filled('date');
    }
}
