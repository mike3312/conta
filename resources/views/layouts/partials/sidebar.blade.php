<div class="bg-dark text-white vh-100" style="width: 260px;">

    <div class="p-3">

        <h4 class="text-center mb-4">
            ERP CONTA
        </h4>

        <div class="list-group list-group-flush">

            @foreach(config('menu') as $item)
                @if(isset($item['children']))
                    @php
                        $groupIsActive = collect($item['children'])->contains(
                            fn ($child) => request()->routeIs(...($child['active'] ?? [$child['route']]))
                        );
                    @endphp

                    <details class="mb-1" @if($groupIsActive) open @endif>
                        <summary
                            class="list-group-item list-group-item-action d-flex align-items-center justify-content-between {{ $groupIsActive ? 'active' : '' }}"
                            style="cursor: pointer; list-style: none;">
                            <span>
                                @if(isset($item['icon']))
                                    <i class="bi {{ $item['icon'] }} me-2"></i>
                                @endif

                                {{ $item['title'] }}
                            </span>

                            <i class="bi bi-chevron-down small"></i>
                        </summary>

                        <div class="list-group list-group-flush ms-3 border-start">
                            @foreach($item['children'] as $child)
                                @php
                                    $childIsActive = request()->routeIs(...($child['active'] ?? [$child['route']]));
                                @endphp

                                <a
                                    href="{{ Route::has($child['route']) ? route($child['route']) : '#' }}"
                                    class="list-group-item list-group-item-action ps-4 {{ $childIsActive ? 'active' : '' }}"
                                    @if($childIsActive) aria-current="page" @endif>
                                    @if(isset($child['icon']))
                                        <i class="bi {{ $child['icon'] }} me-2"></i>
                                    @endif

                                    {{ $child['title'] }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    @php
                        $itemIsActive = request()->routeIs(...($item['active'] ?? [$item['route']]));
                    @endphp

                    <a
                        href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                        class="list-group-item list-group-item-action {{ $itemIsActive ? 'active' : '' }}"
                        @if($itemIsActive) aria-current="page" @endif>

                        @if(isset($item['icon']))
                            <i class="bi {{ $item['icon'] }} me-2"></i>
                        @endif

                        {{ $item['title'] }}

                    </a>
                @endif

            @endforeach

        </div>

    </div>

</div>
