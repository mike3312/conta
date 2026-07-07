<div class="bg-dark text-white vh-100" style="width: 260px;">

    <div class="p-3">

        <h4 class="text-center mb-4">
            ERP CONTA
        </h4>

        <div class="list-group list-group-flush">

            @foreach(config('menu') as $item)

                <a
                    href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                    class="list-group-item list-group-item-action">

                    @if(isset($item['icon']))
                        <i class="bi {{ $item['icon'] }} me-2"></i>
                    @endif

                    {{ $item['title'] }}

                </a>

            @endforeach

        </div>

    </div>

</div>