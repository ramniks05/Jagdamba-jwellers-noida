<?php

namespace App\Http\Requests\Commerce;

use App\Models\AdvanceOrder;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;

class AdvanceOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof AdvanceOrder && (bool) $this->user()?->can('update', $order);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_making' => trim((string) $this->input('estimated_making')) === '' ? '0' : $this->input('estimated_making'),
            'due_on' => trim((string) $this->input('due_on')) === '' ? null : $this->input('due_on'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:160'],
            'design_notes' => ['nullable', 'string', 'max:1000'],
            'expected_weight' => ['required', 'numeric', 'gt:0', Limits::WEIGHT],
            'rate_per_gram' => ['required', 'numeric', 'gt:0', Limits::RATE],
            'estimated_making' => ['required', 'numeric', 'gte:0', Limits::MONEY],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
