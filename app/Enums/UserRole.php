<?php

namespace App\Enums;

enum UserRole: string
{
    case Profiler = 'profiler';
    case Admin = 'admin';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::Profiler => 'Профайлер',
            self::Admin => 'Администратор',
            self::User => 'Потребител',
        };
    }
}
