<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\SettingUpdateRequest;
use App\Models\Setting;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function index(SettingService $settings, CompanyContext $context): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $data = array_map(fn (array $field) => [
            'key' => $field['key'],
            'group' => $field['group'],
            'label' => $field['label'],
            'value' => $field['value'],
        ], $settings->formFields($context->company()));

        return response()->json(['data' => $data]);
    }

    public function update(SettingUpdateRequest $request, SettingService $settings, CompanyContext $context): JsonResponse
    {
        $settings->setMany($context->company(), $request->values());

        return $this->index($settings, $context);
    }
}
