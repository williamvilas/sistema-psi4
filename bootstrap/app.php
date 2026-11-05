<?php

declare(strict_types=1);

use App\Http\Middleware\ForceResponseJson;
use App\Jobs\RegisterLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php'
    )
    ->withMiddleware(function (Middleware $middleware) {
        // O Octane fica atrás do nginx: sem confiar no proxy, request()->ip()
        // seria o IP do container do nginx (access_logs, throttle de login,
        // 2FA e salas de vídeo). Só redes privadas — a porta do Octane não é
        // exposta fora da rede Docker, então ninguém de fora forja o header.
        // Só IP e esquema: confiar em X-Forwarded-Host/Port/Prefix deixaria o
        // cliente trocar o host dos links absolutos (reset de senha, reset do 2FA).
        $middleware->trustProxies(
            at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Fora do local, só aceita o host do APP_URL e seus subdomínios: um Host
        // forjado vira 400 em vez de gerar links para outro domínio.
        $middleware->trustHosts();

        $middleware->web(append: [\Illuminate\Session\Middleware\AuthenticateSession::class]);
        $middleware->web()->validateCsrfTokens(['*']);
        $middleware->api([ForceResponseJson::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            $exceptionIgnored = [
                \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
                \Illuminate\Auth\AuthenticationException::class,
                \Illuminate\Validation\ValidationException::class,
            ];

            foreach ($exceptionIgnored as $ignored) {
                if ($e instanceof $ignored) {
                    return;
                }
            }

            // Senhas, códigos (2FA/OAuth) e a chave embrulhada com seu salt (que
            // permitiria força bruta offline da senha mestre) nunca vão para o log
            $hidden = [
                'password', 'password_confirmation', 'current_password', 'token', 'code', 'state',
                'key_check', 'wrapped_private_key', 'passphrase_wrapped_key', 'passphrase_salt',
                'recovery_wrapped_key', 'recovery_salt', 'pin', 'pin_confirmation',
                // CPFs (sala de vídeo, agendamento público, cadastro de paciente e perfil) também não
                'cpf', 'document', 'payer_document',
            ];

            // Telefones das salas de vídeo e os dados das dinâmicas também não
            if ($request->is('api/video-room*')) {
                $hidden = [...$hidden, 'phone', 'participants', 'content', 'pin', 'grant'];
            }

            RegisterLog::dispatchSync([
                'data' => $request->except($hidden),
                'message' => $e->getMessage(),
                'route' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        });

    })->create();
