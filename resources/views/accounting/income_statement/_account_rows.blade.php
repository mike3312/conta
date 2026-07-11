@foreach($nodes as $node)
    <tr class="{{ count($node['children']) ? 'table-light' : '' }}">
        <td style="padding-left: {{ 1 + ($level * 1.5) }}rem;">
            {{ $node['account']->code }}
        </td>
        <td>
            <span class="{{ count($node['children']) ? 'fw-semibold' : '' }}">{{ $node['account']->name }}</span>

            @if($node['is_contrary'])
                <span class="badge text-bg-warning ms-1">Saldo contrario</span>
            @endif

            @if(count($node['children']))
                <span class="badge text-bg-light border ms-1">Grupo</span>
            @endif
        </td>

        @if($showDetails)
            <td class="text-end">{{ $currency }} {{ number_format((float) $node['debit'], 2) }}</td>
            <td class="text-end">{{ $currency }} {{ number_format((float) $node['credit'], 2) }}</td>
        @endif

        <td class="text-end">
            {{ $currency }} {{ number_format((float) $node['net'], 2) }}
            @if($node['is_contrary'])
                <span class="text-warning">(contrario)</span>
            @endif
        </td>
    </tr>

    @if(count($node['children']))
        @include('accounting.income_statement._account_rows', [
            'nodes' => $node['children'],
            'level' => $level + 1,
            'type' => $type,
            'currency' => $currency,
            'showDetails' => $showDetails,
        ])

        <tr class="table-secondary">
            <th colspan="{{ $showDetails ? 4 : 2 }}" class="text-end">
                Subtotal {{ $node['account']->name }}
            </th>
            <th class="text-end">
                {{ $currency }} {{ number_format((float) $node['subtotal'], 2) }}
                @if($node['subtotal_is_contrary'])
                    <span class="text-warning">(contrario)</span>
                @endif
            </th>
        </tr>
    @endif
@endforeach
