<?php

namespace App\Enums;

use App\Support\Translations;

enum CalendarEventStatus: string
{
    case Active = 'active';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return Translations::get('calendar.status.'.$this->value);
    }
}
