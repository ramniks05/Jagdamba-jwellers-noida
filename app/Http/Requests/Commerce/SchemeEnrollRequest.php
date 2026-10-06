<?php

namespace App\Http\Requests\Commerce;

use App\Models\GoldScheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchemeEnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scheme = $this->route('scheme');

        return $scheme instanceof GoldScheme && (bool) $this->user()?->can('create', GoldScheme::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_uuid' => ['required', 'uuid', Rule::exists('customers', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_system', false))],
        ];
    }
}
