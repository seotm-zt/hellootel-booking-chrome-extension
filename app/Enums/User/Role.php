<?php

namespace App\Enums\User;

enum Role: string
{
    case ADMIN    = 'admin';
    case OPERATOR = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN    => 'Admin',
            self::OPERATOR => 'Operator',
        };
    }
}
