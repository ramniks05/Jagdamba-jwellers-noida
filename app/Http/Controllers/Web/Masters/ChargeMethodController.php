<?php

namespace App\Http\Controllers\Web\Masters;

use App\Enums\ChargeAppliesTo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\ChargeMethodRequest;
use App\Models\ChargeMethod;
use App\Services\Masters\ChargeMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChargeMethodController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ChargeMethod::class);

        $methods = ChargeMethod::query()->orderBy('sort_order')->orderBy('name')->get()->groupBy(
            fn (ChargeMethod $method) => $method->applies_to->value,
        );

        return view('masters.charges.index', [
            'groups' => [
                ChargeAppliesTo::Making->value => ChargeAppliesTo::Making->label(),
                ChargeAppliesTo::Wastage->value => ChargeAppliesTo::Wastage->label(),
            ],
            'methods' => $methods,
        ]);
    }

    public function edit(ChargeMethod $chargeMethod): View
    {
        $this->authorize('update', $chargeMethod);

        return view('masters.charges.form', ['method' => $chargeMethod]);
    }

    public function update(ChargeMethodRequest $request, ChargeMethod $chargeMethod, ChargeMethodService $methods): RedirectResponse
    {
        $methods->update($chargeMethod, $request->validated());

        return redirect()->route('charge-methods.index')->with('status', 'Calculation method saved.');
    }

    public function destroy(ChargeMethod $chargeMethod, ChargeMethodService $methods): RedirectResponse
    {
        $this->authorize('delete', $chargeMethod);
        $methods->delete($chargeMethod);

        return redirect()->route('charge-methods.index')->with('status', 'Calculation method removed.');
    }
}
