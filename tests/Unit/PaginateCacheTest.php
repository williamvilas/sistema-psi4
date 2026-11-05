<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PaginateCacheContextEnum;
use App\Helpers\PaginateCache;
use App\Models\Agenda;
use App\Models\Documents;
use App\Models\MedicalRecords;
use App\Models\Patients;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaginateCacheTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->user = User::factory()->create();
        $this->patient = Patients::query()->create(['user_id' => $this->user->id, 'name' => 'Ana', 'phone' => '11999990000']);
    }

    /**
     * Aquece o cache do contexto e devolve um contador de execuções do callback.
     */
    private function remember(PaginateCacheContextEnum $ctx, ?int $userId = null, array $params = [], bool $global = false): int
    {
        static $calls = 0;
        $before = $calls;

        PaginateCache::remember($ctx, $userId ?? $this->user->id, $params, function () use (&$calls) {
            $calls++;

            return 'value';
        }, $global);

        return $calls - $before;
    }

    public function testItCachesSameFiltersRegardlessOfOrderAndEmptyValues(): void
    {
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA, params: ['status' => 'x', 'type' => 'y']));
        $this->assertSame(0, $this->remember(PaginateCacheContextEnum::AGENDA, params: ['type' => 'y', 'status' => 'x', 'q' => '']));
    }

    public function testItSeparatesCacheByFilterPageAndUser(): void
    {
        $other = User::factory()->create();

        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA, params: ['status' => 'x']));
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA, params: ['status' => 'z']));
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA, $other->id, ['status' => 'x']));

        $this->app['request']->query->set('page', 2);
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA, params: ['status' => 'x']));
    }

    public function testItForgetOnlyInvalidatesGivenContextAndUser(): void
    {
        $other = User::factory()->create();
        $this->remember(PaginateCacheContextEnum::AGENDA);
        $this->remember(PaginateCacheContextEnum::PAYMENTS);
        $this->remember(PaginateCacheContextEnum::AGENDA, $other->id);

        PaginateCache::forget($this->user->id, PaginateCacheContextEnum::AGENDA);

        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA));
        $this->assertSame(0, $this->remember(PaginateCacheContextEnum::PAYMENTS));
        $this->assertSame(0, $this->remember(PaginateCacheContextEnum::AGENDA, $other->id));
    }

    public function testItGlobalForgetInvalidatesOnlyListsThatMixGlobalData(): void
    {
        $this->remember(PaginateCacheContextEnum::DOCUMENTS, global: true);
        $this->remember(PaginateCacheContextEnum::AGENDA);

        PaginateCache::forgetGlobal(PaginateCacheContextEnum::DOCUMENTS);

        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::DOCUMENTS, global: true));
        $this->assertSame(0, $this->remember(PaginateCacheContextEnum::AGENDA));
    }

    public function testItAgendaObserverInvalidatesOnCreateUpdateDelete(): void
    {
        $this->remember(PaginateCacheContextEnum::AGENDA);
        $agenda = Agenda::query()->create([
            'user_id' => $this->user->id, 'patient_id' => $this->patient->id, 'type' => 'online',
            'scheduled_on' => '2026-10-05 12:00:00', 'ends_at' => '2026-10-05 13:00:00', 'status' => 'scheduled', 'price' => 100,
        ]);
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA));

        $agenda->update(['status' => 'completed']);
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA));

        $agenda->delete();
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::AGENDA));
    }

    public function testItPatientChangeInvalidatesAllPatientDependentLists(): void
    {
        foreach (PaginateCacheContextEnum::patientDependents() as $ctx) {
            $this->remember($ctx);
        }
        $this->remember(PaginateCacheContextEnum::DOCUMENTS, global: true);

        $this->patient->update(['name' => 'Ana Maria']);

        foreach (PaginateCacheContextEnum::patientDependents() as $ctx) {
            $this->assertSame(1, $this->remember($ctx), $ctx->value);
        }
        $this->assertSame(0, $this->remember(PaginateCacheContextEnum::DOCUMENTS, global: true));
    }

    public function testItPatientCreateInvalidatesOnlyPatientsList(): void
    {
        $this->remember(PaginateCacheContextEnum::PATIENTS);
        $this->remember(PaginateCacheContextEnum::AGENDA);

        Patients::query()->create(['user_id' => $this->user->id, 'name' => 'Bia', 'phone' => '11999990001']);

        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::PATIENTS));
        $this->assertSame(0, $this->remember(PaginateCacheContextEnum::AGENDA));
    }

    public function testItDocumentsObserverInvalidatesUserAndGlobalTemplates(): void
    {
        $observer = new \App\Observers\DocumentsObserver();

        $this->remember(PaginateCacheContextEnum::DOCUMENTS, global: true);
        $observer->updated(new Documents(['user_id' => $this->user->id]));
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::DOCUMENTS, global: true));

        $other = User::factory()->create();
        $this->remember(PaginateCacheContextEnum::DOCUMENTS, $other->id, global: true);
        $observer->updated(new Documents(['user_id' => null]));
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::DOCUMENTS, $other->id, global: true));
    }

    public function testItMedicalRecordsAndPaymentsObserversInvalidateOwnContext(): void
    {
        $this->remember(PaginateCacheContextEnum::MEDICAL_RECORDS);
        (new \App\Observers\MedicalRecordsObserver())->created(new MedicalRecords(['user_id' => $this->user->id]));
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::MEDICAL_RECORDS));

        $this->remember(PaginateCacheContextEnum::PAYMENTS);
        (new \App\Observers\PaymentOrderObserver())->updated((new PaymentOrder())->forceFill(['user_id' => $this->user->id]));
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::PAYMENTS));
    }

    public function testItBulkPaymentWritesInvalidateWithoutObserver(): void
    {
        $this->remember(PaginateCacheContextEnum::PAYMENTS);
        (new PaymentOrder())->updateByIdAndUser(id: 0, userId: $this->user->id, data: ['description' => 'x']);
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::PAYMENTS));

        (new PaymentOrder())->deleteInBatch(id: [0], userId: $this->user->id);
        $this->assertSame(1, $this->remember(PaginateCacheContextEnum::PAYMENTS));
    }

    public function testItPatientServicePaginateIsCachedAndInvalidatedByBulkDelete(): void
    {
        $service = app(PatientService::class);
        \Illuminate\Support\Facades\Queue::fake();

        $first = $service->paginate($this->user->id, 15, ['q' => 'Ana']);
        $this->assertSame($first, $service->paginate($this->user->id, 15, ['q' => 'Ana']));
        $this->assertCount(1, $first);

        $service->delete([encrypt((string) $this->patient->id)], $this->user->id);

        $this->assertCount(0, $service->paginate($this->user->id, 15, ['q' => 'Ana']));
    }
}
