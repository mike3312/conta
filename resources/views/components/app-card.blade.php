@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->class(['app-card']) }}>
    @if($title || isset($header))
        <header class="app-card-header">
            <div>@if($title)<h2>{{ $title }}</h2>@endif @if($subtitle)<p>{{ $subtitle }}</p>@endif</div>
            @if(isset($header)){{ $header }}@endif
        </header>
    @endif
    <div @class(['app-card-body', 'p-0' => ! $padding])>{{ $slot }}</div>
</section>
