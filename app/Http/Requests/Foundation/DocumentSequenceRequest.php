<?php

namespace App\Http\Requests\Foundation;

use App\Enums\SequenceResetPolicy;
use App\Models\DocumentSequence;
use Closure;
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
        $sequence = $this->route('documentSequence');

        return [
            'prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', function (string $attribute, mixed $value, Closure $fail) use ($sequence): void {
                $taken = DocumentSequence::query()
                    ->where('company_id', $sequence->company_id)
                    ->where('branch_id', $sequence->branch_id)
                    ->where('document_type', '!=', $sequence->document_type->value)
                    ->whereRaw('upper(prefix) = ?', [strtoupper((string) $value)])
                    ->first();

                if ($taken) {
                    $fail('The '.$taken->document_type->label().' numbers already use '.strtoupper((string) $value).'. Pick a different prefix so bills are not confused.');
                }
            }],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'separator' => ['required', 'string', 'max:3', 'regex:/^[\-\/_.]+$/'],
            'padding' => ['required', 'integer', 'between:1,10'],
            'next_number' => ['required', 'integer', 'min:1'],
            'reset_policy' => ['required', Rule::enum(SequenceResetPolicy::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prefix.regex' => 'Use letters and digits only in the prefix, like INV.',
            'suffix.regex' => 'Use letters and digits only in the suffix.',
            'separator.regex' => 'Use a dash, slash, dot or underscore as the separator.',
        ];
    }
}
