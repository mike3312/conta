@props(['href' => null, 'icon' => null, 'variant' => 'primary', 'size' => null, 'type' => 'button'])
@php($classes = 'btn btn-'.$variant.($size ? ' btn-'.$size : ''))
@if($href)<a href="{{ $href }}" {{ $attributes->class([$classes]) }}>@if($icon)<i class="bi {{ $icon }} me-1"></i>@endif{{ $slot }}</a>
@else<button type="{{ $type }}" {{ $attributes->class([$classes]) }}>@if($icon)<i class="bi {{ $icon }} me-1"></i>@endif{{ $slot }}</button>@endif
