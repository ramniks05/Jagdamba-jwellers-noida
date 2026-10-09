<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\CompanyProfileRequest;
use App\Services\Foundation\CompanyProfileService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function edit(CompanyContext $context): View
    {
        $company = $context->company();
        $this->authorize('update', $company);

        return view('foundation.company.edit', [
            'company' => $company,
            'months' => $this->months(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(
        CompanyProfileRequest $request,
        CompanyProfileService $profiles,
        CompanyContext $context,
    ): RedirectResponse {
        $profiles->update(
            $context->company(),
            $request->validated(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
            $request->file('signature'),
            $request->boolean('remove_signature'),
        );

        return redirect()->route('company.edit')->with('status', 'Shop profile saved.');
    }

    /**
     * @return array<int, string>
     */
    private function months(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }
}
