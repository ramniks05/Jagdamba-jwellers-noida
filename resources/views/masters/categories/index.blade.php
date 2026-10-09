@extends('layouts.app')

@section('title', 'Categories')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Categories',
        'intro' => 'Ring, chain, bangle and so on. Choose a parent when you save to make a subcategory.',
        'actions' => auth()->user()->can('create', App\Models\Category::class)
            ? [['url' => route('categories.create'), 'label' => 'Add category', 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    @include('masters.partials.filters', ['action' => route('categories.index')])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="num">Subcategories</th>
                        <th class="num">Pieces</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $category->name }}</span>
                                @if ($category->parent)
                                    <div class="small text-secondary">Under {{ $category->parent->name }}</div>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $category->code }}</td>
                            <td class="num">{{ $category->children_count ?: '—' }}</td>
                            <td class="num">{{ $category->items_count }}</td>
                            <td>@include('masters.partials.status', ['active' => $category->is_active])</td>
                            @include('masters.partials.row-actions', [
                                'record' => $category,
                                'editUrl' => route('categories.edit', $category),
                                'deleteUrl' => route('categories.destroy', $category),
                            ])
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ $search !== '' || $show !== 'all' ? 'No category matches this search.' : 'No categories yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $categories])
@endsection
