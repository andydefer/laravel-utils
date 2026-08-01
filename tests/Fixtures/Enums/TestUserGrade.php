<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Enums;

enum TestUserGrade: int
{
    case BASIC = 1;
    case PREMIUM = 2;
    case VIP = 3;
}
