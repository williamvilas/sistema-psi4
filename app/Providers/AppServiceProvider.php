<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CnpjLookup;
use App\Contracts\ZipcodeLookup;
use App\Jobs\RegisterLog;
use App\Models\Agenda;
use App\Models\Documents;
use App\Models\MedicalRecords;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\PaymentOrder;
use App\Observers\AgendaObserver;
use App\Observers\DocumentsObserver;
use App\Observers\MedicalRecordsObserver;
use App\Observers\PatientScaleApplicationsObserver;
use App\Observers\PatientsObserver;
use App\Observers\PaymentOrderObserver;
use App\Wrappers\OpenCnpjWrapper;
use App\Wrappers\ViaCepWrapper;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Para trocar o provedor de CEP, basta apontar para outra implementação de ZipcodeLookup
        $this->app->bind(ZipcodeLookup::class, ViaCepWrapper::class);

        // Para trocar o provedor de CNPJ, basta apontar para outra implementação de CnpjLookup
        $this->app->bind(CnpjLookup::class, OpenCnpjWrapper::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Agenda::observe(AgendaObserver::class);
        Patients::observe(PatientsObserver::class);
        MedicalRecords::observe(MedicalRecordsObserver::class);
        PatientScaleApplications::observe(PatientScaleApplicationsObserver::class);
        Documents::observe(DocumentsObserver::class);
        PaymentOrder::observe(PaymentOrderObserver::class);

        $this->observeFailedJobs();

        // Tentativas de acesso à sala de vídeo: 5 a cada 15 minutos por sala + IP e, para reduzir tentativas
        // vindas de vários IPs, 30 por sala (alto para um ataque não tirar a sessão legítima do ar com facilidade)
        RateLimiter::for('video-room-access', function (Request $request) {
            $room = strtolower((string) $request->input('room'));

            return [
                Limit::perMinutes(15, 5)->by($room.'|'.$request->ip()),
                Limit::perMinutes(15, 30)->by('room|'.$room),
            ];
        });
    }

    /**
     * Observabilidade centralizada de falhas de Job: reporta a exceção (log
     * padrão) e dispara o alerta via RegisterLog, cobrindo todos os Jobs sem
     * precisar duplicar tratamento de erro em cada um deles.
     */
    private function observeFailedJobs(): void
    {
        Queue::failing(function (JobFailed $event) {
            report($event->exception);

            RegisterLog::dispatchSync([
                'data' => [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                    'attempts' => $event->job->attempts(),
                ],
                'message' => 'Falha no job '.$event->job->resolveName().'. Mensagem: '.$event->exception->getMessage(),
                'method' => $event->job->resolveName().'::failed',
            ]);
        });
    }
}
