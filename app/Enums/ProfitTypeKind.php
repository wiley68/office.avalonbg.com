<?php

namespace App\Enums;

use App\Support\Translations;

enum ProfitTypeKind: string
{
    case Income = 'income';
    case Expense = 'expense';

    public function getLabel(): string
    {
        return Translations::get('profits.kinds.'.$this->value);
    }
}
