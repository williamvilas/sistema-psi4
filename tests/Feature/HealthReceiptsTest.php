<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccessLogActionEnum;
use App\Enums\HealthReceiptBatchStatusEnum;
use App\Enums\HealthReceiptStatusEnum;
use App\Models\AccessLogs;
use App\Models\HealthReceipt;
use App\Models\HealthReceiptBatch;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HealthReceiptsTest extends TestCase
{
    use DatabaseTransactions;

    private function professional(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['current_plan' => 'professional', 'document' => '52998224725'], $attributes));
    }

    private function patientFor(User $user, ?string $document = '11144477735', ?string $payerDocument = null): Patients
    {
        return Patients::query()->create([
            'user_id' => $user->id,
            'name' => 'Maria Paciente',
            'document' => $document,
            'payer_name' => $payerDocument ? 'Joana Responsável' : null,
            'payer_document' => $payerDocument,
            'phone' => '119'.random_int(10000000, 99999999),
        ]);
    }

    public function testItRendersPageAndListsReceiptsWithIssues(): void
    {
        $user = $this->professional();
        $patient = $this->patientFor($user, null);

        $this->actingAs($user)->get(route('health-receipts.index'))->assertOk()->assertSee('Gerar arquivo para o Carnê-Leão');

        $this->actingAs($user)->postJson(route('health-receipts.store'), [
            'patient_id' => encrypt($patient->id),
            'amount' => 'R$ 150,00',
            'paid_on' => '10/10/2026',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson(route('health-receipts.paginate'))->assertOk();

        $this->assertStringContainsString('Paciente sem CPF', $response->json('html'));
        $this->assertStringContainsString('1 recibo a emitir', $response->json('overview'));
    }

    public function testItGeneratesDownloadsAndUndoesBatch(): void
    {
        // Arrange
        $user = $this->professional();
        $patient = $this->patientFor($user, '11144477735', '39053344705');

        $this->actingAs($user)->postJson(route('health-receipts.store'), [
            'patient_id' => encrypt($patient->id),
            'amount' => 'R$ 1.250,00',
            'paid_on' => '14/10/2026',
            'description' => 'Sessão; de outubro',
        ])->assertOk();

        // Act: gerar
        $response = $this->actingAs($user)->postJson(route('health-receipts.batches.store'), [
            'start_date' => '01/10/2026',
            'end_date' => '31/10/2026',
        ])->assertOk();

        $this->assertCount(1, $response->json('downloads'));
        $this->assertSame(0, $response->json('skipped'));

        $receipt = HealthReceipt::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(HealthReceiptStatusEnum::EXPORTED, $receipt->status);
        $this->assertSame('39053344705', $receipt->payer_document);

        // Act: baixar
        $download = $this->actingAs($user)->get($response->json('downloads.0.url'))->assertOk();
        $content = mb_convert_encoding($download->streamedContent(), 'UTF-8', 'ISO-8859-1');

        $this->assertSame(
            "14/10/2026;R01.001.001;255;1250,00;;Sessão de outubro;PF;39053344705;11144477735;;;;;S;52998224725;\r\n",
            $content,
        );
        $this->assertTrue(AccessLogs::query()->where('user_id', $user->id)->where('action', AccessLogActionEnum::HEALTH_RECEIPTS_DOWNLOADED->value)->exists());

        // Act: um segundo arquivo do mesmo período não repete o recibo
        $this->actingAs($user)->postJson(route('health-receipts.batches.store'), [
            'start_date' => '01/10/2026',
            'end_date' => '31/10/2026',
        ])->assertStatus(400)->assertJson(['message' => __('health-receipts.nothing_to_export')]);

        // Act: marcar importado e desfazer
        $batch = HealthReceiptBatch::query()->where('user_id', $user->id)->firstOrFail();
        $this->actingAs($user)->postJson(route('health-receipts.batches.imported', ['id' => encrypt($batch->id)]))->assertOk();
        $this->actingAs($user)->postJson(route('health-receipts.batches.undo', ['id' => encrypt($batch->id)]))->assertOk();

        // Assert
        $this->assertSame(HealthReceiptBatchStatusEnum::UNDONE, $batch->fresh()->status);
        $receipt->refresh();
        $this->assertSame(HealthReceiptStatusEnum::PENDING, $receipt->status);
        $this->assertNull($receipt->health_receipt_batch_id);
        $this->assertNull($receipt->payer_document);
    }

    public function testItDoesNotDownloadBatchFromAnotherUser(): void
    {
        $owner = $this->professional();
        $other = $this->professional(['document' => '11144477735']);

        $batch = HealthReceiptBatch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $owner->id,
            'year' => 2026,
            'receipts_count' => 0,
            'total_amount' => 0,
            'status' => HealthReceiptBatchStatusEnum::GENERATED->value,
        ]);

        $this->actingAs($other)->get(route('health-receipts.batches.download', ['id' => encrypt($batch->id)]))->assertNotFound();
    }

    public function testItAllowsGenerationOnFreePlan(): void
    {
        $user = $this->professional(['current_plan' => 'free']);

        $status = $this->actingAs($user)->postJson(route('health-receipts.batches.store'), [
            'start_date' => '01/10/2026',
            'end_date' => '31/10/2026',
        ])->getStatusCode();

        // Recibos em lote não têm limite no Grátis: a resposta vem da própria geração, nunca de bloqueio
        $this->assertNotSame(403, $status);
    }

    public function testItSavesFinancialResponsibleOnPatient(): void
    {
        $user = $this->professional();
        $patient = $this->patientFor($user);

        $this->actingAs($user)->putJson(route('patients.update', ['id' => encrypt($patient->id)]), [
            'name' => 'Maria Paciente',
            'phone' => '(11) 98888-7777',
            'document' => '111.444.777-35',
            'has_payer' => '1',
            'payer_name' => 'Joana Responsável',
            'payer_document' => '390.533.447-05',
        ])->assertOk();

        $patient->refresh();
        $this->assertSame('Joana Responsável', $patient->payer_name);
        $this->assertSame('39053344705', $patient->payer_document);
    }
}
