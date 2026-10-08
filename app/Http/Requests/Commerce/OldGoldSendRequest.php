<?php

namespace App\Http\Requests\Commerce;

use App\Models\OldGoldExchange;
use App\Models\OldGoldMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OldGoldSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', OldGoldExchange::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at');

        return [
            'purity_uuid' => ['required', 'uuid', Rule::exists('purities', 'uuid')->where($shop)],
            'kind' => ['required', Rule::in(array_keys(OldGoldMovement::SEND_KINDS))],
            'gross_weight' => ['required', 'numeric', 'gt:0'],
            'fine_weight' => ['required', 'numeric', 'gte:0'],
            'party' => ['nullable', 'string', 'max:120'],
            'amount_received' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
