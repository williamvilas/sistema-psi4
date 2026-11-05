<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentAccountsStatusEnum;
use App\Enums\PaymentGatewayEnum;
use App\Enums\PaymentOrderIntegrationStatusEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Models\Patients;
use App\Models\PaymentAccount;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentOrderFormTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['current_plan' => 'professional']);
        $this->patient = Patients::query()->forceCreate([
            'user_id' => $this->user->id,
            'name' => 'Maria Teste Cobrança',
            'phone' => '11987654321',
        ]);
    }

    public function testItShowsCreateFormWithSectionsAndPreview(): void
    {
        $this->connectAccount();

        $this->actingAs($this->user)->get(route('payments.create'))
            ->assertOk()
            ->assertSee('Nova cobrança')
            ->assertSee('id="form-store-payment-order"', false)
            ->assertSee('name="due_preset" id="due_preset_none" value="none" autocomplete="off" checked', false)
            ->assertSee('class="pay-preview', false)
            ->assertSee('Criar cobrança')
            ->assertDontSee('<fieldset disabled', false);
    }

    public function testItDisablesCreateFormWhenAccountNotConnected(): void
    {
        $this->actingAs($this->user)->get(route('payments.create'))
            ->assertOk()
            ->assertSee('<fieldset disabled', false);
    }

    public function testItPreselectsPatientFromQueryString(): void
    {
        $this->connectAccount();

        $this->actingAs($this->user)->get(route('payments.create', ['patient' => encrypt($this->patient->id)]))
            ->assertOk()
            ->assertSee('data-name="Maria Teste Cobrança" selected', false);
    }

    public function testItIgnoresPreselectedPatientFromAnotherUser(): void
    {
        $other = User::factory()->create();
        $otherPatient = Patients::query()->forceCreate([
            'user_id' => $other->id,
            'name' => 'Paciente De Outro Usuário',
            'phone' => '11912345678',
        ]);

        $this->actingAs($this->user)->get(route('payments.create', ['patient' => encrypt($otherPatient->id)]))
            ->assertOk()
            ->assertDontSee('Paciente De Outro Usuário');
    }

    public function testItShowsEditFormWhenOrderIsEditable(): void
    {
        $this->connectAccount();
        $order = $this->createOrder(PaymentOrderIntegrationStatusEnum::ERROR, 'Sessões de outubro');

        $this->actingAs($this->user)->get(route('payments.edit', ['id' => encrypt($order->id)]))
            ->assertOk()
            ->assertSee('id="form-update-payment-order"', false)
            ->assertSee('value="Sessões de outubro"', false)
            ->assertSee('Salvar alterações')
            ->assertDontSee('data-payment-readonly', false);
    }

    public function testItShowsEditFormWithNoticeWhenOrderAlreadyIntegrated(): void
    {
        $this->connectAccount();
        $order = $this->createOrder(PaymentOrderIntegrationStatusEnum::INTEGRATED);

        $this->actingAs($this->user)->get(route('payments.edit', ['id' => encrypt($order->id)]))
            ->assertOk()
            ->assertSee('id="form-update-payment-order"', false)
            ->assertSee(__('payments.integrated_edit_notice'))
            ->assertDontSee('data-payment-readonly', false);
    }

    public function testItShowsReadonlySummaryWhenOrderCheckoutProcessing(): void
    {
        $this->connectAccount();
        $order = $this->createOrder(PaymentOrderIntegrationStatusEnum::PROCESSING);

        $this->actingAs($this->user)->get(route('payments.edit', ['id' => encrypt($order->id)]))
            ->assertOk()
            ->assertSee('data-payment-readonly', false)
            ->assertSee(__('payments.cannot_edit'))
            ->assertDontSee('id="form-update-payment-order"', false);
    }

    public function testItDisablesEditFormWhenAccountNotConnected(): void
    {
        $order = $this->createOrder(PaymentOrderIntegrationStatusEnum::ERROR);

        $this->actingAs($this->user)->get(route('payments.edit', ['id' => encrypt($order->id)]))
            ->assertOk()
            ->assertSee('id="form-update-payment-order"', false)
            ->assertSee('<fieldset disabled', false);
    }

    private function connectAccount(): void
    {
        PaymentAccount::query()->forceCreate([
            'user_id' => $this->user->id,
            'provider' => PaymentGatewayEnum::MERCADO_PAGO->value,
            'status' => PaymentAccountsStatusEnum::INTEGRATED->value,
        ]);
    }

    private function createOrder(PaymentOrderIntegrationStatusEnum $integrationStatus, ?string $description = null): PaymentOrder
    {
        return PaymentOrder::withoutEvents(fn () => PaymentOrder::query()->forceCreate([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'amount' => 250,
            'description' => $description,
            'status' => PaymentOrderStatusEnum::PENDING->value,
            'integration_status' => $integrationStatus->value,
            'payment_methods' => ['pix', 'card'],
        ]));
    }
}
