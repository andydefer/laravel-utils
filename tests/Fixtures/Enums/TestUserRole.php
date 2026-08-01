<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Enums;

enum TestUserRole: string
{
    case ADMIN = 'admin';
    case DOCTOR = 'doctor';
    case USER = 'user';
}
