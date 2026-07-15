<?php

namespace App\Services;

use App\Models\Document;
use App\Models\ProjectEmailAttachment;
use App\Support\Translations;
use Illuminate\Validation\ValidationException;

class DocumentUsageService
{
    /**
     * @return list<array{blocking: bool, message: string}>
     */
    public function usages(Document $document): array
    {
        $usages = [];

        $emailAttachments = ProjectEmailAttachment::query()
            ->where('document_id', $document->id)
            ->with(['emailLink.project:id,name'])
            ->get();

        foreach ($emailAttachments as $attachment) {
            $projectName = $attachment->emailLink->project->name;
            $subject = $attachment->emailLink->subject
                ?: Translations::get('projects.email.no_subject');

            $usages[] = [
                'blocking' => true,
                'message' => Translations::get('documents.usage.project_email', [
                    'project' => $projectName,
                    'subject' => $subject,
                ]),
            ];
        }

        $document->loadMissing([
            'projects:id,name',
            'tasks:id,project_id,name',
            'tasks.project:id,name',
        ]);

        foreach ($document->projects as $project) {
            $usages[] = [
                'blocking' => false,
                'message' => Translations::get('documents.usage.project', [
                    'project' => $project->name,
                ]),
            ];
        }

        foreach ($document->tasks as $task) {
            $usages[] = [
                'blocking' => false,
                'message' => Translations::get('documents.usage.task', [
                    'project' => $task->project->name,
                    'task' => $task->name,
                ]),
            ];
        }

        return $usages;
    }

    public function assertDeletable(Document $document): void
    {
        $usages = $this->usages($document);
        $blockingUsages = array_values(array_filter(
            $usages,
            fn (array $usage): bool => $usage['blocking'],
        ));

        if ($blockingUsages === []) {
            return;
        }

        $lines = array_map(
            fn (array $usage): string => '• '.$usage['message'],
            $usages,
        );

        throw ValidationException::withMessages([
            'document' => implode(
                "\n",
                array_filter([
                    Translations::get('documents.errors.in_use'),
                    ...$lines,
                    Translations::get('documents.errors.in_use_footer'),
                ]),
            ),
        ]);
    }
}
