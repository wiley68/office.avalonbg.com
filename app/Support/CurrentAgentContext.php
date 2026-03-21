<?php

namespace App\Support;

use App\Enums\AgentContext;

/**
 * Текущ контекст на агентския разговор за заявката (оркестратор vs бележки).
 */
final class CurrentAgentContext
{
    private static ?AgentContext $context = null;

    public static function set(AgentContext $context): void
    {
        self::$context = $context;
    }

    public static function get(): ?AgentContext
    {
        return self::$context;
    }

    public static function clear(): void
    {
        self::$context = null;
    }
}
