@extends('layouts.app')

@section('title', 'Document numbers')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Document numbers',
        'intro' => 'How bills, orders, receipts and other papers are numbered. A number once issued never changes; edits apply to the next one.',
        'actions' => [
            ['url' => route('financial-years.index'), 'label' => 'Financial years', 'icon' => 'calendar-range'],
        ],
    ])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Next number</th>
                        <th>Last issued</th>
                        <th>Starts again</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sequences as $sequence)
                        <tr>
                            <td class="fw-semibold">{{ $sequence->document_type->label() }}</td>
                            <td class="text-nowrap">
                                @if (isset($previews[$sequence->id]))
                                    <code class="fs-6 fw-semibold text-body">{{ $previews[$sequence->id] }}</code>
                                @else
                                    <span class="text-secondary">Unavailable</span>
                                @endif
                                @if (! empty($previewErrors[$sequence->id]))
                                    <div class="small text-danger">{{ $previewErrors[$sequence->id] }}</div>
                                @endif
                            </td>
                            <td class="text-nowrap text-secondary">{{ $sequence->last_issued_number ?: '—' }}</td>
                            <td>{{ $sequence->reset_policy->label() }}</td>
                            <td>@include('masters.partials.status', ['active' => $sequence->is_active, 'off' => 'Off'])</td>
                            <td class="text-end">
                                @can('update', $sequence)
                                    <a href="{{ route('document-sequences.edit', $sequence) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
