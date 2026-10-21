@extends('layouts.app')

@section('title', 'Own gold report')

@section('content')
    <h1 class="page-title h3 mb-1">Own gold report</h1>
    <p class="text-secondary mb-3">Pieces marked Own on a bill. They are not in stock, and a bill for them does not change the main stock.</p>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Bill</th>
                    <th>Customer</th>
                    <th>Metal</th>
                    <th>HSN</th>
                    <th class="num">Gross</th>
                    <th class="num">Rate / g</th>
                    <th class="num">Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td>
                            <a href="{{ route('sales.show', $line->sale) }}">{{ $line->sale?->number }}</a>
                            <div class="small text-secondary">{{ $line->sale?->sold_at?->timezone(config('app.timezone'))->format('d-m-Y') }}@if ($returned->contains($line->id)) · Returned @endif</div>
                        </td>
                        <td>{{ $line->sale?->customer?->name }}</td>
                        <td>{{ $line->name }}<div class="small text-secondary">{{ $line->metal_name }} {{ $line->purity_name }}</div></td>
                        <td>{{ $line->hsn_code ?: '—' }}</td>
                        <td class="num">{{ $weight((string) $line->gross_weight) }}</td>
                        <td class="num">{{ $money((string) $line->rate_per_gram) }}</td>
                        <td class="num">{{ $money((string) $line->line_amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No own gold yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $lines->links() }}</div>
@endsection
