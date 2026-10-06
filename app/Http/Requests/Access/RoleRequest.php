<?php

namespace App\Http\Requests\Access;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        if ($role instanceof Role) {
            return (bool) $this->user()?->can('update', $role);
        }

        return (bool) $this->user()?->can('create', Role::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('company_id', $this->user()->company_id))
                    ->ignore($roleId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(array_keys(config('access.permissions')))],
        ];
    }
}
