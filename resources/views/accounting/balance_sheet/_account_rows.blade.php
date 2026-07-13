@foreach($nodes as $node)
    <tr class="{{ count($node['children']) ? 'table-light' : '' }}">
        <td style="padding-left: {{ 1 + ($level * 1.5) }}rem;">{{ $node['account']->code }}</td>
        <td>
            <span class="{{ count($node['children']) ? 'fw-semibold' : '' }}">{{ $node['account']->name }}</span>

            @if($node['is_contrary'])
                <span class="badge text-bg-warning ms-1">Saldo contrario</span>
            @endif

            @if(count($node['children']))
                <span class="badge text-bg-light border ms-1">Grupo</span>
            @endif
        </td>
        <td class="text-end">
            {{ $currency }} {{ number_format((float) $node['balance'], 2) }}
            @if($node['is_contrary'])<span class="text-warning">(contrario)</span>@endif
        </td>
    </tr>

    @if(count($node['children']))
        @include('accounting.balance_sheet._account_rows', [
            'nodes' => $node['children'],
            'level' => $level + 1,
            'currency' => $currency,
        ])

        <tr class="table-secondary">
            <th colspan="2" class="text-end">Subtotal {{ $node['account']->name }}</th>
            <th class="text-end">
                {{ $currency }} {{ number_format((float) $node['subtotal'], 2) }}
                @if($node['subtotal_is_contrary'])<span class="text-warning">(contrario)</span>@endif
            </th>
        </tr>
    @endif
@endforeach
