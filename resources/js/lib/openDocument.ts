import { download as downloadDocument } from '@/routes/documents';

export function openDocument(documentId: number): void {
    window.open(
        downloadDocument(documentId).url,
        '_blank',
        'noopener,noreferrer',
    );
}

export function saveDocumentLocally(documentId: number): void {
    window.open(
        downloadDocument(documentId, { query: { download: 1 } }).url,
        '_blank',
        'noopener,noreferrer',
    );
}
