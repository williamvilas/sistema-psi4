<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\PsychologistSettings;
use App\Models\User;
use App\Services\PsychologistSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PsychologistSettingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PsychologistSettingService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->user = User::factory()->create();
        Auth::login($this->user);
        $this->service = app(PsychologistSettingService::class);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'clinic_name' => 'Clínica Teste',
            'cnpj' => '12.345.678/0001-90',
            'crp' => '06/123456',
            'cep' => '01001-000',
            'cidade' => 'São Paulo',
            'bairro' => 'Sé',
            'rua' => 'Praça da Sé',
            'numero' => '10',
            'complemento' => null,
            'photo_data' => null,
            'remove_photo' => false,
        ], $override);
    }

    private function photoData(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);
        $contents = ob_get_clean();

        return 'data:image/jpeg;base64,'.base64_encode($contents);
    }

    public function testItStoreWithoutPhoto(): void
    {
        $settings = $this->service->store($this->payload(), $this->user->id);

        $this->assertSame('Clínica Teste', $settings->clinic_name);
        $this->assertSame('12345678000190', $settings->cnpj);
        $this->assertNull($settings->photo_path);
    }

    public function testItStoreWithNewPhotoAndSyncUserPhoto(): void
    {
        $settings = $this->service->store($this->payload(['photo_data' => $this->photoData()]), $this->user->id);

        Storage::assertExists($settings->photo_path);
        $this->assertStringStartsWith("psychologists/{$this->user->id}/", $settings->photo_path);

        $userPhoto = $this->user->fresh()->photo;
        $this->assertStringStartsWith('users/', $userPhoto);
        $this->assertNotSame($settings->photo_path, $userPhoto);
        Storage::assertExists($userPhoto);
    }

    public function testItReplacePhotoDeletesOldFiles(): void
    {
        $first = $this->service->store($this->payload(['photo_data' => $this->photoData()]), $this->user->id);
        $firstUserPhoto = $this->user->fresh()->photo;
        Auth::login($this->user->fresh());

        $second = $this->service->store($this->payload(['photo_data' => $this->photoData()]), $this->user->id);

        $this->assertNotSame($first->photo_path, $second->photo_path);
        Storage::assertMissing($first->photo_path);
        Storage::assertMissing($firstUserPhoto);
        Storage::assertExists($second->photo_path);
    }

    public function testItKeepPhotoWhenNoPhotoDataSent(): void
    {
        $first = $this->service->store($this->payload(['photo_data' => $this->photoData()]), $this->user->id);

        $second = $this->service->store($this->payload(['clinic_name' => 'Outro']), $this->user->id);

        $this->assertSame($first->photo_path, $second->photo_path);
        Storage::assertExists($first->photo_path);
    }

    public function testItRemovePhotoClearsSettingsAndUserPhoto(): void
    {
        $first = $this->service->store($this->payload(['photo_data' => $this->photoData()]), $this->user->id);
        $userPhoto = $this->user->fresh()->photo;
        Auth::login($this->user->fresh());

        $settings = $this->service->store($this->payload(['remove_photo' => true]), $this->user->id);

        $this->assertNull($settings->photo_path);
        $this->assertNull($this->user->fresh()->photo);
        Storage::assertMissing($first->photo_path);
        Storage::assertMissing($userPhoto);
    }

    public function testItThrowExceptionWhenPhotoIsNotAnImage(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->store($this->payload([
            'photo_data' => 'data:image/jpeg;base64,'.base64_encode('not-an-image'),
        ]), $this->user->id);
    }

    public function testItThrowExceptionWhenPhotoDataHasInvalidFormat(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->store($this->payload(['photo_data' => 'invalid']), $this->user->id);
    }

    public function testItDetailsReturnsSavedSettings(): void
    {
        $this->service->store($this->payload(), $this->user->id);

        $details = $this->service->details($this->user->id);

        $this->assertInstanceOf(PsychologistSettings::class, $details);
        $this->assertSame('Clínica Teste', $details->clinic_name);
    }
}
