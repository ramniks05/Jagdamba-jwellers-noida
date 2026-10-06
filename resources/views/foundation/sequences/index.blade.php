@extends('layouts.app')

@section('title', 'Document numbers')

@section('content')
    <h1 class="page-title h3 mb-2">Document numbers</h1>
    <p class="text-secondary">Issued numbers are stored permanently. Editing a series changes only future numbers.</p>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Next number</th>
                        <th>Last issued</th>
                        <th>Reset</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sequences as $sequence)
                        <tr>
                            <td>{{ $sequence->document_type->label() }}</td>
                            <td>
                                {{ $previews[$sequence->id] ?? 'Unavailable' }}
                                @if (! empty($previewErrors[$sequence->id]))
                                    <div class="small text-danger">{{ $previewErrors[$sequence->id] }}</div>
                                @endif
                            </td>
                            <td>{{ $sequence->last_issued_number ?: '—' }}</td>
                            <td>{{ $sequence->reset_policy->label() }}</td>
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
