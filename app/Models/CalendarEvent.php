<?php

namespace App\Models;

use App\Enums\CalendarEventPriority;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CalendarEventType $type
 * @property CalendarEventPriority $priority
 * @property CalendarEventStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'user_id',
    'title',
    'description',
    'starts_at',
    'ends_at',
    'type',
    'priority',
    'status',
    'completed_at',
])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverlapping(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        return $query
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'completed_at' => 'datetime',
            'type' => CalendarEventType::class,
            'priority' => CalendarEventPriority::class,
            'status' => CalendarEventStatus::class,
        ];
    }
}
