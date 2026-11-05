<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ScaleEnum: string
{
    use EnumTrait;

    case PHQ9 = 'phq9';
    case GAD7 = 'gad7';
    case DASS21 = 'dass21';
    case PCL5 = 'pcl5';

    public function label(): string
    {
        return __('scales.name.'.$this->value);
    }

    /** Itens, opções, faixas e item crítico (config/scales.php). A pontuação é calculada no navegador. */
    public function definition(): array
    {
        return ['slug' => $this->value, 'name' => $this->label(), ...config('scales.'.$this->value)];
    }

    /**
     * Só o que o paciente precisa para responder pelo link: sem faixas, pontuação nem orientações clínicas
     * (a pontuação é calculada no navegador da psicóloga ao decifrar).
     */
    public function publicDefinition(): array
    {
        $config = config('scales.'.$this->value);

        return [
            'slug' => $this->value,
            'name' => $this->label(),
            'version' => $config['version'],
            'instruction' => $config['instruction'],
            'items' => $config['items'],
            'options' => $config['options'],
            'reference_event' => (bool) ($config['reference_event'] ?? false),
        ];
    }
}
