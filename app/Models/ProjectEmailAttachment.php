<?php

namespace App\Models;

use Database\Factories\ProjectEmailAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_email_link_id', 'imap_part', 'document_id'])]
class ProjectEmailAttachment extends Model
{
    /** @use HasFactory<ProjectEmailAttachmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProjectEmailAttachment $attachment): void {
            $attachment->group_id = $attachment->emailLink()->firstOrFail()->group_id;
        });
    }

    /**
     * @return BelongsTo<ProjectEmailLink, $this>
     */
    public function emailLink(): BelongsTo
    {
        return $this->belongsTo(ProjectEmailLink::class, 'project_email_link_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
