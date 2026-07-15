<?php

namespace App\Enums;

use App\Support\Translations;

enum CalendarEventType: string
{
    case Action = 'action';
    case Task = 'task';
    case Meeting = 'meeting';
    case Personal = 'personal';
    case Reminder = 'reminder';

    public function getLabel(): string
    {
        return Translations::get('calendar.type.'.$this->value);
    }
}
