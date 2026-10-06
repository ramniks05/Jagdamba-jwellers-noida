<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
