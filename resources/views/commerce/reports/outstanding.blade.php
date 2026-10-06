@extends('layouts.app')

@section('title', 'Outstanding')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h1 class="page-title h3 mb-0">Outstanding report</h1>
        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print report</button>
    </div>
    <form class="filter-bar no-print" method="GET" action="{{ route('reports.outstanding') }}">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="due-search">Customer, mobile, or code</label>
                <input class="form-control" id="due-search" name="search" value="{{ $search }}" placeholder="Name or mobile">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel"></i> Show</button>
                @if ($search !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('reports.outstanding') }}">Clear</a>
                @endif
            </div>
        </div>
    </form>
    <article class="report-sheet">
        <header class="report-head">
            <div>
                <div class="invoice-kicker">Customer outstanding</div>
                <h2>{{ $company->displayName() }}</h2>
            </div>
            <div>Total due {{ $total }}</div>
        </header>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Code</th>
                        <th>Mobile</th>
                        <th class="num">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><a href="{{ route('customers.show', $row['customer']) }}">{{ $row['customer']->name }}</a></td>
                            <td>{{ $row['customer']->code }}</td>
                            <td>{{ $row['customer']->mobile }}</td>
                            <td class="num">{{ $money($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Nobody has an outstanding balance.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="3">Total</td>
                            <td class="num">{{ $total }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </article>
@endsection
