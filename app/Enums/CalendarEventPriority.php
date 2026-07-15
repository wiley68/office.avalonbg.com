<?php

namespace App\Enums;

use App\Support\Translations;

enum CalendarEventPriority: string
{
    case Critical = 'critical';
    case Important = 'important';
    case Standard = 'standard';
    case None = 'none';

    public function getLabel(): string
    {
        return Translations::get('calendar.priority.'.$this->value);
    }
}
