type EmailAttachmentLike = {
    part: string;
    filename: string;
    mime_type?: string | null;
};

const INLINE_MIME_TYPES = new Set([
    'application/pdf',
    'text/plain',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/svg+xml',
]);

const INLINE_EXTENSIONS = new Set([
    'pdf',
    'txt',
    'jpg',
    'jpeg',
    'png',
    'webp',
    'gif',
    'svg',
]);

export function canOpenEmailAttachmentInline(attachment: EmailAttachmentLike): boolean {
    if (attachment.mime_type && INLINE_MIME_TYPES.has(attachment.mime_type)) {
        return true;
    }

    const extension = attachment.filename.split('.').pop()?.toLowerCase() ?? '';

    return INLINE_EXTENSIONS.has(extension);
}

export function emailAttachmentUrl(
    projectId: number,
    linkId: number,
    part: string,
    forceDownload = false,
): string {
    const baseUrl = `/internal-api/projects/${projectId}/email/${linkId}/attachments/${encodeURIComponent(part)}`;

    if (! forceDownload) {
        return baseUrl;
    }

    return `${baseUrl}?download=1`;
}

export function openEmailAttachment(
    projectId: number,
    linkId: number,
    attachment: EmailAttachmentLike,
): void {
    const forceDownload = ! canOpenEmailAttachmentInline(attachment);

    window.open(
        emailAttachmentUrl(projectId, linkId, attachment.part, forceDownload),
        '_blank',
        'noopener,noreferrer',
    );
}
