@props(['icon' => 'bi-inbox', 'title' => 'Sin información', 'description' => null])
<div {{ $attributes->class(['empty-state']) }}><span><i class="bi {{ $icon }}"></i></span><h3>{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif @if(isset($action))<div class="mt-3">{{ $action }}</div>@endif</div>
