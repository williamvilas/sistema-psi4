<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDocumentDefaults extends Model
{
    protected $table = 'user_document_defaults';

    protected $fillable = [
        'user_id',
        'type',
        'field_key',
        'value',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    /** @return array<string, string|null> valores salvos, indexados pela chave do campo */
    public function valuesByUserAndType(int $userId, string $type): array
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->pluck('value', 'field_key')
            ->all();
    }

    public function saveValue(int $userId, string $type, string $fieldKey, ?string $value): self
    {
        return $this->newQuery()->updateOrCreate(
            ['user_id' => $userId, 'type' => $type, 'field_key' => $fieldKey],
            ['value' => $value]
        );
    }

    public function deleteValue(int $userId, string $type, string $fieldKey): void
    {
        $this->newQuery()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('field_key', $fieldKey)
            ->delete();
    }
}
