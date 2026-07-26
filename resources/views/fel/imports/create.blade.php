@extends('layouts.app')
@section('title', 'Importar documentos FEL')
@section('content')
<x-page-header title="Centro de Importación FEL" subtitle="Importa documentos para revisión humana antes de cualquier proceso contable." icon="bi-cloud-arrow-up" />
<form method="POST" action="{{ route('fel-imports.store') }}" enctype="multipart/form-data" id="fel-upload-form">
    @csrf
    <div class="card shadow-sm"><div class="card-body p-4">
        <div id="fel-drop-zone" class="border border-2 rounded-4 text-center p-5 bg-light" style="border-style: dashed !important" role="button" tabindex="0">
            <i class="bi bi-cloud-arrow-up display-4 text-primary"></i>
            <h2 class="h5 mt-3">Arrastra aquí tus documentos FEL</h2>
            <p class="text-muted">XML · ZIP · XLS · XLSX · CSV</p>
            <button type="button" class="btn btn-outline-primary" id="fel-select-button">Seleccionar archivos</button>
            <input class="visually-hidden" type="file" id="fel-files" name="files[]" multiple accept=".xml,.zip,.xls,.xlsx,.csv">
        </div>
        @error('files')<div class="alert alert-danger mt-3">{{ $message }}</div>@enderror
        @foreach($errors->get('files.*') as $messages) @foreach($messages as $message)<div class="alert alert-danger mt-3">{{ $message }}</div>@endforeach @endforeach
        <div class="d-flex justify-content-between align-items-center mt-4"><strong id="fel-count">0 archivos seleccionados</strong><button class="btn btn-primary" id="fel-submit" disabled><i class="bi bi-upload me-1"></i>Procesar documentos</button></div>
        <div class="table-responsive mt-3 d-none" id="fel-list-wrap"><table class="table align-middle"><thead><tr><th>Archivo</th><th>Tipo</th><th>Tamaño</th><th></th></tr></thead><tbody id="fel-list"></tbody></table></div>
    </div></div>
</form>
<div class="alert alert-info mt-4"><i class="bi bi-info-circle me-2"></i>Todos los documentos quedarán pendientes u observados. La importación y la aprobación <strong>no crean pólizas ni movimientos contables</strong>.</div>
@endsection
@push('scripts')
<script>
(() => {
    const input = document.getElementById('fel-files'), zone = document.getElementById('fel-drop-zone'), list = document.getElementById('fel-list'), wrap = document.getElementById('fel-list-wrap'), count = document.getElementById('fel-count'), submit = document.getElementById('fel-submit');
    let files = [];
    const render = () => { const dt = new DataTransfer(); files.forEach(file => dt.items.add(file)); input.files = dt.files; list.innerHTML = ''; files.forEach((file, index) => { const row = document.createElement('tr'); row.innerHTML = `<td>${escapeHtml(file.name)}</td><td><span class="badge text-bg-light">${escapeHtml((file.name.split('.').pop() || '').toUpperCase())}</span></td><td>${(file.size / 1024).toFixed(1)} KB</td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove="${index}" aria-label="Eliminar"><i class="bi bi-trash"></i></button></td>`; list.appendChild(row); }); count.textContent = `${files.length} archivo${files.length === 1 ? '' : 's'} seleccionado${files.length === 1 ? '' : 's'}`; submit.disabled = files.length === 0; wrap.classList.toggle('d-none', files.length === 0); };
    const escapeHtml = value => { const div = document.createElement('div'); div.textContent = value; return div.innerHTML; };
    const add = selected => { for (const file of selected) if (!files.some(current => current.name === file.name && current.size === file.size)) files.push(file); render(); };
    document.getElementById('fel-select-button').addEventListener('click', () => input.click()); input.addEventListener('change', event => add(event.target.files)); list.addEventListener('click', event => { const button = event.target.closest('[data-remove]'); if (button) { files.splice(Number(button.dataset.remove), 1); render(); } });
    ['dragenter','dragover'].forEach(name => zone.addEventListener(name, event => { event.preventDefault(); zone.classList.add('border-primary'); })); ['dragleave','drop'].forEach(name => zone.addEventListener(name, event => { event.preventDefault(); zone.classList.remove('border-primary'); })); zone.addEventListener('drop', event => add(event.dataTransfer.files)); zone.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') input.click(); });
})();
</script>
@endpush
