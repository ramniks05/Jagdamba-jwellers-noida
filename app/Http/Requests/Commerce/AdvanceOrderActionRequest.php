<?php

namespace App\Http\Requests\Commerce;

use App\Enums\PaymentMethod;
use App\Models\AdvanceOrder;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdvanceOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof AdvanceOrder && (bool) $this->user()?->can('update', $order);
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('orders.cancel') && trim((string) $this->input('refund')) === '') {
            $this->merge(['refund' => '0']);
        }

        if ($this->routeIs('orders.advance') && $this->filled('amount')) {
            $tz = config('app.timezone');
            $raw = trim((string) $this->input('received_at', ''));

            if ($raw === '') {
                $raw = now($tz)->format('Y-m-d\TH:i');
            }

            $when = Carbon::parse($raw, $tz);

            if (! $when->isSameDay(now($tz))) {
                $raw = $when->copy()->setTime(12, 0)->format('Y-m-d\TH:i');
            }

            $this->merge(['received_at' => $raw]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->routeIs('orders.cancel')) {
            return [
                'refund' => ['required', 'numeric', 'gte:0', Limits::MONEY],
                'method' => ['required', Rule::enum(PaymentMethod::class)],
                'reference' => ['nullable', 'string', 'max:80'],
            ];
        }

        if ($this->routeIs('orders.advance')) {
            if ($this->filled('advance_held') || ! $this->filled('amount')) {
                return [
                    'advance_held' => ['required', 'numeric', 'gte:0', Limits::MONEY],
                    'method' => ['required', Rule::enum(PaymentMethod::class)],
                    'reference' => ['nullable', 'string', 'max:80'],
                ];
            }

            return [
                'amount' => ['required', 'numeric', 'gt:0', Limits::MONEY],
                'method' => ['required', Rule::enum(PaymentMethod::class)],
                'reference' => ['nullable', 'string', 'max:80'],
                'received_at' => ['required', 'date'],
            ];
        }

        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->routeIs('orders.advance') || ! $this->filled('amount') || ! $this->filled('received_at')) {
                return;
            }

            $when = Carbon::parse((string) $this->input('received_at'), config('app.timezone'));

            if ($when->isFuture()) {
                $validator->errors()->add('received_at', 'Payment date and time cannot be in the future.');
            }
        });
    }
}
