<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentsEnum;
use App\Models\Agenda;
use App\Models\Documents;
use App\Models\UserDocumentDefaults;
use Illuminate\Support\Facades\Lang;

readonly class DocumentFieldsService
{
    public function __construct(
        private Documents $documents,
        private UserDocumentDefaults $defaults,
        private Agenda $agenda,
    ) {
    }

    /**
     * Campos extras do tipo do documento, já com o valor inicial de cada um
     * (padrão salvo pelo psicólogo ou, na ausência, o padrão do sistema).
     */
    public function fields(int $documentId, int $userId): array
    {
        $type = $this->getType($documentId, $userId);
        $saved = $this->defaults->valuesByUserAndType(userId: $userId, type: $type->value);

        $fields = array_map(function (array $field) use ($type, $saved) {
            if ($field['type'] !== 'textarea') {
                return $field;
            }

            $field['value'] = array_key_exists($field['key'], $saved)
                ? (string) $saved[$field['key']]
                : $this->systemDefault($type, $field['key']);

            return $field;
        }, $type->fields());

        return [
            'has_agenda' => $type->hasAgenda(),
            'fields' => array_values(array_filter($fields, fn (array $field) => $field['type'] !== 'agenda')),
        ];
    }

    public function firstSessionDate(int $patientId, int $userId, string $timezone): ?string
    {
        $first = $this->agenda->firstCompletedByPatient(userId: $userId, patientId: $patientId);

        return $first?->scheduled_on?->copy()->setTimezone($timezone)->format('d/m/Y');
    }

    public function saveDefault(int $documentId, int $userId, string $fieldKey, ?string $value): void
    {
        $type = $this->getType($documentId, $userId);
        $this->assertTextField($type, $fieldKey);

        $this->defaults->saveValue(userId: $userId, type: $type->value, fieldKey: $fieldKey, value: $value);
    }

    /** Remove o padrão do psicólogo e devolve o padrão do sistema. */
    public function resetDefault(int $documentId, int $userId, string $fieldKey): string
    {
        $type = $this->getType($documentId, $userId);
        $this->assertTextField($type, $fieldKey);

        $this->defaults->deleteValue(userId: $userId, type: $type->value, fieldKey: $fieldKey);

        return $this->systemDefault($type, $fieldKey);
    }

    private function getType(int $documentId, int $userId): DocumentsEnum
    {
        $doc = $this->documents->findById(id: $documentId, userId: $userId, cols: ['id', 'type']);

        if (! $doc) {
            throw new \DomainException(__('documents.not_found'));
        }

        return $doc->type;
    }

    private function assertTextField(DocumentsEnum $type, string $fieldKey): void
    {
        if (! in_array($fieldKey, $type->textKeys(), true)) {
            throw new \DomainException(__('documents.field_not_allowed'));
        }
    }

    private function systemDefault(DocumentsEnum $type, string $fieldKey): string
    {
        $slug = 'documents.defaults.'.$type->value.'.'.$fieldKey;

        return Lang::has($slug) ? (string) __($slug) : '';
    }
}
