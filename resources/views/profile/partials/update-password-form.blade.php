<section>
    <h2 class="h5 mb-1">Actualizar contraseña</h2><p class="text-muted small mb-4">Usa una contraseña segura y única.</p>
    <form method="post" action="{{ route('password.update') }}" class="d-grid gap-3">@csrf @method('put')
        <div><x-input-label for="update_password_current_password" value="Contraseña actual"/><x-text-input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"/><x-input-error :messages="$errors->updatePassword->get('current_password')"/></div>
        <div><x-input-label for="update_password_password" value="Nueva contraseña"/><x-text-input id="update_password_password" name="password" type="password" autocomplete="new-password"/><x-input-error :messages="$errors->updatePassword->get('password')"/></div>
        <div><x-input-label for="update_password_password_confirmation" value="Confirmar contraseña"/><x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"/></div>
        <div><button class="btn btn-primary" type="submit">Actualizar contraseña</button>@if(session('status') === 'password-updated')<span class="text-success small ms-2">Contraseña actualizada.</span>@endif</div>
    </form>
</section>
