<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStorageService
{
    private const Disk = 'local';

    /**
     * @var list<string>
     */
    private const AllowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    /**
     * @var list<string>
     */
    private const InlineMimeTypes = [
        'application/pdf',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    /**
     * @var array<string, string>
     */
    private const ExtensionToMime = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];

    /**
     * @var list<string>
     */
    private const GenericMimeTypes = [
        'application/octet-stream',
        'inode/x-empty',
        'application/x-empty',
    ];

    public function store(User $user, UploadedFile $file, ?string $description = null): Document
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $directory = 'documents/'.$user->id;
        $path = $file->storeAs($directory, $filename, self::Disk);

        return Document::query()->create([
            'user_id' => $user->id,
            'original_name' => $originalName,
            'storage_path' => $path,
            'mime_type' => $this->resolveMimeType($file),
            'size_bytes' => $file->getSize(),
            'description' => $description,
        ]);
    }

    public function storeFromContents(
        User $user,
        string $contents,
        string $originalName,
        string $mimeType,
        ?string $description = null,
    ): Document {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $directory = 'documents/'.$user->id;
        $path = $directory.'/'.$filename;

        $this->disk()->put($path, $contents);

        return Document::query()->create([
            'user_id' => $user->id,
            'original_name' => $originalName,
            'storage_path' => $path,
            'mime_type' => $this->resolveMimeTypeFromName($originalName, $mimeType),
            'size_bytes' => strlen($contents),
            'description' => $description,
        ]);
    }

    public function delete(Document $document): void
    {
        $disk = $this->disk();

        if ($disk->exists($document->storage_path)) {
            $disk->delete($document->storage_path);
        }

        $document->projects()->detach();
        $document->tasks()->detach();
        $document->delete();
    }

    public function download(Document $document, bool $forceAttachment = false): StreamedResponse
    {
        $headers = [
            'Content-Type' => $document->mime_type,
        ];

        if (! $forceAttachment && $this->displaysInlineInBrowser($document->mime_type)) {
            $headers['Content-Disposition'] = $this->contentDisposition(
                'inline',
                $document->original_name,
            );

            return $this->disk()->response(
                $document->storage_path,
                $document->original_name,
                $headers,
            );
        }

        $headers['Content-Disposition'] = $this->contentDisposition(
            'attachment',
            $document->original_name,
        );

        return $this->disk()->download(
            $document->storage_path,
            $document->original_name,
            $headers,
        );
    }

    public function replaceFile(Document $document, UploadedFile $file): void
    {
        $disk = $this->disk();
        $oldPath = $document->storage_path;

        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $directory = 'documents/'.$document->user_id;
        $newPath = $file->storeAs($directory, $filename, self::Disk);

        $document->update([
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $newPath,
            'mime_type' => $this->resolveMimeType($file),
            'size_bytes' => $file->getSize(),
        ]);

        if ($disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }
    }

    public function displaysInlineInBrowser(string $mimeType): bool
    {
        return in_array($mimeType, self::InlineMimeTypes, true);
    }

    public function contentDispositionHeader(
        string $mimeType,
        string $filename,
        bool $forceAttachment = false,
    ): string {
        $disposition = ! $forceAttachment && $this->displaysInlineInBrowser($mimeType)
            ? 'inline'
            : 'attachment';

        return $this->contentDisposition($disposition, $filename);
    }

    private function contentDisposition(string $disposition, string $filename): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?: 'document';

        return sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $disposition,
            addcslashes($fallback, '"\\'),
            rawurlencode($filename),
        );
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::Disk);

        return $disk;
    }

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return self::AllowedMimeTypes;
    }

    public function isAllowedUpload(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();

        if ($mimeType !== null && in_array($mimeType, self::AllowedMimeTypes, true)) {
            return true;
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === '' || ! isset(self::ExtensionToMime[$extension])) {
            return false;
        }

        if ($mimeType === null || in_array($mimeType, self::GenericMimeTypes, true)) {
            return true;
        }

        return $mimeType === self::ExtensionToMime[$extension];
    }

    public function resolveMimeType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();

        if ($mimeType !== null && in_array($mimeType, self::AllowedMimeTypes, true)) {
            return $mimeType;
        }

        $extension = strtolower($file->getClientOriginalExtension());

        return self::ExtensionToMime[$extension] ?? $mimeType ?? 'application/octet-stream';
    }

    public function resolveMimeTypeFromName(string $originalName, string $mimeType): string
    {
        if (in_array($mimeType, self::AllowedMimeTypes, true)) {
            return $mimeType;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return self::ExtensionToMime[$extension] ?? $mimeType ?: 'application/octet-stream';
    }
}
