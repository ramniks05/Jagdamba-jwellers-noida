<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\CompanyProfileRequest;
use App\Http\Resources\CompanyResource;
use App\Services\Foundation\CompanyProfileService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function show(CompanyContext $context): CompanyResource
    {
        $company = $context->company();
        $this->authorize('view', $company);

        return new CompanyResource($company);
    }

    public function update(
        CompanyProfileRequest $request,
        CompanyProfileService $profiles,
        CompanyContext $context,
    ): CompanyResource {
        $company = $profiles->update(
            $context->company(),
            $request->safe()->except(['logo', 'remove_logo', 'signature', 'remove_signature']),
            $request->file('logo'),
            $request->boolean('remove_logo'),
            $request->file('signature'),
            $request->boolean('remove_signature'),
        );

        return new CompanyResource($company);
    }

    public function destroyLogo(CompanyProfileService $profiles, CompanyContext $context): JsonResponse
    {
        $company = $context->company();
        $this->authorize('update', $company);
        $profiles->update($company, $company->only([
            'name',
            'legal_name',
            'code',
            'email',
            'phone',
            'mobile',
            'website',
            'gstin',
            'pan',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'timezone',
            'currency_code',
            'fy_start_month',
        ]), null, true);

        return response()->json(['message' => 'Logo removed.']);
    }
}
