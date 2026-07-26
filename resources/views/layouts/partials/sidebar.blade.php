@php
    $mobile = $mobile ?? false;
@endphp
<div class="sidebar-inner">
    @unless($mobile)
        <a href="{{ route('dashboard') }}" class="app-brand">
            <span class="brand-mark"><i class="bi bi-calculator-fill"></i></span><span>ERP Conta</span>
        </a>
    @endunless

    <nav class="sidebar-nav" aria-label="Navegación principal">
        @foreach(config('menu') as $item)
            @if(isset($item['children']))
                @php
                    $groupId = 'menu-'.Str::slug($item['title']).($mobile ? '-mobile' : '');
                    $groupIsActive = collect($item['children'])->contains(fn ($child) => request()->routeIs(...($child['active'] ?? [$child['route']])));
                @endphp
                <button class="sidebar-link sidebar-group-toggle {{ $groupIsActive ? 'active-parent' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $groupId }}" aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}">
                    <span><i class="bi {{ $item['icon'] }}"></i>{{ $item['title'] }}</span><i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div class="collapse {{ $groupIsActive ? 'show' : '' }} sidebar-submenu" id="{{ $groupId }}">
                    @foreach($item['children'] as $child)
                        @php($childIsActive = request()->routeIs(...($child['active'] ?? [$child['route']])))
                        <a href="{{ route($child['route']) }}" class="sidebar-link sidebar-sublink {{ $childIsActive ? 'active' : '' }}" @if($childIsActive) aria-current="page" @endif>
                            <i class="bi {{ $child['icon'] }}"></i>{{ $child['title'] }}
                        </a>
                    @endforeach
                </div>
            @else
                @php($itemIsActive = request()->routeIs(...($item['active'] ?? [$item['route']])))
                <a href="{{ route($item['route']) }}" class="sidebar-link {{ $itemIsActive ? 'active' : '' }}" @if($itemIsActive) aria-current="page" @endif>
                    <i class="bi {{ $item['icon'] }}"></i>{{ $item['title'] }}
                </a>
            @endif
        @endforeach
    </nav>

    <div class="sidebar-footer"><i class="bi bi-headset"></i><div><strong>¿Necesitas ayuda?</strong><small>Centro de soporte</small></div></div>
</div>
