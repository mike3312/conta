@foreach($nodes as $node)
<tr class="{{ count($node['children']) ? 'section' : '' }}"><td>{{ $node['account']->code }}</td><td style="padding-left:{{ $level*10+4 }}px">{{ $node['account']->name }}</td><td class="num">Q {{ number_format((float)($node['balance'] ?? $node['net']),2) }}@if($node['is_contrary']) (contrario)@endif</td></tr>
@if(count($node['children']))
    @include('accounting.exports._hierarchy_rows', ['nodes'=>$node['children'],'level'=>$level+1])
    <tr class="total"><td colspan="2">Subtotal {{ $node['account']->name }}</td><td class="num">Q {{ number_format((float)$node['subtotal'],2) }}</td></tr>
@endif
@endforeach
