@foreach(['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'status' => 'info'] as $key => $type)
    @if(session($key))
        <div class="alert alert-{{ $type }} alert-dismissible fade show app-alert" role="alert"><i class="bi bi-info-circle"></i><span>{{ session($key) }}</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
    @endif
@endforeach

@if(session('fel_reclassification_recommended'))
    @php($felRecommendation = session('fel_reclassification_recommended'))
    <div class="alert alert-warning app-alert" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        <div class="flex-grow-1">
            <strong>El NIT de la empresa ha cambiado.</strong>
            <div>Algunos documentos FEL importados anteriormente podrían requerir una nueva clasificación.</div>
            <div class="small mt-1">Puedes revisar y actualizar su clasificación desde el Centro de Importación FEL.</div>
            @if((int) data_get($felRecommendation, 'company_id') === (int) session('company_id'))
                <a href="{{ route('fel.reclassification.index') }}" class="btn btn-sm btn-warning mt-3">
                    <i class="bi bi-arrow-repeat me-1"></i>Ir a reclasificación FEL
                </a>
            @else
                <div class="small mt-2">Active la empresa {{ data_get($felRecommendation, 'company_name') }} para reclasificar sus documentos.</div>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger app-alert" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        <div><strong>Revisa la información ingresada.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
@endif
