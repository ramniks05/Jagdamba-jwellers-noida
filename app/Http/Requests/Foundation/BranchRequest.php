<?php

namespace App\Http\Requests\Foundation;

use App\Models\Branch;
use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    use NormalizesShopIdentity;

    public function authorize(): bool
    {
        $branch = $this->route('branch');

        if ($branch instanceof Branch) {
            return (bool) $this->user()?->can('update', $branch);
        }

        return (bool) $this->user()?->can('create', Branch::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = $this->route('branch') instanceof Branch ? $this->route('branch')->id : null;

        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'required',
                'regex:'.IdentityRules::CODE,
                Rule::unique('branches', 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->user()->company_id))
                    ->ignore($branchId),
            ],
            'is_head_office' => ['sometimes', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
            'timezone' => ['nullable', 'timezone'],
            ...$this->contactRules(),
            ...$this->addressRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->identityMessages();
    }
}
