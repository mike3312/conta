<header>
    <h1>{{ $reportTitle }}</h1>
    <strong>{{ $company->name }}</strong> · NIT: {{ $company->tax_id ?: 'No registrado' }}<br>
    <span class="meta">{{ $periodText }} · Generado: {{ $generatedAt->format('d/m/Y H:i:s') }} · Moneda: {{ $company->currency ?: 'GTQ' }}</span>
</header>
<footer>Página <span class="page"></span></footer>
