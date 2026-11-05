@extends('layouts.app')
@section('title', 'Senha Mestre dos Prontuários')

@section('header')
    @include('components.css-dash')
    @include('settings.partials.settings-css')
    <style>
        .ek-wrap { max-width: 860px; }
        .ek-stepper { display: flex; gap: .5rem; margin: 0 0 1.5rem; padding: 0; list-style: none; }
        .ek-stepper li { flex: 1; min-width: 0; }
        .ek-stepper-bar { display: block; height: 6px; border-radius: 6px; background: var(--dash-line); margin-bottom: .5rem; }
        .ek-stepper-label { display: block; font-size: 1rem; font-weight: 600; color: var(--dash-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ek-stepper li.is-done .ek-stepper-bar, .ek-stepper li.is-current .ek-stepper-bar { background: var(--dash-primary); }
        .ek-stepper li.is-current .ek-stepper-label { color: var(--dash-ink); }
        .ek-list { padding-left: 1.2rem; color: var(--dash-ink); }
        .ek-list li { margin-bottom: .5rem; }
        .ek-alert { display: flex; gap: .6rem; align-items: flex-start; background: #fff4d9; color: #7a5300; border-radius: 14px; padding: .8rem 1rem; margin-bottom: 1rem; }
        .ek-alert i { margin-top: .2rem; }
        .ek-key {
            display: block; font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 1.25rem; font-weight: 700; letter-spacing: .05em;
            color: var(--dash-ink); text-align: center; padding: 1rem; border: 1px dashed #c9d4dd; border-radius: 14px; background: #fbfcfd; word-break: break-all;
        }
        .ek-actions { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-top: 1.25rem; }
        .ek-collapse-toggle { background: none; border: 0; padding: 0; text-align: left; width: 100%; }
    </style>
@endsection

@section('content')
    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-key me-2"></i> Senha mestre dos prontuários
        </h3>
    </div>

    <div id="encryption-key-page" class="ek-wrap" data-redirect="{{ $redirect }}" data-email="{{ auth()->user()->email }}">

        @if(! $exists)
            <div class="dash-card cfg-card" id="ek-setup">
                <ol class="ek-stepper" aria-label="Etapas">
                    <li class="is-current" data-ek-indicator="1"><span class="ek-stepper-bar"></span><span class="ek-stepper-label">Como funciona</span></li>
                    <li data-ek-indicator="2"><span class="ek-stepper-bar"></span><span class="ek-stepper-label">Senha mestre</span></li>
                    <li data-ek-indicator="3"><span class="ek-stepper-bar"></span><span class="ek-stepper-label">Chave de recuperação</span></li>
                </ol>

                <div data-ek-step="1">
                    <h4 class="cfg-head-title mb-3">Seus prontuários protegidos de ponta a ponta</h4>
                    <ul class="ek-list">
                        <li>O conteúdo de cada prontuário é <strong>criptografado no seu navegador</strong> antes de ser enviado, com AES-256.</li>
                        <li>Só abre com a sua <strong>senha mestre</strong>, que nunca é enviada ao servidor. Nem a equipe do {{ config('app.name') }} consegue ler seus prontuários.</li>
                        <li>Você também recebe uma <strong>chave de recuperação</strong>, para o caso de esquecer a senha mestre.</li>
                    </ul>
                    <div class="ek-alert" role="note">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>Se você perder a senha mestre <strong>e</strong> a chave de recuperação, os prontuários não poderão ser abertos por ninguém. Lembre que a Resolução CFP 001/2009 exige a guarda dos registros por no mínimo 5 anos.</span>
                    </div>
                    <div class="ek-actions">
                        <button type="button" class="btn btn-primary" id="ek-start">Começar</button>
                    </div>
                </div>

                <form id="form-store-encryption-key" class="d-none" data-ek-step="2" autocomplete="off" novalidate>
                    <h4 class="cfg-head-title mb-1">Crie sua senha mestre</h4>
                    <p class="cfg-head-sub mb-3">Use pelo menos 10 caracteres. Uma frase fácil de lembrar e difícil de adivinhar funciona bem. Não use a mesma senha do login.</p>

                    <div class="form-group px-0">
                        <label for="passphrase">Senha mestre <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="passphrase" id="passphrase" autocomplete="new-password" required>
                    </div>
                    <div class="form-group px-0">
                        <label for="passphrase_confirmation">Confirme a senha mestre <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="passphrase_confirmation" id="passphrase_confirmation" autocomplete="new-password" required>
                    </div>

                    <div class="ek-actions">
                        <button type="button" class="btn btn-outline-secondary" onclick="goToEncryptionKeyStep(1)">Voltar</button>
                        <button type="submit" class="btn btn-primary">Continuar</button>
                    </div>
                </form>

                <div class="d-none" data-ek-step="3">
                    <h4 class="cfg-head-title mb-1">Guarde sua chave de recuperação</h4>
                    <p class="cfg-head-sub mb-3">Ela é mostrada <strong>só agora</strong>. Baixe ou copie e guarde em local seguro, separado do computador (ex.: impressa ou em um gerenciador de senhas).</p>

                    <code class="ek-key" id="ek-recovery-key"></code>

                    <div class="ek-actions">
                        <button type="button" class="btn btn-outline-secondary ek-download" data-target="#ek-recovery-key"><i class="fas fa-download me-1"></i> Baixar arquivo</button>
                        <button type="button" class="btn btn-outline-secondary ek-copy" data-target="#ek-recovery-key"><i class="fas fa-copy me-1"></i> Copiar</button>
                    </div>

                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="ek-saved">
                        <label class="form-check-label" for="ek-saved">Guardei minha chave de recuperação em local seguro.</label>
                    </div>

                    <div class="ek-actions">
                        <button type="button" class="btn btn-primary" id="ek-finish" disabled>Concluir</button>
                    </div>
                </div>
            </div>
        @else
            <div class="dash-card cfg-card mb-4">
                <div class="cfg-head">
                    <div class="cfg-head-icon"><i class="fas fa-lock"></i></div>
                    <div class="cfg-head-body">
                        <h4 class="cfg-head-title">Criptografia de ponta a ponta ativa <span class="dash-chip dash-chip-success">Ativa</span></h4>
                        <p class="cfg-head-sub">O conteúdo dos prontuários é cifrado no seu navegador com a sua senha mestre. Desbloqueados, eles ficam acessíveis neste navegador por até 30 minutos sem uso.</p>
                    </div>
                </div>
                <div class="ek-actions mt-0">
                    <button type="button" class="btn btn-outline-secondary" id="ek-lock"><i class="fas fa-lock me-1"></i> Bloquear agora neste navegador</button>
                    <a href="{{ route('access-logs.index') }}" class="btn btn-outline-secondary"><i class="fas fa-clock-rotate-left me-1"></i> Ver atividade de acesso</a>
                </div>
            </div>

            <div class="dash-card cfg-card mb-4">
                <button class="ek-collapse-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#ek-change-body" aria-expanded="false" aria-controls="ek-change-body">
                    <span class="cfg-row-title"><i class="fas fa-pen me-2"></i> Alterar senha mestre</span>
                    <span class="cfg-row-detail d-block">Os prontuários continuam os mesmos; só a senha que os abre muda.</span>
                </button>
                <div class="collapse" id="ek-change-body">
                    <form id="form-update-passphrase" class="mt-3" autocomplete="off" novalidate>
                        <div class="form-group px-0">
                            <label for="current_passphrase">Senha mestre atual <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_passphrase" id="current_passphrase" autocomplete="current-password" required>
                        </div>
                        <div class="form-group px-0">
                            <label for="new_passphrase">Nova senha mestre <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_passphrase" id="new_passphrase" autocomplete="new-password" required>
                        </div>
                        <div class="form-group px-0">
                            <label for="new_passphrase_confirmation">Confirme a nova senha mestre <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_passphrase_confirmation" id="new_passphrase_confirmation" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Alterar senha mestre</button>
                    </form>
                </div>
            </div>

            <div class="dash-card cfg-card mb-4" id="ek-recover">
                <button class="ek-collapse-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#ek-recover-body" aria-expanded="false" aria-controls="ek-recover-body">
                    <span class="cfg-row-title"><i class="fas fa-life-ring me-2"></i> Esqueci a senha mestre</span>
                    <span class="cfg-row-detail d-block">Use a chave de recuperação para criar uma nova senha mestre.</span>
                </button>
                <div class="collapse" id="ek-recover-body">
                    <form id="form-recover-encryption-key" class="mt-3" autocomplete="off" novalidate>
                        <div class="form-group px-0">
                            <label for="recovery_key">Chave de recuperação <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="recovery_key" id="recovery_key" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX" spellcheck="false" required>
                        </div>
                        <div class="form-group px-0">
                            <label for="recover_new_passphrase">Nova senha mestre <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_passphrase" id="recover_new_passphrase" autocomplete="new-password" required>
                        </div>
                        <div class="form-group px-0">
                            <label for="recover_new_passphrase_confirmation">Confirme a nova senha mestre <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_passphrase_confirmation" id="recover_new_passphrase_confirmation" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Recuperar acesso</button>
                    </form>
                </div>
            </div>

            <div class="dash-card cfg-card mb-4">
                <button class="ek-collapse-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#ek-recovery-body" aria-expanded="false" aria-controls="ek-recovery-body">
                    <span class="cfg-row-title"><i class="fas fa-rotate me-2"></i> Gerar nova chave de recuperação</span>
                    <span class="cfg-row-detail d-block">Perdeu a chave ou acha que alguém a viu? Gere outra; a anterior deixa de funcionar.</span>
                </button>
                <div class="collapse" id="ek-recovery-body">
                    <form id="form-update-recovery-key" class="mt-3" autocomplete="off" novalidate>
                        <div class="form-group px-0">
                            <label for="recovery_current_passphrase">Senha mestre <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_passphrase" id="recovery_current_passphrase" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Gerar nova chave</button>
                    </form>

                    <div id="ek-new-recovery" class="d-none mt-4">
                        <div class="ek-alert" role="note">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>Esta chave é mostrada <strong>só agora</strong>. Guarde-a em local seguro.</span>
                        </div>
                        <code class="ek-key" id="ek-new-recovery-key"></code>
                        <div class="ek-actions">
                            <button type="button" class="btn btn-outline-secondary ek-download" data-target="#ek-new-recovery-key"><i class="fas fa-download me-1"></i> Baixar arquivo</button>
                            <button type="button" class="btn btn-outline-secondary ek-copy" data-target="#ek-new-recovery-key"><i class="fas fa-copy me-1"></i> Copiar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <p class="text-muted small">
            <i class="fas fa-circle-info me-1"></i>
            Saiba o que é criptografado, onde ficam os dados e quem tem acesso em <a href="{{ route('security') }}" target="_blank" rel="noopener">Segurança e privacidade</a>.
        </p>
    </div>
@endsection
