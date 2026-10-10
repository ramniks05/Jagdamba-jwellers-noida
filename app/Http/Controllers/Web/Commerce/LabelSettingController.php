<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Commerce\JewelleryLabelZplService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LabelSettingController extends Controller
{
    public function edit(SettingService $settings, CompanyContext $context): View
    {
        $this->authorize('inventory.print');
        $fields = [];

        foreach ($settings->formFields($context->company()) as $index => $field) {
            if ($field['group'] === 'label') {
                $fields[$field['key']] = $field + ['index' => $index];
            }
        }

        return view('commerce.labels.settings', [
            'fields' => $fields,
            'canManage' => auth()->user()->can('update', Setting::class),
        ]);
    }

    public function update(Request $request, SettingService $settings, JewelleryLabelZplService $labels, CompanyContext $context): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $catalog = array_filter(config('foundation.settings'), fn (array $meta) => $meta['group'] === 'label');
        $rows = $request->input('settings', []);
        $values = [];
        $positions = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;

            if (is_string($key) && isset($catalog[$key])) {
                $values[$key] = $catalog[$key]['type'] === 'boolean'
                    ? filter_var($row['value'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    : ($row['value'] ?? null);
                $positions[substr($key, 6)] = $index;
            }
        }

        if (array_diff(array_keys($catalog), array_keys($values)) !== []) {
            throw ValidationException::withMessages(['settings' => 'Submit every tag setting.']);
        }

        $short = [];

        foreach ($values as $key => $value) {
            $short[substr($key, 6)] = $value;
        }

        try {
            $labels->preview(null, $short);
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $errors[isset($positions[$field]) ? 'settings.'.$positions[$field].'.value' : 'settings'] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        $settings->setMany($context->company(), $values);

        return redirect()->route('labels.settings.edit')->with('status', 'Tag settings saved.');
    }
}
