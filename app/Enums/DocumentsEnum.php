<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum DocumentsEnum: string
{
    use EnumTrait;

    case PSYCHOLOGICAL_CERTIFICATE = 'psychological_certificate';
    case PSYCHOLOGIST_STATEMENT = 'psychologist_statement';
    case ATTENDANCE_STATEMENT = 'attendance_statement';
    case PSYCHOLOGICAL_REPORT = 'psychological_report';

    public static function getTexts(): array
    {
        $text = [];
        $values = self::values();

        foreach($values as $v) {
            $text[$v] = __('documents.'.$v);
        }

        asort($text);

        return $text;
    }

    /**
     * Campos extras exibidos no modal de emissão para cada tipo de documento.
     * Tipos: agenda (seleção de sessões finalizadas), date, select e textarea.
     * Os campos textarea aceitam padrão por psicólogo (user_document_defaults);
     * o texto padrão do sistema vem do slug documents.defaults.<tipo>.<campo>.
     */
    public function fields(): array
    {
        $text = fn (string $key, int $rows = 3) => [
            'key' => $key,
            'type' => 'textarea',
            'rows' => $rows,
            'label' => __('documents.fields.'.$this->value.'.'.$key),
        ];

        return match ($this) {
            self::ATTENDANCE_STATEMENT => [
                ['key' => 'agenda', 'type' => 'agenda'],
            ],
            self::PSYCHOLOGICAL_CERTIFICATE => [
                $text('text_1', 2),
                $text('text_2', 4),
            ],
            self::PSYCHOLOGIST_STATEMENT => [
                [
                    'key' => 'date',
                    'type' => 'date',
                    'label' => __('documents.fields.'.$this->value.'.date'),
                ],
                [
                    'key' => 'frequency',
                    'type' => 'select',
                    'label' => __('documents.fields.'.$this->value.'.frequency'),
                    'options' => DocumentFrequencyEnum::getTexts(),
                ],
                $text('text_1', 3),
            ],
            self::PSYCHOLOGICAL_REPORT => [
                $text('text_6', 1),
                $text('text_5', 2),
                $text('text_1', 4),
                $text('text_2', 4),
                $text('text_3', 5),
                $text('text_4', 4),
            ],
        };
    }

    /** Ícone (Font Awesome) exibido no cartão e no modal de emissão. */
    public function icon(): string
    {
        return match ($this) {
            self::ATTENDANCE_STATEMENT => 'fas fa-calendar-check',
            self::PSYCHOLOGICAL_CERTIFICATE => 'fas fa-file-medical',
            self::PSYCHOLOGIST_STATEMENT => 'fas fa-file-signature',
            self::PSYCHOLOGICAL_REPORT => 'fas fa-file-lines',
        };
    }

    /** Tom de cor do ícone (classes dash-tone-* do components.css-dash). */
    public function tone(): int
    {
        return match ($this) {
            self::ATTENDANCE_STATEMENT => 5,
            self::PSYCHOLOGICAL_CERTIFICATE => 1,
            self::PSYCHOLOGIST_STATEMENT => 4,
            self::PSYCHOLOGICAL_REPORT => 0,
        };
    }

    public function description(): string
    {
        return __('documents.descriptions.'.$this->value);
    }

    public function hasAgenda(): bool
    {
        return in_array('agenda', array_column($this->fields(), 'type'), true);
    }

    /** Chaves dos campos de texto (aceitam padrão salvo pelo psicólogo). */
    public function textKeys(): array
    {
        return array_column(
            array_filter($this->fields(), fn (array $field) => $field['type'] === 'textarea'),
            'key'
        );
    }
}
