<?php

namespace App\Models;

use App\Enums\ProjectEmailLinkStatus;
use Database\Factories\ProjectEmailLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ProjectEmailLinkStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $last_verified_at
 */
#[Fillable([
    'project_id',
    'user_id',
    'folder',
    'imap_uid',
    'uidvalidity',
    'subject',
    'from_name',
    'from_address',
    'sent_at',
    'status',
    'last_verified_at',
])]
class ProjectEmailLink extends Model
{
    /** @use HasFactory<ProjectEmailLinkFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
            'status' => ProjectEmailLinkStatus::class,
            'sent_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }
}
