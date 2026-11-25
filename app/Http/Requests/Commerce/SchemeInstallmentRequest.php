<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\GoldScheme;
use App\Models\SchemeEnrollment;
use App\Support\Limits;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SchemeInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $enrollment instanceof SchemeEnrollment && (bool) $this->user()?->can('create', GoldScheme::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', Limits::MONEY],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:80'],
            'paid_on' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $enrollment = $this->route('enrollment');

            if ($validator->errors()->isNotEmpty() || ! $this->filled('paid_on') || ! $enrollment instanceof SchemeEnrollment) {
                return;
            }

            $paid = Carbon::parse((string) $this->input('paid_on'), config('app.timezone'))->startOfDay();
            $today = now(config('app.timezone'))->startOfDay();

            if ($paid->gt($today)) {
                $validator->errors()->add('paid_on', 'Paid date cannot be in the future.');
            }

            if ($enrollment->started_on && $paid->lt($enrollment->started_on->copy()->startOfDay())) {
                $validator->errors()->add('paid_on', 'Paid date cannot be before the member\'s start date ('.$enrollment->started_on->format('d-m-Y').').');
            }
        });
    }
}
