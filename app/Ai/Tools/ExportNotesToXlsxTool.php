<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\NotesXlsxExportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ExportNotesToXlsxTool implements Tool
{
    public function __construct(
        private readonly NotesXlsxExportService $exportService,
    ) {}

    public function name(): string
    {
        return 'export_notes_to_xlsx';
    }

    public function description(): Stringable|string
    {
        return 'Експортира всички лични бележки на логнатия потребител в Excel файл (.xlsx). '
            .'Връща връзка за еднократно изтегляне. Изисква автентикиран потребител в текущата HTTP сесия.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => 'Няма логнат потребител.'], JSON_UNESCAPED_UNICODE);
        }

        $token = (string) Str::uuid();

        $tmpBase = tempnam(sys_get_temp_dir(), 'notes_xlsx_');
        if ($tmpBase === false) {
            return json_encode([
                'error' => 'Не може да се подготви временен файл за експорт (temp директория).',
            ], JSON_UNESCAPED_UNICODE);
        }

        unlink($tmpBase);
        $absolutePath = $tmpBase.'.xlsx';

        try {
            $count = $this->exportService->writeExportFile($user, $absolutePath);
        } catch (Throwable $e) {
            report($e);
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            return json_encode([
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Неуспешно генериране на Excel файл.',
            ], JSON_UNESCAPED_UNICODE);
        }

        Cache::put(
            'notes_export:'.$token,
            [
                'user_id' => $user->id,
                'path' => $absolutePath,
            ],
            now()->addMinutes(30),
        );

        $downloadUrl = route('dashboard.notes.export.download', ['token' => $token], true);
        $filename = 'belazhki-'.now()->format('Y-m-d-His').'.xlsx';

        return json_encode([
            'ok' => true,
            'note_count' => $count,
            'download_url' => $downloadUrl,
            'filename' => $filename,
            'hint' => 'Отвори download_url в същия браузър (сесията трябва да е логната). Връзката е еднократна.',
        ], JSON_UNESCAPED_UNICODE);
    }
}
