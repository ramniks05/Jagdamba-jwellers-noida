@extends('layouts.app')

@section('title', 'Branches')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Branches',
        'intro' => 'Each shop counter you bill from. The head office address is the default on bills.',
        'actions' => auth()->user()->can('create', App\Models\Branch::class)
            ? [['url' => route('branches.create'), 'label' => 'Add branch', 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Code</th>
                        <th>Contact</th>
                        <th>GSTIN</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $branch->name }}</span>
                                @if ($branch->is_head_office)
                                    <span class="order-status is-ready ms-1">Head office</span>
                                @endif
                                <div class="small text-secondary">{{ collect([$branch->address_line1, $branch->city, $branch->state])->filter()->join(', ') }}</div>
                            </td>
                            <td class="text-nowrap">{{ $branch->code }}</td>
                            <td class="text-nowrap">{{ $branch->mobile ?: ($branch->phone ?: '—') }}</td>
                            <td class="text-nowrap">{{ $branch->gstin ?: '—' }}</td>
                            <td>@include('masters.partials.status', ['active' => $branch->status === App\Enums\BranchStatus::Active, 'off' => 'Inactive'])</td>
                            <td class="text-end text-nowrap">
                                @can('update', $branch)
                                    <a href="{{ route('branches.edit', $branch) }}">Edit</a>
                                @endcan
                                @can('delete', $branch)
                                    @unless ($branch->is_head_office)
                                        <form class="d-inline" method="POST" action="{{ route('branches.destroy', $branch) }}" onsubmit="return confirm('Remove {{ addslashes($branch->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-link text-danger p-0 ms-2 align-baseline" type="submit">Remove</button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No branches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $branches])
@endsection
