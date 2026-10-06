<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\FinancialYearRequest;
use App\Models\FinancialYear;
use App\Services\Foundation\FinancialYearService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinancialYearController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', FinancialYear::class);

        return view('foundation.financial-years.index', [
            'years' => FinancialYear::query()->with('closedBy:id,name')->orderByDesc('start_date')->paginate(20),
        ]);
    }

    public function create(FinancialYearService $years, CompanyContext $context): View
    {
        $this->authorize('create', FinancialYear::class);
        $suggestion = $years->suggestRange($context->company());

        return view('foundation.financial-years.form', [
            'year' => new FinancialYear([
                'name' => $suggestion['name'],
                'start_date' => $suggestion['start'],
                'end_date' => $suggestion['end'],
                'is_current' => false,
            ]),
        ]);
    }

    public function store(FinancialYearRequest $request, FinancialYearService $years, CompanyContext $context): RedirectResponse
    {
        $years->create($context->company(), $request->validated());

        return redirect()->route('financial-years.index')->with('status', 'Financial year saved.');
    }

    public function edit(FinancialYear $financialYear): View|RedirectResponse
    {
        $this->authorize('update', $financialYear);

        if ($financialYear->is_closed) {
            return redirect()->route('financial-years.index')->withErrors([
                'year' => 'A closed financial year cannot be edited.',
            ]);
        }

        return view('foundation.financial-years.form', ['year' => $financialYear]);
    }

    public function update(FinancialYearRequest $request, FinancialYear $financialYear, FinancialYearService $years): RedirectResponse
    {
        $years->update($financialYear, $request->validated());

        return redirect()->route('financial-years.index')->with('status', 'Financial year saved.');
    }

    public function destroy(FinancialYear $financialYear, FinancialYearService $years): RedirectResponse
    {
        $this->authorize('delete', $financialYear);
        $years->delete($financialYear);

        return redirect()->route('financial-years.index')->with('status', 'Financial year removed.');
    }

    public function current(FinancialYear $financialYear, FinancialYearService $years): RedirectResponse
    {
        $this->authorize('update', $financialYear);
        $years->setCurrent($financialYear);

        return redirect()->route('financial-years.index')->with('status', 'Current financial year updated.');
    }

    public function close(FinancialYear $financialYear, FinancialYearService $years): RedirectResponse
    {
        $this->authorize('close', $financialYear);
        $years->close($financialYear, auth()->id());

        return redirect()->route('financial-years.index')->with('status', 'Financial year closed.');
    }
}
