<?php

declare(strict_types=1);

use App\Http\Controllers\VideoRoomAccessController;
use App\Http\Controllers\VideoRoomActivitiesController;
use App\Http\Controllers\VideoRoomsController;
use App\Http\Controllers\VideoRoomTranscriptionsController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\AuthenticateVideoRoomJwt;
use App\Http\Middleware\AuthenticateVideoRoomToken;
use Illuminate\Support\Facades\Route;

Route::prefix('webhook')->group(function () {
    Route::post('/mercadopago', [WebhookController::class, 'store'])->name('webhook.mercadopago');
    Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:30,1')->name('webhook.whatsapp.verify');
    Route::post('/whatsapp', [WhatsAppWebhookController::class, 'store'])->name('webhook.whatsapp');
});

// Salas de vídeo do Jitsi (API interna, token de serviço)
Route::prefix('video-rooms')->middleware(AuthenticateVideoRoomToken::class)->group(function () {
    Route::post('/', [VideoRoomsController::class, 'store'])->name('video-rooms.store');
    Route::get('/{room}', [VideoRoomsController::class, 'show'])->name('video-rooms.show');
    Route::patch('/{room}', [VideoRoomsController::class, 'update'])->name('video-rooms.update');
    Route::delete('/{room}', [VideoRoomsController::class, 'destroy'])->name('video-rooms.destroy');
});

// Público, chamado pelo front do Jitsi: troca telefone por JWT
Route::post('/video-room/access', [VideoRoomAccessController::class, 'store'])
    ->middleware('throttle:video-room-access')
    ->name('video-room.access');

// Dinâmicas da sala, chamadas pelo front do Jitsi sem login no PSI4. Só o moderador, pelo JWT da sala.
Route::prefix('video-room/activities')->middleware(AuthenticateVideoRoomJwt::class)->group(function () {
    Route::get('/context', [VideoRoomActivitiesController::class, 'context'])->middleware('throttle:60,1')->name('video-room.activities.context');
    // Só para as abas abertas antes da troca do PIN pela autorização no PSI4. Sai junto com o PIN.
    Route::post('/unlock', [VideoRoomActivitiesController::class, 'unlock'])->middleware('throttle:10,1')->name('video-room.activities.unlock');
    // Com prefixo, porque throttle sem prefixo divide o mesmo contador por IP: a consulta a cada poucos segundos travaria o resto.
    Route::post('/authorizations', [VideoRoomActivitiesController::class, 'requestAuthorization'])->middleware('throttle:20,1,authorization-request')->name('video-room.activities.authorizations.store');
    Route::get('/authorizations/{id}', [VideoRoomActivitiesController::class, 'authorizationResult'])->where('id', '[A-Za-z0-9]{64}')->middleware('throttle:60,1,authorization-poll')->name('video-room.activities.authorizations.show');
    Route::post('/', [VideoRoomActivitiesController::class, 'store'])->middleware('throttle:20,60')->name('video-room.activities.store');
});

// Transcrição da sala, chamada pelo front do Jitsi sem login no PSI4. O áudio não passa por aqui: vai direto ao S3 temporário.
Route::prefix('video-room/transcriptions')->whereUuid('uuid')->group(function () {
    Route::middleware(AuthenticateVideoRoomJwt::class)->group(function () {
        Route::get('/context', [VideoRoomTranscriptionsController::class, 'context'])->middleware('throttle:60,1')->name('video-room.transcriptions.context');
        Route::post('/', [VideoRoomTranscriptionsController::class, 'store'])->middleware('throttle:20,1')->name('video-room.transcriptions.store');
        Route::post('/{uuid}/segments', [VideoRoomTranscriptionsController::class, 'segment'])->middleware('throttle:10,1,transcription-segments')->name('video-room.transcriptions.segments');
        Route::post('/{uuid}/complete', [VideoRoomTranscriptionsController::class, 'complete'])->middleware('throttle:20,1')->name('video-room.transcriptions.complete');
    });

    // Só o convidado, com o JWT dele: uma mensagem na sala não prova consentimento.
    Route::middleware(AuthenticateVideoRoomJwt::class.':'.AuthenticateVideoRoomJwt::GUEST)->group(function () {
        Route::get('/{uuid}/consent', [VideoRoomTranscriptionsController::class, 'consentText'])->middleware('throttle:30,1')->name('video-room.transcriptions.consent-text');
        Route::post('/{uuid}/consent', [VideoRoomTranscriptionsController::class, 'consent'])->middleware('throttle:20,1')->name('video-room.transcriptions.consent');
    });
});
