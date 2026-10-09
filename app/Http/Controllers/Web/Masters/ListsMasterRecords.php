<?php

namespace App\Http\Controllers\Web\Masters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

trait ListsMasterRecords
{
    protected function visibility(Request $request): string
    {
        return in_array($request->query('show'), ['active', 'hidden'], true) ? (string) $request->query('show') : 'all';
    }

    protected function search(Request $request): string
    {
        return trim((string) $request->query('search', ''));
    }

    protected function visible(Builder|Relation $query, string $show): Builder|Relation
    {
        return $query->when($show !== 'all', fn ($rows) => $rows->where($rows->qualifyColumn('is_active'), $show === 'active'));
    }
}
