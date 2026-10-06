<?php

namespace App\Http\Requests\Foundation;

use App\Models\FinancialYear;
use Illuminate\Foundation\Http\FormRequest;

class FinancialYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $year = $this->route('financialYear');

        if ($year instanceof FinancialYear) {
            return (bool) $this->user()?->can('update', $year);
        }

        return (bool) $this->user()?->can('create', FinancialYear::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
