@props(['title', 'subtitle' => null, 'icon' => null])
<div {{ $attributes->class(['page-header']) }}>
    <div class="d-flex align-items-start gap-3">
        @if($icon)<span class="page-header-icon"><i class="bi {{ $icon }}"></i></span>@endif
        <div><h1>{{ $title }}</h1>@if($subtitle)<p>{{ $subtitle }}</p>@endif</div>
    </div>
    @if(isset($actions))<div class="page-header-actions">{{ $actions }}</div>@endif
</div>
