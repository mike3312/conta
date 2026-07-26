@props(['title' => null, 'subtitle' => null])
<x-app-card :title="$title" :subtitle="$subtitle" :padding="false" {{ $attributes }}>
    @if(isset($header))<div class="app-card-toolbar">{{ $header }}</div>@endif
    <div class="table-responsive app-table-wrap">{{ $slot }}</div>
    @if(isset($footer))<div class="app-card-footer">{{ $footer }}</div>@endif
</x-app-card>
