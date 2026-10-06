<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $context = app(CompanyContext::class);

            if ($context->isBypassed()) {
                return;
            }

            $companyId = $context->id();

            if (! $companyId) {
                $builder->whereRaw('0 = 1');

                return;
            }

            $builder->where($builder->qualifyColumn('company_id'), $companyId);
        });

        static::creating(function (Model $model): void {
            $context = app(CompanyContext::class);
            $companyId = $context->id();

            if (! $model->getAttribute('company_id') && $companyId && ! $context->isBypassed()) {
                $model->setAttribute('company_id', $companyId);
            }

            if (! $model->getAttribute('company_id')) {
                throw new RuntimeException($model::class.' requires a company_id.');
            }

            if (! $context->isBypassed() && $companyId && (int) $model->getAttribute('company_id') !== $companyId) {
                throw new RuntimeException($model::class.' cannot be created for a different company.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('company_id')) {
                throw new RuntimeException('company_id cannot be changed.');
            }
        });
    }
}
