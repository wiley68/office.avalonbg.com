const MIME_TYPE_ICON_MAP: Record<string, string> = {
    'application/pdf': 'pdf',
    'application/msword': 'word',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
        'word',
    'application/vnd.ms-excel': 'excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
        'excel',
    'text/plain': 'text',
    'image/jpeg': 'jpeg',
    'image/png': 'png',
    'image/webp': 'webp',
    'image/gif': 'gif',
};

const ICON_BASE_PATH = '/images/document-types';

export function documentTypeIconUrl(
    mimeType: string,
    version?: string | number | null,
): string {
    const iconName = MIME_TYPE_ICON_MAP[mimeType] ?? 'file';
    const baseUrl = `${ICON_BASE_PATH}/${iconName}.svg`;

    if (version === undefined || version === null || version === '') {
        return baseUrl;
    }

    return `${baseUrl}?v=${encodeURIComponent(String(version))}`;
}

export const ALLOWED_DOCUMENT_MIME_TYPES = Object.keys(MIME_TYPE_ICON_MAP);
