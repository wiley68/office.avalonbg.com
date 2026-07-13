export interface DocumentListItem {
    id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    description: string | null;
    created_at: string;
}

export function formatDocumentSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function formatDocumentDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}

export function formatDocumentMimeType(mimeType: string): string {
    const subtype = mimeType.split('/').at(1);

    if (!subtype) {
        return mimeType.toUpperCase();
    }

    return subtype.replace('.', ' ').toUpperCase();
}
