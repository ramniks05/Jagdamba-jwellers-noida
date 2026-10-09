<?php

namespace App\Http\Requests\Masters;

use App\Support\IdentityRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->existing();

        if ($record) {
            return (bool) $this->user()?->can('update', $record);
        }

        return (bool) $this->user()?->can('create', $this->modelClass());
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => Str::upper(trim((string) $this->input('code'))),
            ]);
        }

        if ($this->input('sort_order') === null || $this->input('sort_order') === '') {
            $this->merge(['sort_order' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $record = $this->existing();

        return [
            'name' => ['required', 'string', 'max:80', $this->uniqueName()],
            'code' => [
                'required',
                'regex:'.IdentityRules::CODE,
                Rule::unique($this->table(), 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->user()->company_id))
                    ->ignore($record?->id),
            ],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This name is already in the list.',
            'code.unique' => 'Another entry already uses this code.',
            'code.regex' => 'Use 2 to 20 letters or numbers for the code, with no spaces.',
            'design_number.regex' => 'Start the design number with a letter or number. Use up to 40 letters, numbers, dots, dashes or slashes.',
        ];
    }

    protected function uniqueName(): Unique
    {
        return Rule::unique($this->table(), 'name')
            ->where(fn ($query) => $query->where('company_id', $this->user()->company_id)->whereNull('deleted_at'))
            ->ignore($this->existing()?->id);
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        $controller = $this->route()?->getController();

        if (! is_object($controller) || ! method_exists($controller, 'modelClass')) {
            abort(404);
        }

        return $controller::modelClass();
    }

    public function existing(): ?Model
    {
        $controller = $this->route()?->getController();

        if (! is_object($controller) || ! method_exists($controller, 'routeKey')) {
            return null;
        }

        return $this->recordFromRoute($controller->routeKey(), $this->modelClass());
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected function recordFromRoute(string $key, string $class): ?Model
    {
        $value = $this->route($key);

        if ($value instanceof $class) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $class::query()->where('uuid', $value)->first();
    }

    protected function table(): string
    {
        return (new ($this->modelClass()))->getTable();
    }
}
