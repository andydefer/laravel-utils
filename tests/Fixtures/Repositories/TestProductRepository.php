<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelUtils\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelUtils\Tests\Fixtures\Records\TestProductFilterRecord;
use AndyDefer\LaravelUtils\Tests\Fixtures\Records\TestProductRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class TestProductRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(
            modelClass: TestProduct::class,
            recordClass: TestProductRecord::class,
        );
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof TestProductFilterRecord) {
            return;
        }

        if ($filters->name !== null) {
            $query->where('name', 'like', '%'.$filters->name.'%');
        }

        if ($filters->min_price !== null) {
            $query->where('price', '>=', $filters->min_price);
        }

        if ($filters->max_price !== null) {
            $query->where('price', '<=', $filters->max_price);
        }

        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        if ($filters->is_deleted === true) {
            $query->onlyTrashed();
        } elseif ($filters->is_deleted === false) {
            $query->withoutTrashed();
        }

        // ✅ Filtre cluster_query
        if ($filters->cluster_query !== null) {
            $query->whereCluster('metadata', $filters->cluster_query);
        }
    }
}
