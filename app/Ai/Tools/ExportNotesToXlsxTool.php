<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\NotesXlsxExportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
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
        /*
         * Празна схема [] кара Prism да изпрати "properties": [] (JSON масив), а не обект — xAI връща 400.
         * Нужен е поне един опционален параметър, за да се генерира валиден JSON Schema обект.
         */
        return [
            'confirm' => $schema
                ->boolean()
                ->description('Потвърди експорт на всички бележки в Excel (true). По избор — може да се пропусне.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => 'Няма логнат потребител.'], JSON_UNESCAPED_UNICODE);
        }

        try {
            $result = $this->exportService->createPendingDownload($user);
        } catch (Throwable $e) {
            report($e);

            return json_encode([
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Неуспешно генериране на Excel файл.',
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            ...$result,
            'hint' => 'Отвори download_url в същия браузър (сесията трябва да е логната). Връзката е еднократна.',
        ], JSON_UNESCAPED_UNICODE);
    }
}
