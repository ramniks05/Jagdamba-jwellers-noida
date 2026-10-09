<?php

namespace App\Http\Requests\Foundation;

use App\Models\FinancialYear;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Throwable;

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
            'end_date' => ['required', 'date', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    $start = Carbon::parse((string) $this->input('start_date'));
                    $end = Carbon::parse((string) $value);
                } catch (Throwable) {
                    return;
                }

                if ($end->gte($start->copy()->addYear())) {
                    $fail('A financial year can be at most 12 months long.');
                }
            }],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
