<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class DashboardCache
{
    public static function key(string $name, int $userId): string
    {
        return 'dashboard.'.$name.'.'.$userId;
    }

    public static function remember(string $name, int $userId, callable $callback): mixed
    {
        return Cache::remember(
            self::key($name, $userId),
            now()->addDay(),
            $callback,
        );
    }

    public static function forgetAllForUser(int $userId): void
    {
        foreach (self::keys() as $name) {
            Cache::forget(self::key($name, $userId));
        }
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            'admin_user_count',
        ];
    }
}
