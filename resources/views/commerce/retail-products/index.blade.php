@extends('layouts.app')

@section('title', 'Other items')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Other items',
        'intro' => 'Fixed-price products with no weight (murti, gift items). Pick them on a bill or type a one-off name and price.',
        'actions' => auth()->user()->can('create', App\Models\RetailProduct::class)
            ? [['url' => route('retail-products.create'), 'label' => 'Add item', 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    @include('masters.partials.filters', ['action' => route('retail-products.index')])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="num">Default price</th>
                        <th>GST</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td class="fw-semibold">{{ $product->name }}</td>
                            <td>{{ $product->code }}</td>
                            <td class="num">{{ $product->default_unit_price !== null ? number_format((float) $product->default_unit_price, 2) : '—' }}</td>
                            <td>
                                @if ($product->default_tax_mode->value === 'none')
                                    No GST
                                @else
                                    {{ $product->default_tax_mode->label() }}
                                    @if ($product->default_tax_percent)
                                        · {{ rtrim(rtrim(number_format((float) $product->default_tax_percent, 2), '0'), '.') }}%
                                    @endif
                                @endif
                            </td>
                            <td>@include('masters.partials.status', ['active' => $product->is_active])</td>
                            <td class="text-end">
                                @can('update', $product)
                                    <a href="{{ route('retail-products.edit', $product) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ $search !== '' || $show !== 'all' ? 'No item matches this search.' : 'No other items yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    </div>
@endsection
