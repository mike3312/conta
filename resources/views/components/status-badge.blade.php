@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : strtolower((string) $status);
    $label = $status instanceof \BackedEnum && method_exists($status, 'label') ? $status->label() : ucfirst($value);
    $class = match($value) { 'posted', 'active', 'open' => 'success', 'draft' => 'warning', 'voided', 'inactive', 'closed' => 'danger', default => 'secondary' };
@endphp
<span {{ $attributes->class(['status-badge', 'status-'.$class]) }}><span class="status-dot"></span>{{ $label }}</span>
