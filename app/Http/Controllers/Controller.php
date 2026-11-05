<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ExpiredFlowException;
use App\Exceptions\PlanLimitException;
use App\Jobs\RegisterLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    public function response(callable $callback): JsonResponse
    {
        try {
            $response = $callback();

            return $this->success($response);
        } catch (ValidationException $v) {
            return response()->json($v->errors(), 400);
        } catch (ExpiredFlowException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'link' => route('login', ['code' => 'session-expired'], false),
            ], 410);
        } catch (PlanLimitException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'link' => route('plans.index', [], false),
            ], 403);
        } catch (\DomainException $d) {
            return $this->error($d->getMessage());
        } catch (\Exception $e) {
            RegisterLog::dispatchSync([
                'message' => $e->getMessage(),
                'route' => request()->fullUrl(),
                'method' => request()->method(),
            ]);

            report($e);

            return $this->error('error_server', 500);
        }
    }

    public function isForbidden(mixed $data): void
    {
        if (empty($data)) {
            abort(403);
        }
    }

    public function error(string $message, int $code = 400): JsonResponse
    {
        return response()->json(['message' => $message], $code);
    }

    public function notContent(): JsonResponse
    {
        return response()->json([], 204);
    }

    public function success(array $response = []): JsonResponse
    {
        if (empty($response)) {
            return $this->notContent();
        }

        return response()->json($response, 200);
    }

    public function getMessageSuccess(?string $title = null, ?string $message = null): array
    {
        return [
            'title' => $title ?? __('messages.title_success'),
            'message' => $message ?? __('messages.message_success'),
        ];
    }
}
