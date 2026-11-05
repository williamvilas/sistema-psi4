<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Logs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionLogTest extends TestCase
{
    use DatabaseTransactions;

    public function testItOmitsSensitiveFieldsWhenLoggingException(): void
    {
        Route::post('/_test/exception-log', fn () => throw new \RuntimeException('falha-teste-log'));

        $this->post('/_test/exception-log', [
            'email' => 'psicologa@psi4.com.br',
            'password' => 'senha-secreta',
            'password_confirmation' => 'senha-secreta',
            'code' => '123456',
            'passphrase_wrapped_key' => 'chave-embrulhada',
            'passphrase_salt' => 'salt',
        ]);

        $log = Logs::query()->where('message', 'falha-teste-log')->latest('id')->firstOrFail();

        $this->assertSame(['email' => 'psicologa@psi4.com.br'], $log->data);
    }
}
