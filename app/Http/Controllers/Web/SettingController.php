<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\SettingUpdateRequest;
use App\Models\Setting;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(SettingService $settings, NumberFormatService $format, CompanyContext $context): View
    {
        $this->authorize('viewAny', Setting::class);
        $company = $context->company();
        $groups = [];

        foreach ($settings->formFields($company) as $index => $field) {
            $groups[$field['group']]['label'] = $field['group_label'];
            $groups[$field['group']]['fields'][] = $field + ['index' => $index];
        }

        $timezone = (string) $settings->get('datetime.timezone', $company);
        $moment = now()->timezone($timezone);

        return view('foundation.settings.edit', [
            'groups' => $groups,
            'currencyPreview' => $format->money('1234567.5', $company),
            'weightPreview' => $format->weight('12.346', $company),
            'datePreview' => $moment->format((string) $settings->get('datetime.date_format', $company)).' '.$moment->format((string) $settings->get('datetime.time_format', $company)),
            'canManage' => auth()->user()->can('update', Setting::class),
        ]);
    }

    public function update(SettingUpdateRequest $request, SettingService $settings, CompanyContext $context): RedirectResponse
    {
        $settings->setMany($context->company(), $request->values());

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
