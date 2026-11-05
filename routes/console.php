<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:backup-database-command')
    ->dailyAt('2:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('America/Sao_Paulo')
    ->when(fn () => App::environment('production'));

// O aviso (8h) roda antes do rebaixamento (9h) para o aviso do dia do vencimento sempre sair
Schedule::command('app:notify-plan-renewal-command')
    ->dailyAt('8:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('America/Sao_Paulo');

Schedule::command('app:expire-plans-command')
    ->dailyAt('9:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('America/Sao_Paulo');

Schedule::command('app:delete-patients-command')
    ->dailyAt('3:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('America/Sao_Paulo');

Schedule::command('app:delete-agenda-command')
    ->dailyAt('4:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('America/Sao_Paulo');

Schedule::command('app:delete-medical-records-command')
    ->dailyAt('5:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('America/Sao_Paulo');

Schedule::command('app:notify-pending-attendance-command')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Avisos automáticos por WhatsApp (lembrete com a antecedência configurada e link da sala 5 minutos antes)
Schedule::command('app:send-agenda-reminders-command')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:send-room-links-command')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:send-logs-command')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:delete-funnel-events-command')
    ->dailyAt('5:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('America/Sao_Paulo');

/*Schedule::command('app:delete-files-temporary-command temp')
    ->dailyAt('4:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:optimize-app-command')
    ->dailyAt('4:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();*/

Schedule::command('app:expire-session-transcriptions-command')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->timezone('America/Sao_Paulo');
