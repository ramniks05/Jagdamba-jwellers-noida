<?php

namespace App\Services\Masters;

use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterRecordService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $class, Company $company, array $attributes, ?callable $customize = null): Model
    {
        return DB::transaction(function () use ($class, $company, $attributes, $customize) {
            $this->context->ensureId((int) $company->id);
            $record = new $class;
            $record->setAttribute('company_id', $company->id);
            $this->fill($record, $attributes);
            if ($customize) {
                $customize($record, $attributes);
            }
            $record->save();

            return $record->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $record, array $attributes, ?callable $customize = null): Model
    {
        return DB::transaction(function () use ($record, $attributes, $customize) {
            $this->context->ensureId((int) $record->getAttribute('company_id'));
            $this->fill($record, $attributes);
            if ($customize) {
                $customize($record, $attributes);
            }
            $record->save();

            return $record->refresh();
        });
    }

    public function delete(Model $record): void
    {
        DB::transaction(function () use ($record) {
            $this->context->ensureId((int) $record->getAttribute('company_id'));

            if (method_exists($record, 'deletionBlocker')) {
                $message = $record->deletionBlocker();

                if (is_string($message) && $message !== '') {
                    throw ValidationException::withMessages([
                        'record' => $message,
                    ]);
                }
            }

            $record->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fill(Model $record, array $attributes): void
    {
        $fields = array_intersect_key($attributes, array_flip($record->getFillable()));
        unset($fields['company_id'], $fields['is_system'], $fields['parent_id'], $fields['metal_type_id'], $fields['collection_id'], $fields['category_id'], $fields['fineness'], $fields['applies_to']);

        $record->fill($fields);
        $record->setAttribute('is_active', filter_var($attributes['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $record->setAttribute('sort_order', (int) ($attributes['sort_order'] ?? 0));
    }
}
