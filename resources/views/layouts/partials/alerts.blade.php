@foreach(['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'status' => 'info'] as $key => $type)
    @if(session($key))
        <div class="alert alert-{{ $type }} alert-dismissible fade show app-alert" role="alert"><i class="bi bi-info-circle"></i><span>{{ session($key) }}</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
    @endif
@endforeach

@if($errors->any())
    <div class="alert alert-danger app-alert" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        <div><strong>Revisa la información ingresada.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
@endif
