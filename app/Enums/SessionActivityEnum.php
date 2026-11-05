<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

/**
 * Dinâmicas de apoio usadas na sessão de vídeo. Não são escalas nem testes psicológicos (ver ScaleEnum).
 */
enum SessionActivityEnum: string
{
    use EnumTrait;

    case EMOTION_WHEEL = 'emotion_wheel';
    case THERMOMETER = 'thermometer';
    case CONTROL_CIRCLES = 'control_circles';
    case QUESTION_CARDS = 'question_cards';
    case LIFE_TIMELINE = 'life_timeline';

    public function title(): string
    {
        return (string) config('session_activities.activities.'.$this->value.'.title');
    }
}
