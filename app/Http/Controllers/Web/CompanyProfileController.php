<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\CompanyProfileRequest;
use App\Services\Foundation\CompanyProfileService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function file(string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', $path);
        abort_unless(
            preg_match('#^companies/[0-9a-f-]{36}/(?:logo|signature)-[a-z0-9]+\.(?:jpg|png|webp)$#', $path) === 1,
            404,
        );
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, headers: [
            'Cache-Control' => 'public, max-age=86400',
        ]);
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
