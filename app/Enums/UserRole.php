<?php

namespace App\Enums;

enum UserRole: string
{
    case Profiler = 'profiler';
    case Admin = 'admin';
    case User = 'user';
}
