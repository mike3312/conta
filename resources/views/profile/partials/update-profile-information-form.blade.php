<section>
    <h2 class="h5 mb-1">Información del perfil</h2><p class="text-muted small mb-4">Actualiza tu nombre y correo electrónico.</p>
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>
    <form method="post" action="{{ route('profile.update') }}" class="d-grid gap-3">@csrf @method('patch')
        <div><x-input-label for="name" value="Nombre"/><x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name"/><x-input-error :messages="$errors->get('name')"/></div>
        <div><x-input-label for="email" value="Correo electrónico"/><x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username"/><x-input-error :messages="$errors->get('email')"/></div>
        <div class="d-flex align-items-center gap-3"><button class="btn btn-primary" type="submit">Guardar cambios</button>@if(session('status') === 'profile-updated')<span class="text-success small">Cambios guardados.</span>@endif</div>
    </form>
</section>
