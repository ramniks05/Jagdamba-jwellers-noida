<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\AdvanceOrder;
use App\Models\Payment;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdvanceOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');
        $payment = $this->route('payment');

        return $order instanceof AdvanceOrder
            && $payment instanceof Payment
            && (int) $payment->advance_order_id === (int) $order->id
            && $payment->direction === 'in'
            && (bool) $this->user()?->can('update', $order);
    }

    protected function prepareForValidation(): void
    {
        $tz = config('app.timezone');
        $raw = trim((string) $this->input('received_at', ''));

        if ($raw === '') {
            $payment = $this->route('payment');
            $raw = $payment instanceof Payment && $payment->received_at
                ? $payment->received_at->timezone($tz)->format('Y-m-d\TH:i')
                : now($tz)->format('Y-m-d\TH:i');
        }

        $when = Carbon::parse($raw, $tz);

        if (! $when->isSameDay(now($tz))) {
            $raw = $when->copy()->setTime(12, 0)->format('Y-m-d\TH:i');
        }

        $this->merge(['received_at' => $raw]);
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
            'received_at' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $when = Carbon::parse((string) $this->input('received_at'), config('app.timezone'));

            if ($when->isFuture()) {
                $validator->errors()->add('received_at', 'Payment date and time cannot be in the future.');
            }
        });
    }
}
