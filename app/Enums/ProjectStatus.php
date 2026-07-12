<?php

namespace App\Enums;

use App\Support\Translations;

enum ProjectStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Deferred = 'deferred';

    public function getLabel(): string
    {
        return Translations::get('projects.status.'.$this->value);
    }
}
