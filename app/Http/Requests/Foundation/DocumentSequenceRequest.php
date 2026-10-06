<?php

namespace App\Http\Requests\Foundation;

use App\Enums\SequenceResetPolicy;
use App\Models\DocumentSequence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sequence = $this->route('documentSequence');

        return $sequence instanceof DocumentSequence
            && (bool) $this->user()?->can('update', $sequence);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'separator' => ['required', 'string', 'max:3', 'regex:/^[\-\/_.]+$/'],
            'padding' => ['required', 'integer', 'between:1,10'],
            'next_number' => ['required', 'integer', 'min:1'],
            'reset_policy' => ['required', Rule::enum(SequenceResetPolicy::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
