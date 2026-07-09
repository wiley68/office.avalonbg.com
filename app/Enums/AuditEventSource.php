<?php

namespace App\Enums;

enum AuditEventSource: string
{
    case Office = 'office';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'Офис',
            self::Api => 'API',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $case) => $case->value, self::cases()),
            array_map(fn (self $case) => $case->label(), self::cases()),
        );
    }
}
