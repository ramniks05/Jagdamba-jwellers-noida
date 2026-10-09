<?php

namespace App\Http\Requests\Access;

use App\Models\User;
use App\Support\IdentityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        if ($user instanceof User) {
            return (bool) $this->user()?->can('update', $user);
        }

        return (bool) $this->user()?->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower(trim((string) $this->input('email'))),
            ]);
        }

        $this->merge([
            'roles' => array_values(array_filter((array) $this->input('roles', []))),
            'branches' => array_values(array_filter((array) $this->input('branches', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subject = $this->route('user');
        $userId = $subject instanceof User ? $subject->id : null;
        $creating = ! $subject instanceof User;

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:160',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:'.IdentityRules::PHONE],
            'password' => $creating || $this->filled('password')
                ? [$creating ? 'required' : 'nullable', 'confirmed', Password::defaults()]
                : ['nullable'],
            'is_active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['uuid'],
            'branches' => ['array'],
            'branches.*' => ['uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return IdentityRules::messages() + [
            'roles.required' => 'Pick at least one role.',
        ];
    }
}
