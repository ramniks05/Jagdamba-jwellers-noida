<?php

namespace App\Http\Requests\Commerce;

use App\Models\StockTray;
use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StockTrayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tray = $this->route('tray');

        if ($tray instanceof StockTray) {
            return (bool) $this->user()?->can('update', $tray);
        }

        return (bool) $this->user()?->can('create', StockTray::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
        ]);

        if ($this->input('sort_order') === null || $this->input('sort_order') === '') {
            $this->merge(['sort_order' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tray = $this->route('tray');
        $trayId = $tray instanceof StockTray ? $tray->id : null;

        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required',
                'regex:'.IdentityRules::CODE,
                Rule::unique('stock_trays', 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->user()->company_id))
                    ->ignore($trayId),
            ],
            'grams' => ['required', 'numeric', 'gte:0'],
            'pieces' => ['required', 'integer', $trayId ? 'min:0' : 'min:1'],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use 2 to 20 letters or numbers for the code, with no spaces.',
            'pieces.min' => 'Enter how many pieces are on the tray.',
        ];
    }
}
