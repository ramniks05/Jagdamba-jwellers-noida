<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\CustomerFormRequest;
use App\Models\Company;
use App\Services\Commerce\CustomerIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerFormController extends Controller
{
    public function create(Company $company): View
    {
        abort_unless($company->isOperational(), 404);

        return view('commerce.customer-form.show', [
            'company' => $company,
        ]);
    }

    public function store(CustomerFormRequest $request, Company $company, CustomerIntakeService $intakes): RedirectResponse
    {
        abort_unless($company->isOperational(), 404);
        $result = $intakes->receive($company, $request->validated());

        return redirect()
            ->route('customer-form.create', $company)
            ->with('form_result', $result['result']);
    }
}
