<?php

namespace App\Models;

use App\Concerns\PreservesCreator;
use Database\Factories\AccessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'category', 'content', 'is_encrypted'])]
class Access extends Model
{
    /** @use HasFactory<AccessFactory> */
    use HasFactory, PreservesCreator;

    public function version(): string
    {
        return hash('sha256', json_encode([
            $this->id, $this->group_id, $this->name, $this->category, $this->content, $this->is_encrypted,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }
}
