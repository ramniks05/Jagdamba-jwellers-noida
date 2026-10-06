<?php

namespace App\Http\Requests\Masters;

use App\Enums\StoneGradeKind;
use App\Models\StoneGrade;
use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoneGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grade = $this->existing();

        if ($grade) {
            return (bool) $this->user()?->can('update', $grade);
        }

        return (bool) $this->user()?->can('create', StoneGrade::class);
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
        $grade = $this->existing();

        return [
            'kind' => ['required', Rule::enum(StoneGradeKind::class)],
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required',
                'regex:'.IdentityRules::CODE,
                Rule::unique('stone_grades', 'code')
                    ->where(fn ($query) => $query
                        ->where('company_id', $this->user()->company_id)
                        ->where('kind', $this->input('kind')))
                    ->ignore($grade?->id),
            ],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function existing(): ?StoneGrade
    {
        $value = $this->route('stoneGrade');

        if ($value instanceof StoneGrade) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return StoneGrade::query()->where('uuid', $value)->first();
    }
}
