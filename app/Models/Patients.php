<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class Patients extends Model
{
    use SoftDeletes;

    protected $table = 'patients';

    protected $fillable = [
        'user_id',
        'name',
        'birth_date',
        'birthday_md',
        'document',
        'payer_name',
        'payer_document',
        'phone',
        'email',
        'whatsapp_notifications',
        'notes',
    ];

    protected $casts = [
        'whatsapp_notifications' => 'boolean',
    ];

    public function getPatient(string $document, int $userId, array $cols = ['id']): ?self
    {
        return Patients::query()->select($cols)->where('document', $document)->where('user_id', $userId)->first();
    }

    public function getPatientByPhone(string $phone, int $userId, array $cols = ['id']): ?self
    {
        return Patients::query()->select($cols)->where('phone', $phone)->where('user_id', $userId)->first();
    }

    public function getAllByPhone(string $phone, int $userId, array $cols = ['id']): Collection
    {
        return Patients::query()->select($cols)->where('phone', $phone)->where('user_id', $userId)->get();
    }

    /**
     * Paciente pediu para sair dos avisos (responder SAIR): vale para todos os cadastros com o telefone,
     * porque os avisos de todas as psicólogas saem do mesmo número oficial.
     */
    public function disableWhatsAppNotificationsByPhone(string $phone): int
    {
        return Patients::query()
            ->where('phone', $phone)
            ->where('whatsapp_notifications', true)
            ->update(['whatsapp_notifications' => false]);
    }

    public function countSamePhone(string $phone, int $userId, int $exceptId): int
    {
        return Patients::query()
            ->where('user_id', $userId)
            ->where('phone', $phone)
            ->where('id', '!=', $exceptId)
            ->count();
    }

    public function scopeWithDuplicatePhone(Builder $query): Builder
    {
        return $query->addSelect([
            'has_duplicate_phone' => DB::table('patients as p2')
                ->selectRaw('1')
                ->whereColumn('p2.user_id', 'patients.user_id')
                ->whereColumn('p2.phone', 'patients.phone')
                ->whereColumn('p2.id', '!=', 'patients.id')
                ->whereNull('p2.deleted_at')
                ->limit(1),
        ]);
    }

    public function countByBirthdayDay(int $userId, string $monthDay): int
    {
        return Patients::query()
            ->where('user_id', $userId)
            ->where('birthday_md', $monthDay)
            ->count();
    }

    public function findByIds(array $ids, int $userId, array $cols = ['id']): Collection
    {
        return Patients::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    public function deleteInBatch(array $id, int $userId): int
    {
        return Patients::query()->whereIn('id', $id)->where('user_id', $userId)->delete();
    }

    public function findById(int $id, int $userId, array $cols = ['*']): ?self
    {
        return Patients::query()->select($cols)->where('id', $id)->where('user_id', $userId)->first();
    }

    public function updateData(int $id, array $data, int $userId): int
    {
        return Patients::query()->where('id', $id)->where('user_id', $userId)->update($data);
    }

    public function autocomplete(string $q, int $userId): Collection
    {
        return Patients::query()
            ->select(['id', 'name', 'phone'])
            ->where('user_id', $userId)
            ->where('name', 'LIKE', '%'.addcslashes($q, '%_\\').'%')
            ->limit(10)
            ->get();
    }

    public function getAllDeletedLazy(int $chunkSize = 500): LazyCollection
    {
        return self::query()
            ->onlyTrashed()
            ->select(['id'])
            ->lazyById($chunkSize);
    }
}
