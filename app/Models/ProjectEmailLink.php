<?php

namespace App\Models;

use App\Enums\ProjectEmailLinkStatus;
use Database\Factories\ProjectEmailLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ProjectEmailLinkStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $archived_at
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
    'body_text',
    'body_html',
    'conversation_key',
    'status',
    'last_verified_at',
    'archived_at',
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
     * @return HasMany<ProjectEmailAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectEmailAttachment::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
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
            'archived_at' => 'datetime',
        ];
    }
}
