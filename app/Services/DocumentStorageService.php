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
    ];

    public function store(User $user, UploadedFile $file, ?string $description = null): Document
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        $directory = 'documents/' . $user->id;
        $path = $file->storeAs($directory, $filename, self::Disk);

        return Document::query()->create([
            'user_id' => $user->id,
            'original_name' => $originalName,
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
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

    public function download(Document $document): StreamedResponse
    {
        return $this->disk()->download(
            $document->storage_path,
            $document->original_name,
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
}
