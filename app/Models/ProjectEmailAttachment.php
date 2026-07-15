<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_email_link_id', 'imap_part', 'document_id'])]
class ProjectEmailAttachment extends Model
{
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
