@if($transcriptions->isNotEmpty())
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th scope="col">Paciente</th>
                    <th scope="col">Sessão</th>
                    <th scope="col">Situação</th>
                    <th scope="col"><span class="visually-hidden">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($transcriptions as $value)
                    <tr>
                        <td>{{ $value->patient?->name ?? __('session-transcriptions.patient_removed') }}</td>
                        <td>{{ $value->occurred_on->format('d/m/Y') }}</td>
                        <td>{{ __('session-transcriptions.status.'.$value->status->value) }}</td>
                        <td class="text-end">
                            <a href="{{ route('session-transcriptions.consent', ['uuid' => $value->uuid]) }}" class="btn btn-sm btn-outline-secondary">Ver consentimento</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $transcriptions->links() }}
@else
    <p class="text-muted text-center py-5">Nenhuma transcrição registrada.</p>
@endif
