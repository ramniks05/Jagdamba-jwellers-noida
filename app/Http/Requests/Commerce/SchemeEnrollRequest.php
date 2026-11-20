<?php

namespace App\Http\Requests\Commerce;

use App\Models\GoldScheme;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SchemeEnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scheme = $this->route('scheme');

        return $scheme instanceof GoldScheme && (bool) $this->user()?->can('create', GoldScheme::class);
    }

    protected function prepareForValidation(): void
    {
        $startedOn = trim((string) $this->input('started_on', ''));

        $this->merge([
            'started_on' => $startedOn !== ''
                ? Carbon::parse($startedOn, config('app.timezone'))->toDateString()
                : now(config('app.timezone'))->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_uuid' => ['required', 'uuid', Rule::exists('customers', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_system', false))],
            'started_on' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('started_on')) {
                return;
            }

            $started = Carbon::parse((string) $this->input('started_on'), config('app.timezone'))->startOfDay();
            if ($started->gt(now(config('app.timezone'))->startOfDay())) {
                $validator->errors()->add('started_on', 'Start date cannot be in the future.');
            }
        });
    }
}
