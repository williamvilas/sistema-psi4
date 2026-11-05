{{-- Campos de nova senha com mostrar/ocultar e requisitos. Recebe $withCurrent (pede a senha atual). --}}
@if($withCurrent ?? false)
    <div class="form-group">
        <label for="current_password">Senha atual <span class="text-danger">*</span></label>
        <div class="prof-input prof-input-password">
            <i class="fa-solid fa-lock"></i>
            <input id="current_password" type="password" name="current_password" class="form-control" autocomplete="current-password" required>
            <button type="button" class="btn-toggle-password" aria-label="Mostrar ou ocultar senha"><i class="fa-regular fa-eye"></i></button>
        </div>
    </div>
@endif

<div class="form-group">
    <label for="password">Nova senha <span class="text-danger">*</span></label>
    <div class="prof-input prof-input-password">
        <i class="fa-solid fa-key"></i>
        <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" aria-describedby="password-rules" required>
        <button type="button" class="btn-toggle-password" aria-label="Mostrar ou ocultar senha"><i class="fa-regular fa-eye"></i></button>
    </div>
</div>

<div class="form-group">
    <label for="password_confirmation">Confirmar nova senha <span class="text-danger">*</span></label>
    <div class="prof-input prof-input-password">
        <i class="fa-solid fa-key"></i>
        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
        <button type="button" class="btn-toggle-password" aria-label="Mostrar ou ocultar senha"><i class="fa-regular fa-eye"></i></button>
    </div>
    <ul class="prof-rules" id="password-rules" aria-live="polite">
        <li data-password-rule="length"><i class="fa-regular fa-circle"></i> Pelo menos 8 caracteres</li>    </ul>
</div>
