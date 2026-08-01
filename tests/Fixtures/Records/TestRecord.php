<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Traits\Hydratable;

final class TestRecord extends AbstractRecord
{
    use Hydratable;

    public function __construct(
        public readonly int $age,
        public readonly string $city,
        public readonly bool $is_active,
    ) {}

}
