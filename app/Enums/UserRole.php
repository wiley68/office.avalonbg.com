<?php

namespace App\Enums;

use App\Support\Translations;

enum UserRole: string
{
    case Profiler = 'profiler';
    case Admin = 'admin';
    case User = 'user';

    public function getLabel(): string
    {
        return Translations::get('roles.'.$this->value);
    }
}
