<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasMasterRecord
{
    use BelongsToCompany, HasPublicUuid, SoftDeletes;

    public function initializeHasMasterRecord(): void
    {
        $this->mergeCasts([
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);
    }

    public static function bootHasMasterRecord(): void
    {
        static::saving(function (Model $model): void {
            if (! $model->masterCodesAreUppercase()) {
                return;
            }

            foreach (['code', 'design_number'] as $column) {
                $value = $model->getAttribute($column);

                if (is_string($value)) {
                    $model->setAttribute($column, strtoupper(trim($value)));
                }
            }
        });
    }

    public function masterCodesAreUppercase(): bool
    {
        return true;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_active'), true);
    }

    /**
     * Soft deletes skip the database foreign keys, so removal checks every table that points here.
     *
     * @param  array<string, array{0: string, 1: string}>  $uses  table => [column, singular label]
     */
    protected function usageBlocker(string $noun, array $uses): ?string
    {
        foreach ($uses as $table => [$column, $label]) {
            $count = DB::table($table)->where($column, $this->getKey())->count();

            if ($count > 0) {
                return 'This '.$noun.' is used by '.$count.' '.Str::plural($label, $count).'. Untick Active to hide it instead.';
            }
        }

        return null;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where($query->qualifyColumn('name'), 'like', $like);

            foreach (['code', 'design_number'] as $column) {
                if (in_array($column, $query->getModel()->getFillable(), true)) {
                    $query->orWhere($query->qualifyColumn($column), 'like', $like);
                }
            }
        });
    }
}
